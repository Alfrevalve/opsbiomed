<?php

namespace App\Console\Commands;

use App\Models\CaseReturn;
use App\Models\Failure;
use App\Models\InventoryLot;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Services\Traceability\TraceCodeService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class BackfillTraceCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trace:backfill';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera codigos trazables para registros operativos existentes.';

    /**
     * Execute the console command.
     */
    public function handle(TraceCodeService $traceCodeService): int
    {
        $total = 0;

        foreach ([InventoryLot::class, SurgeryCase::class, Reservation::class, CaseReturn::class, Failure::class] as $modelClass) {
            $processed = 0;

            $modelClass::query()
                ->where(function ($query): void {
                    $query->whereNull('trace_code')->orWhere('trace_code', '');
                })
                ->orderBy('id')
                ->chunkById(200, function ($records) use ($traceCodeService, &$processed): void {
                    foreach ($records as $record) {
                        if ($record instanceof Model) {
                            $traceCodeService->ensureFor($record);
                            $processed++;
                        }
                    }
                });

            $total += $processed;
            $this->line(class_basename($modelClass).": {$processed} codigo(s) generado(s).");
        }

        $this->info("Trazabilidad actualizada. Total: {$total} codigo(s).");

        return self::SUCCESS;
    }
}
