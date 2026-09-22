<?php

namespace Tests\Integration;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\AuditLog;
use App\Models\CaseResourceAssignment;
use App\Models\CatalogImport;
use App\Models\CatalogImportRow;
use App\Models\Institution;
use App\Models\InventoryAdjustment;
use App\Models\InventoryLot;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReservationConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        if (getenv('OPS_MYSQL_INTEGRATION_TESTS') !== '1'
            || getenv('DB_CONNECTION') !== 'mysql'
            || getenv('DB_DATABASE') !== 'ops_biomed_staging_local'
            || getenv('DB_HOST') !== '127.0.0.1'
            || getenv('DB_PORT') !== '3306'
            || ! in_array(getenv('DB_URL'), ['', false], true)) {
            $this->markTestSkipped('Use phpunit.mysql.xml and the approved local ops_biomed_staging_local database.');
        }

        parent::setUp();
    }

    public function test_two_mysql_processes_cannot_reserve_the_same_last_unit(): void
    {
        $institution = Institution::create(['name' => 'Concurrency test '.Str::uuid(), 'active' => true]);
        $surgeryType = SurgeryType::create(['code' => 'mysql-'.Str::lower(Str::random(10)), 'name' => 'MySQL concurrency', 'active' => true]);
        $patient = Patient::create(['code' => 'MYSQL-'.Str::upper(Str::random(10)), 'full_name' => 'Test Patient']);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $firstCase = $this->caseFor($institution, $surgeryType, $patient, $firstUser);
        $secondCase = $this->caseFor($institution, $surgeryType, $patient, $secondUser);
        $warehouse = Warehouse::create([
            'name' => 'Immediate '.Str::uuid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $product = Product::create([
            'product_line' => 'MR8',
            'family' => 'Fresas',
            'subfamily' => 'Concurrente',
            'product_code' => 'MYSQL-'.Str::upper(Str::random(10)),
            'name' => 'Test product',
            'classification' => 'consumible',
            'expiry_required' => false,
            'tracking_type' => 'lot',
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'MYSQL-'.Str::upper(Str::random(10)),
            'warehouse_id' => $warehouse->id,
            'quantity' => 1,
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ]);

        $testDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ops-biomed-'.Str::uuid();
        mkdir($testDirectory);
        $startPath = $testDirectory.DIRECTORY_SEPARATOR.'start';
        $workers = [];

        try {
            foreach ([[$firstUser, $firstCase], [$secondUser, $secondCase]] as [$user, $case]) {
                $suffix = (string) $user->id;
                $readyPath = $testDirectory.DIRECTORY_SEPARATOR.'ready-'.$suffix;
                $resultPath = $testDirectory.DIRECTORY_SEPARATOR.'result-'.$suffix.'.json';
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/reservation_concurrency_worker.php'),
                    (string) $user->id,
                    (string) $case->id,
                    (string) $lot->id,
                    '1',
                    (string) Str::uuid(),
                    $readyPath,
                    $startPath,
                    $resultPath,
                ], base_path(), $this->mysqlWorkerEnvironment());
                $process->start();
                $workers[] = ['process' => $process, 'ready' => $readyPath, 'result' => $resultPath];
            }

            $deadline = microtime(true) + 20;
            while (microtime(true) < $deadline
                && collect($workers)->contains(fn (array $worker): bool => ! is_file($worker['ready']))) {
                usleep(20000);
            }

            $missingReadyWorkers = array_filter($workers, fn (array $worker): bool => ! is_file($worker['ready']));
            $workerDiagnostics = array_map(fn (array $worker): array => [
                'exit_code' => $worker['process']->getExitCode(),
                'stdout' => $worker['process']->getOutput(),
                'stderr' => $worker['process']->getErrorOutput(),
            ], $missingReadyWorkers);
            $this->assertSame([], $workerDiagnostics, 'Concurrency workers did not reach the start barrier.');
            file_put_contents($startPath, 'go', LOCK_EX);

            foreach ($workers as $worker) {
                $worker['process']->wait();
                $this->assertSame(0, $worker['process']->getExitCode(), $worker['process']->getErrorOutput());
                $this->assertFileExists($worker['result']);
            }

            $results = array_map(
                fn (array $worker): array => json_decode((string) file_get_contents($worker['result']), true, flags: JSON_THROW_ON_ERROR),
                $workers,
            );
            $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['result'] === 'reserved')));
            $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['result'] === 'rejected')));
            $this->assertSame(1, (int) Reservation::query()
                ->where('inventory_lot_id', $lot->id)
                ->where('status', 'active')
                ->sum('quantity'));
            $this->assertSame(1, Reservation::query()->where('inventory_lot_id', $lot->id)->count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker['process']->isRunning()) {
                    $worker['process']->stop(1);
                }
            }

            foreach (glob($testDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($testDirectory);
        }
    }

    public function test_two_mysql_import_commits_for_the_same_product_lot_and_warehouse_do_not_duplicate_inventory(): void
    {
        $users = [User::factory()->create(), User::factory()->create()];
        $this->createCatalogWarehouses();
        $imports = [
            $this->catalogImport($users[0], 5),
            $this->catalogImport($users[1], 7),
        ];

        $results = $this->runConcurrently([
            ['catalog_commit', $users[0]->id, ['import_id' => $imports[0]->id]],
            ['catalog_commit', $users[1]->id, ['import_id' => $imports[1]->id]],
        ]);

        $this->assertSame(['completed', 'completed'], array_column($results, 'result'), json_encode($results));
        $product = Product::query()->where('product_code', 'MYSQL-CATALOG-SHARED')->firstOrFail();
        $principal = Warehouse::query()->where('name', 'ALMACEN PRINCIPAL')->firstOrFail();
        $lots = InventoryLot::query()
            ->where('product_id', $product->id)
            ->where('lot', 'MYSQL-SHARED-LOT')
            ->where('warehouse_id', $principal->id)
            ->whereDate('expiry', '2030-12-31')
            ->get();

        $this->assertCount(1, $lots);
        $this->assertContains((int) $lots->first()->quantity, [5, 7]);
        $this->assertSame(1, CatalogImport::query()->where('status', 'committed')->count());
        $this->assertSame(1, CatalogImport::query()->where('status', 'replaced')->count());
        $this->assertSame(2, AuditLog::query()->whereIn('action', ['catalog.import.committed', 'catalog.import.committed_with_issues'])->count());
    }

    public function test_concurrent_catalog_imports_both_rollback_when_the_shared_lot_has_an_active_reservation(): void
    {
        $this->createCatalogWarehouses();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $principal = Warehouse::query()->where('name', 'ALMACEN PRINCIPAL')->firstOrFail();
        $product = Product::create([
            'product_code' => 'MYSQL-CATALOG-RESERVED',
            'name' => 'Original protected item',
            'classification' => 'consumible',
            'expiry_required' => true,
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'MYSQL-RESERVED-LOT',
            'serial' => 'MYSQL-RESERVED-SERIAL',
            'expiry' => '2030-12-31',
            'warehouse_id' => $principal->id,
            'quantity' => 9,
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ]);
        $institution = Institution::create(['name' => 'MySQL reserved import '.Str::uuid(), 'active' => true]);
        $surgeryType = SurgeryType::create(['code' => 'mysql-res-'.Str::lower(Str::random(8)), 'name' => 'MySQL reservation guard', 'active' => true]);
        $patient = Patient::create(['code' => 'MYSQL-'.Str::upper(Str::random(10)), 'full_name' => 'Concurrency patient']);
        $case = $this->caseFor($institution, $surgeryType, $patient, $userA);
        Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 2,
            'status' => 'active',
            'reserved_by' => $userA->id,
        ]);
        $imports = [
            $this->catalogImport($userA, 5, 'MYSQL-CATALOG-RESERVED', 'MYSQL-RESERVED-LOT', 'MYSQL-RESERVED-SERIAL'),
            $this->catalogImport($userB, 7, 'MYSQL-CATALOG-RESERVED', 'MYSQL-RESERVED-LOT', 'MYSQL-RESERVED-SERIAL'),
        ];

        $results = $this->runConcurrently([
            ['catalog_commit', $userA->id, ['import_id' => $imports[0]->id]],
            ['catalog_commit', $userB->id, ['import_id' => $imports[1]->id]],
        ]);

        $this->assertSame(['rejected', 'rejected'], array_column($results, 'result'));
        $this->assertSame(9, (int) $lot->refresh()->quantity);
        $this->assertSame('Original protected item', $product->refresh()->name);
        $this->assertSame(2, CatalogImport::query()->where('status', 'validated')->count());
        $this->assertSame(0, AuditLog::query()->whereIn('action', ['catalog.import.committed', 'catalog.import.committed_with_issues'])->count());
        $this->assertDatabaseHas('reservations', ['id' => Reservation::query()->value('id'), 'status' => 'active']);
    }

    public function test_two_mysql_assignments_cannot_confirm_the_same_instrumentist_at_overlapping_times(): void
    {
        $creatorA = User::factory()->create();
        $creatorB = User::factory()->create();
        $instrumentist = User::factory()->create(['active' => true]);
        $instrumentist->assignRole(Role::findOrCreate('Instrumentista'));
        $institution = Institution::create(['name' => 'MySQL schedule '.Str::uuid(), 'active' => true]);
        $surgeryType = SurgeryType::create(['code' => 'mysql-sch-'.Str::lower(Str::random(8)), 'name' => 'MySQL schedule test', 'active' => true]);
        $patient = Patient::create(['code' => 'MYSQL-'.Str::upper(Str::random(10)), 'full_name' => 'Concurrency patient']);
        $firstCase = $this->caseFor($institution, $surgeryType, $patient, $creatorA);
        $secondCase = $this->caseFor($institution, $surgeryType, $patient, $creatorB);
        $scheduledAt = now()->addHours(4)->startOfMinute();
        $firstCase->update(['scheduled_at' => $scheduledAt]);
        $secondCase->update(['scheduled_at' => $scheduledAt]);

        $results = $this->runConcurrently([
            ['assign_instrumentist', $creatorA->id, ['case_id' => $firstCase->id, 'instrumentist_id' => $instrumentist->id]],
            ['assign_instrumentist', $creatorB->id, ['case_id' => $secondCase->id, 'instrumentist_id' => $instrumentist->id]],
        ]);

        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['result'] === 'completed')));
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['result'] === 'rejected')));
        $this->assertSame(1, SurgeryCase::query()->where('assigned_instrumentist_id', $instrumentist->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'schedule.instrumentist_assigned')->count());
    }

    public function test_two_mysql_assignments_cannot_confirm_the_same_reusable_resource_at_overlapping_times(): void
    {
        $creatorA = User::factory()->create();
        $creatorB = User::factory()->create();
        $institution = Institution::create(['name' => 'MySQL resource '.Str::uuid(), 'active' => true]);
        $surgeryType = SurgeryType::create(['code' => 'mysql-res-'.Str::lower(Str::random(8)), 'name' => 'MySQL resource test', 'active' => true]);
        $patient = Patient::create(['code' => 'MYSQL-'.Str::upper(Str::random(10)), 'full_name' => 'Concurrency patient']);
        $firstCase = $this->caseFor($institution, $surgeryType, $patient, $creatorA);
        $secondCase = $this->caseFor($institution, $surgeryType, $patient, $creatorB);
        $scheduledAt = now()->addHours(4)->startOfMinute();
        $firstCase->update(['scheduled_at' => $scheduledAt]);
        $secondCase->update(['scheduled_at' => $scheduledAt]);
        $warehouse = Warehouse::create([
            'name' => 'MySQL reusable '.Str::uuid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'MYSQL-RESOURCE-'.Str::upper(Str::random(8)),
            'name' => 'Reusable motor',
            'classification' => 'reusable',
            'expiry_required' => false,
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'MYSQL-MOTOR-'.Str::upper(Str::random(8)),
            'warehouse_id' => $warehouse->id,
            'quantity' => 1,
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ]);

        $results = $this->runConcurrently([
            ['assign_resource', $creatorA->id, ['case_id' => $firstCase->id, 'lot_id' => $lot->id]],
            ['assign_resource', $creatorB->id, ['case_id' => $secondCase->id, 'lot_id' => $lot->id]],
        ]);

        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['result'] === 'completed')));
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['result'] === 'rejected')));
        $this->assertSame(1, CaseResourceAssignment::query()->where('inventory_lot_id', $lot->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'schedule.resource_assigned')->count());
    }

    public function test_two_mysql_adjustments_are_serialized_and_both_are_audited(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $warehouse = Warehouse::create([
            'name' => 'MySQL adjustment '.Str::uuid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'MYSQL-ADJUST-'.Str::upper(Str::random(8)),
            'name' => 'Adjustment test product',
            'classification' => 'consumible',
            'expiry_required' => false,
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'MYSQL-ADJUST-'.Str::upper(Str::random(8)),
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ]);

        $results = $this->runConcurrently([
            ['inventory_adjustment', $userA->id, ['lot_id' => $lot->id, 'quantity_adjustment' => 2]],
            ['inventory_adjustment', $userB->id, ['lot_id' => $lot->id, 'quantity_adjustment' => -1]],
        ]);

        $this->assertSame(['completed', 'completed'], array_column($results, 'result'));
        $this->assertSame(11, (int) $lot->refresh()->quantity);
        $this->assertSame(2, InventoryAdjustment::query()->where('inventory_lot_id', $lot->id)->count());
        $this->assertSame(2, AuditLog::query()->where('action', 'inventory.adjustment.created')->count());
    }

    private function createCatalogWarehouses(): void
    {
        foreach ([
            ['ALMACEN PRINCIPAL', 'principal', true],
            ['ALMACEN CONSIGNACION', 'consignacion', true],
            ['ALMACEN DESVALORIZADO', 'desvalorizado', false],
            ['ALMACEN YSAN', 'ysan', false],
        ] as [$name, $type, $immediate]) {
            Warehouse::create([
                'name' => $name,
                'type' => $type,
                'lead_time_hours' => $type === 'ysan' ? 48 : 0,
                'counts_as_immediate' => $immediate,
                'active' => true,
            ]);
        }
    }

    private function catalogImport(User $user, int $quantity, string $code = 'MYSQL-CATALOG-SHARED', string $lot = 'MYSQL-SHARED-LOT', string $serial = 'MYSQL-SHARED-SERIAL'): CatalogImport
    {
        $import = CatalogImport::create([
            'original_filename' => 'mysql-concurrency.xlsx',
            'disk' => 'private',
            'stored_path' => 'integration/mysql-concurrency.xlsx',
            'status' => 'validated',
            'uploaded_by' => $user->id,
            'total_rows' => 1,
            'products_detected' => 1,
            'lots_detected' => 1,
            'critical_errors' => 0,
            'warnings' => 0,
            'inconsistencies' => 0,
        ]);
        CatalogImportRow::create([
            'catalog_import_id' => $import->id,
            'row_number' => 2,
            'raw_data' => [],
            'product_line' => 'MR8',
            'family' => 'Fresas',
            'subfamily' => 'Concurrency',
            'product_code' => $code,
            'product_name' => 'Concurrent product '.$quantity,
            'classification' => 'consumible',
            'expiry_required' => true,
            'expiry_is_na' => false,
            'lot' => $lot,
            'serial' => $serial,
            'detail_expiry' => '2030-12-31',
            'location' => 'Principal',
            'warehouse_quantities' => ['principal' => $quantity, 'consignacion' => 0, 'desvalorizado' => 0, 'ysan' => 0],
            'total_quantity' => $quantity,
            'status' => 'valid',
        ]);

        return $import;
    }

    /** @param array<int, array{0: string, 1: int, 2: array<string, mixed>}> $jobs
     * @return list<array<string, mixed>>
     */
    private function runConcurrently(array $jobs): array
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ops-biomed-'.Str::uuid();
        mkdir($directory, 0700, true);
        $startPath = $directory.DIRECTORY_SEPARATOR.'start';
        $workers = [];

        try {
            foreach ($jobs as $index => [$operation, $userId, $payload]) {
                $readyPath = $directory.DIRECTORY_SEPARATOR.'ready-'.$index;
                $resultPath = $directory.DIRECTORY_SEPARATOR.'result-'.$index.'.json';
                $encodedPayload = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/mysql_concurrency_worker.php'),
                    $operation,
                    (string) $userId,
                    $encodedPayload,
                    $readyPath,
                    $startPath,
                    $resultPath,
                ], base_path(), $this->mysqlWorkerEnvironment());
                $process->setTimeout(45);
                $process->start();
                $workers[] = ['process' => $process, 'ready' => $readyPath, 'result' => $resultPath];
            }

            $deadline = microtime(true) + 20;
            while (microtime(true) < $deadline
                && collect($workers)->contains(fn (array $worker): bool => ! is_file($worker['ready']))) {
                usleep(20000);
            }

            $readyCount = count(array_filter($workers, fn (array $worker): bool => is_file($worker['ready'])));
            $workerDiagnostics = array_map(fn (array $worker): array => [
                'exit_code' => $worker['process']->getExitCode(),
                'stdout' => $worker['process']->getOutput(),
                'stderr' => $worker['process']->getErrorOutput(),
            ], array_filter($workers, fn (array $worker): bool => ! is_file($worker['ready'])));
            $this->assertSame(count($jobs), $readyCount, 'Concurrency workers did not reach the start barrier: '.json_encode($workerDiagnostics));
            file_put_contents($startPath, 'go', LOCK_EX);

            foreach ($workers as $worker) {
                $worker['process']->wait();
                $this->assertSame(0, $worker['process']->getExitCode(), $worker['process']->getErrorOutput());
                $this->assertFileExists($worker['result']);
            }

            return array_map(
                fn (array $worker): array => json_decode((string) file_get_contents($worker['result']), true, flags: JSON_THROW_ON_ERROR),
                $workers,
            );
        } finally {
            foreach ($workers as $worker) {
                if ($worker['process']->isRunning()) {
                    $worker['process']->stop(1);
                }
            }

            foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }

    private function caseFor(Institution $institution, SurgeryType $surgeryType, Patient $patient, User $user): SurgeryCase
    {
        return SurgeryCase::create([
            'case_code' => 'MYSQL-'.Str::upper(Str::random(12)),
            'status' => CaseStatus::SolicitudRegistrada,
            'institution_id' => $institution->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => now()->addDays(2),
            'priority' => 'normal',
            'procedure_name' => 'MySQL concurrency test',
            'request_origin' => 'otro',
            'created_by' => $user->id,
        ]);
    }

    /** @return array<string, string> */
    private function mysqlWorkerEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'BCRYPT_ROUNDS' => '4',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => 'ops_biomed_staging_local',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'DB_URL' => '',
            'OPS_MYSQL_INTEGRATION_TESTS' => '1',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];
    }
}
