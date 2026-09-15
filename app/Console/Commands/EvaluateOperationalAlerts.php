<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationalAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluateOperationalAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:alerts:evaluate';

    protected $aliases = ['app:evaluate-operational-alerts'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evalúa los SLA operativos y actualiza sus alertas.';

    /**
     * Execute the console command.
     */
    public function handle(OperationalAlertService $service): int
    {
        try {
            $summary = $service->evaluate();
        } catch (Throwable $exception) {
            Log::error('No se pudieron evaluar las alertas operativas.', ['exception' => $exception]);
            $this->error('No se pudieron evaluar las alertas operativas: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'SLA evaluados: %d | Alertas creadas: %d | Vencidas: %d | Notificaciones internas: %d',
            $summary['slas'],
            $summary['created'],
            $summary['expired'],
            $summary['notifications'],
        ));

        return self::SUCCESS;
    }
}
