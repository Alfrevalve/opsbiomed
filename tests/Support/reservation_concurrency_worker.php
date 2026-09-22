<?php

use App\Models\SurgeryCase;
use App\Services\Inventory\ReservationService;
use Illuminate\Contracts\Console\Kernel;

$basePath = dirname(__DIR__, 2);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $userId, $caseId, $lotId, $quantity, $idempotencyKey, $readyPath, $startPath, $resultPath] = $argv;

file_put_contents($readyPath, 'ready', LOCK_EX);
$deadline = microtime(true) + 15;

while (! is_file($startPath) && microtime(true) < $deadline) {
    usleep(10000);
}

try {
    if (! is_file($startPath)) {
        throw new RuntimeException('Concurrency test start barrier timed out.');
    }

    auth()->loginUsingId((int) $userId);
    $reservation = app(ReservationService::class)->reserveLot(
        SurgeryCase::query()->findOrFail((int) $caseId),
        (int) $lotId,
        (int) $quantity,
        $idempotencyKey,
    );

    $result = ['result' => 'reserved', 'reservation_id' => $reservation->id];
} catch (Throwable $exception) {
    $result = ['result' => 'rejected', 'exception' => $exception::class];
}

file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR), LOCK_EX);
