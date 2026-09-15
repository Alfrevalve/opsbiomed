<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\AuditLog;
use App\Models\CaseReturn;
use App\Models\Doctor;
use App\Models\Failure;
use App\Models\Institution;
use App\Models\InventoryLot;
use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TraceabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_trace_codes_are_generated_uniquely_and_backfilled_idempotently(): void
    {
        $firstLot = $this->inventoryLot();
        $secondLot = $this->inventoryLot();
        $creator = $this->userWithPermissions([]);
        $case = $this->surgeryCase($creator);
        $reservation = Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $firstLot->id,
            'quantity' => 1,
            'status' => 'active',
            'reserved_by' => $creator->id,
        ]);
        $caseReturn = CaseReturn::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $firstLot->id,
            'returned_qty' => 1,
            'condition' => 'pendiente_inspeccion',
        ]);
        $failure = Failure::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $firstLot->id,
            'product_id' => $firstLot->product_id,
            'failure_type' => 'otro',
            'occurrence_moment' => 'inventario',
            'severity' => 'media',
            'status' => 'reportada',
            'description' => 'Falla de prueba para trazabilidad.',
            'reported_by' => $creator->id,
        ]);

        $this->assertMatchesRegularExpression('/^LOT-'.$firstLot->id.'-MR8-TRACE-/', (string) $firstLot->trace_code);
        $this->assertMatchesRegularExpression('/^CASE-'.$case->id.'-MR8-TRACE-/', (string) $case->trace_code);
        $this->assertSame('RES-'.$reservation->id, $reservation->trace_code);
        $this->assertSame('RET-'.$caseReturn->id, $caseReturn->trace_code);
        $this->assertSame('FAIL-'.$failure->id, $failure->trace_code);
        $this->assertCount(6, array_unique([
            $firstLot->trace_code,
            $secondLot->trace_code,
            $case->trace_code,
            $reservation->trace_code,
            $caseReturn->trace_code,
            $failure->trace_code,
        ]));

        $firstLot->forceFill(['trace_code' => null])->saveQuietly();
        Artisan::call('trace:backfill');
        $firstLot->refresh();

        $this->assertNotNull($firstLot->trace_code);
        $backfilledCode = $firstLot->trace_code;
        Artisan::call('trace:backfill');
        $this->assertSame($backfilledCode, $firstLot->fresh()->trace_code);
    }

    public function test_user_with_trace_scan_permission_can_scan_a_lot_and_the_scan_is_audited(): void
    {
        $user = $this->userWithPermissions(['trace.view', 'trace.scan', 'inventory.view']);
        $lot = $this->inventoryLot();

        $this->actingAs($user)
            ->post(route('trace.scan'), ['trace_code' => $lot->trace_code])
            ->assertOk()
            ->assertSee('Resultado de escaneo')
            ->assertSee($lot->product->product_code)
            ->assertSee($lot->lot)
            ->assertSee($lot->warehouse->name);

        $this->assertDatabaseHas('trace_events', [
            'trace_code' => $lot->trace_code,
            'traceable_type' => InventoryLot::class,
            'traceable_id' => $lot->id,
            'event_type' => 'scanned',
            'user_id' => $user->id,
        ]);
        $this->assertTrue(AuditLog::query()
            ->where('action', 'trace.scanned')
            ->where('auditable_type', InventoryLot::class)
            ->where('auditable_id', $lot->id)
            ->exists());
    }

    public function test_user_without_trace_permissions_cannot_access_or_scan(): void
    {
        $user = User::factory()->create();
        $lot = $this->inventoryLot();

        $this->actingAs($user)
            ->get(route('trace.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('trace.scan'), ['trace_code' => $lot->trace_code])
            ->assertForbidden();
    }

    public function test_scanning_a_case_returns_its_operational_control_link(): void
    {
        $user = $this->userWithPermissions(['trace.view', 'trace.scan', 'cases.view']);
        $case = $this->surgeryCase($user);

        $this->actingAs($user)
            ->post(route('trace.scan'), ['trace_code' => $case->trace_code])
            ->assertOk()
            ->assertSee($case->case_code)
            ->assertSee('Caso quirurgico')
            ->assertSee('href="'.route('cases.control', $case).'"', false);
    }

    public function test_unknown_trace_code_shows_a_clear_error_and_records_the_attempt(): void
    {
        $user = $this->userWithPermissions(['trace.view', 'trace.scan']);

        $this->actingAs($user)
            ->post(route('trace.scan'), ['trace_code' => 'LOT-999999-DESCONOCIDO'])
            ->assertOk()
            ->assertSee('Codigo no encontrado')
            ->assertSee('No se encontro un registro');

        $this->assertDatabaseHas('trace_events', [
            'trace_code' => 'LOT-999999-DESCONOCIDO',
            'event_type' => 'scanned',
            'traceable_type' => null,
            'traceable_id' => null,
            'user_id' => $user->id,
        ]);
    }

    public function test_printable_lot_and_case_labels_load_and_are_audited(): void
    {
        $user = $this->userWithPermissions(['trace.view', 'trace.print', 'inventory.view', 'cases.view']);
        $lot = $this->inventoryLot();
        $case = $this->surgeryCase($user);

        $this->actingAs($user)
            ->get(route('trace.labels.lots', ['lots' => $lot->id]))
            ->assertOk()
            ->assertSee('Etiquetas de lotes')
            ->assertSee($lot->trace_code);
        $this->actingAs($user)
            ->get(route('trace.labels.cases', $case))
            ->assertOk()
            ->assertSee($case->case_code)
            ->assertSee($case->trace_code);

        $this->assertDatabaseHas('trace_events', [
            'trace_code' => $lot->trace_code,
            'event_type' => 'label_printed',
        ]);
        $this->assertTrue(AuditLog::query()->where('action', 'trace.label_printed')->exists());
    }

    public function test_scan_does_not_expose_prices_without_billing_permission(): void
    {
        $user = $this->userWithPermissions(['trace.view', 'trace.scan', 'inventory.view']);
        $lot = $this->inventoryLot();
        ProductPrice::create([
            'product_id' => $lot->product_id,
            'unit_price' => 999.99,
            'minimum_price' => 800,
            'currency' => 'PEN',
            'price_type' => 'lista',
            'active' => true,
            'valid_from' => today(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('trace.scan'), ['trace_code' => $lot->trace_code])
            ->assertOk()
            ->assertDontSee('999.99')
            ->assertDontSee('Precio vigente');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::findOrCreate('Trazabilidad QA');
        $role->syncPermissions(
            collect($permissions)->map(
                fn (string $permission): Permission => Permission::findOrCreate($permission),
            ),
        );
        $user = User::factory()->create(['active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function inventoryLot(): InventoryLot
    {
        $product = Product::create([
            'product_code' => 'MR8-TRACE-'.uniqid(),
            'name' => 'Fresa trazable MR8',
            'classification' => 'consumable',
            'expiry_required' => true,
            'active' => true,
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Almacen trazabilidad '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);

        return InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'LOT-TRACE-'.uniqid(),
            'serial' => 'SER-TRACE-'.uniqid(),
            'expiry' => today()->addYear(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'status' => 'apto',
            'eligible_flag' => true,
        ])->load(['product', 'warehouse']);
    }

    private function surgeryCase(User $creator): SurgeryCase
    {
        $institution = Institution::create([
            'name' => 'Clinica trazabilidad '.uniqid(),
            'debt_status' => 'al_dia',
            'active' => true,
        ]);
        $doctor = Doctor::create([
            'name' => 'Dr. Trazabilidad '.uniqid(),
            'institution_id' => $institution->id,
            'active' => true,
        ]);
        $patient = Patient::create([
            'code' => 'TRACE-'.uniqid(),
            'full_name' => 'Paciente Trazabilidad',
        ]);
        $surgeryType = SurgeryType::create([
            'code' => 'TRACE-'.uniqid(),
            'name' => 'Cirugia trazable',
            'active' => true,
        ]);

        return SurgeryCase::create([
            'case_code' => 'MR8-TRACE-'.uniqid(),
            'status' => CaseStatus::Programada,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Cirugia para validar trazabilidad',
            'request_origin' => 'otro',
            'created_by' => $creator->id,
        ]);
    }
}
