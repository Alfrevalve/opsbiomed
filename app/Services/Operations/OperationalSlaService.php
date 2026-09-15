<?php

namespace App\Services\Operations;

use App\Models\OperationalSla;

class OperationalSlaService
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function defaultDefinitions(): array
    {
        return [
            [
                'code' => 'request_without_reservation',
                'name' => 'Solicitud sin reserva',
                'module' => 'Solicitudes',
                'trigger_event' => 'case.created',
                'target_minutes' => 1440,
                'time_unit' => 'hours',
                'priority' => 'high',
                'responsible_roles' => ['Jefe de Linea', 'Programador Quirurgico', 'Almacen'],
            ],
            [
                'code' => 'surgery_without_coverage',
                'name' => 'Cirugía próxima sin cobertura',
                'module' => 'Inventario',
                'trigger_event' => 'surgery.scheduled',
                'target_minutes' => 2880,
                'time_unit' => 'hours',
                'priority' => 'critical',
                'responsible_roles' => ['Jefe de Linea', 'Almacen', 'Direccion Tecnica'],
            ],
            [
                'code' => 'return_pending_inspection',
                'name' => 'Devolución pendiente de inspección',
                'module' => 'Devoluciones',
                'trigger_event' => 'return.created',
                'target_minutes' => 1440,
                'time_unit' => 'hours',
                'priority' => 'high',
                'responsible_roles' => ['Almacen', 'Direccion Tecnica'],
            ],
            [
                'code' => 'critical_failure_unreviewed',
                'name' => 'Falla crítica sin revisión',
                'module' => 'Fallas técnicas',
                'trigger_event' => 'failure.reported',
                'target_minutes' => 240,
                'time_unit' => 'hours',
                'priority' => 'critical',
                'responsible_roles' => ['Direccion Tecnica', 'Administrador'],
            ],
            [
                'code' => 'document_pending_validation',
                'name' => 'Documento pendiente de validación',
                'module' => 'Documentos',
                'trigger_event' => 'document.uploaded',
                'target_minutes' => 1440,
                'time_unit' => 'hours',
                'priority' => 'medium',
                'responsible_roles' => ['Jefe de Linea', 'Direccion Tecnica', 'Cobranza'],
            ],
            [
                'code' => 'case_closed_without_billing',
                'name' => 'Caso cerrado sin facturación',
                'module' => 'Facturación',
                'trigger_event' => 'case.closed',
                'target_minutes' => 2880,
                'time_unit' => 'hours',
                'priority' => 'high',
                'responsible_roles' => ['Cobranza', 'Jefe de Linea'],
            ],
            [
                'code' => 'overdue_collection',
                'name' => 'Cobranza vencida',
                'module' => 'Cobranza',
                'trigger_event' => 'billing.due',
                'target_minutes' => 0,
                'time_unit' => 'days',
                'priority' => 'critical',
                'responsible_roles' => ['Cobranza', 'Gerencia'],
            ],
            [
                'code' => 'zero_cost_pending_approval',
                'name' => 'Costo cero pendiente de aprobación',
                'module' => 'Aprobaciones',
                'trigger_event' => 'cost_zero.requested',
                'target_minutes' => 1440,
                'time_unit' => 'hours',
                'priority' => 'high',
                'responsible_roles' => ['Gerencia', 'Jefe de Linea'],
            ],
        ];
    }

    public function ensureDefaults(): int
    {
        $count = 0;

        foreach (self::defaultDefinitions() as $definition) {
            OperationalSla::query()->updateOrCreate(
                ['code' => $definition['code']],
                $definition + [
                    'schedule_rules' => ['timezone' => config('app.timezone')],
                    'metadata' => ['source' => 'OPS BIOMED MR8'],
                    'active' => true,
                ],
            );
            $count++;
        }

        return $count;
    }
}
