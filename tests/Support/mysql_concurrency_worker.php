<?php

use App\Models\CatalogImport;
use App\Models\InventoryLot;
use App\Models\SurgeryCase;
use App\Models\User;
use App\Services\Catalog\CatalogImportService;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\Inventory\ReservationService;
use App\Services\Operations\SurgeryScheduleService;
use Illuminate\Contracts\Console\Kernel;

$basePath = dirname(__DIR__, 2);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $operation, $userId, $payloadEncoded, $readyPath, $startPath, $resultPath] = $argv;
$payload = json_decode(base64_decode($payloadEncoded, true) ?: '', true, flags: JSON_THROW_ON_ERROR);

file_put_contents($readyPath, 'ready', LOCK_EX);
$deadline = microtime(true) + 20;

while (! is_file($startPath) && microtime(true) < $deadline) {
    usleep(10000);
}

try {
    if (! is_file($startPath)) {
        throw new RuntimeException('Concurrency worker start barrier timed out.');
    }

    $user = User::query()->findOrFail((int) $userId);
    auth()->login($user);

    match ($operation) {
        'catalog_commit' => app(CatalogImportService::class)->commit(CatalogImport::query()->findOrFail((int) $payload['import_id'])),
        'assign_instrumentist' => app(SurgeryScheduleService::class)->assignInstrumentist(
            SurgeryCase::query()->findOrFail((int) $payload['case_id']),
            (int) $payload['instrumentist_id'],
            $user,
        ),
        'assign_resource' => app(SurgeryScheduleService::class)->assignResource(
            SurgeryCase::query()->findOrFail((int) $payload['case_id']),
            ['resource_type' => 'motor', 'inventory_lot_id' => (int) $payload['lot_id']],
            $user,
        ),
        'inventory_adjustment' => app(InventoryAdjustmentService::class)->create(
            InventoryLot::query()->findOrFail((int) $payload['lot_id']),
            [
                'adjustment_type' => 'conteo_fisico',
                'quantity_adjustment' => (int) $payload['quantity_adjustment'],
                'reason' => 'MySQL concurrency adjustment.',
                'adjusted_at' => now(),
            ],
            $user->id,
        ),
        'reserve' => app(ReservationService::class)->reserveLot(
            SurgeryCase::query()->findOrFail((int) $payload['case_id']),
            (int) $payload['lot_id'],
            (int) $payload['quantity'],
            $payload['idempotency_key'],
        ),
        default => throw new RuntimeException('Unsupported concurrency operation.'),
    };

    $result = ['result' => 'completed'];
} catch (Throwable $exception) {
    $result = [
        'result' => 'rejected',
        'exception' => $exception::class,
        'message' => mb_substr($exception->getMessage(), 0, 500),
    ];
}

file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR), LOCK_EX);
