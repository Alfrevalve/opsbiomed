<?php

namespace App\Enums;

enum CaseStatus: string
{
    case SolicitudRegistrada = 'solicitud_registrada';
    case Solicitado = 'solicitado';
    case Programada = 'programada';
    case ValidacionComercial = 'validacion_comercial';
    case Reservado = 'reservado';
    case Reservada = 'reservada';
    case Preparacion = 'preparacion';
    case Internado = 'internado';
    case PreoperatorioConfirmado = 'preoperatorio_confirmado';
    case EnCirugia = 'en_cirugia';
    case EnSala = 'en_sala';
    case PendienteCierre = 'pendiente_cierre';
    case Conciliacion = 'conciliacion';
    case Facturacion = 'facturacion';
    case Cerrado = 'cerrado';
    case Cerrada = 'cerrada';
    case Facturada = 'facturada';
    case Observado = 'observado';
    case Cancelado = 'cancelado';
    case Reprogramado = 'reprogramado';

    public function label(): string
    {
        return match ($this) {
            self::SolicitudRegistrada => 'Solicitud registrada',
            self::Solicitado => 'Solicitado',
            self::Programada => 'Programada',
            self::ValidacionComercial => 'Validacion comercial',
            self::Reservado => 'Reservado',
            self::Reservada => 'Reservada',
            self::Preparacion => 'Preparacion',
            self::Internado => 'Internado',
            self::PreoperatorioConfirmado => 'Preoperatorio confirmado',
            self::EnCirugia => 'En cirugia',
            self::EnSala => 'En sala',
            self::PendienteCierre => 'Pendiente de cierre',
            self::Conciliacion => 'Conciliacion',
            self::Facturacion => 'Facturacion',
            self::Cerrado => 'Cerrado',
            self::Cerrada => 'Cerrada',
            self::Facturada => 'Facturada',
            self::Observado => 'Observado',
            self::Cancelado => 'Cancelado',
            self::Reprogramado => 'Reprogramado',
        };
    }

    /**
     * Return the consolidated label used by the operational workflow.
     *
     * Legacy values remain intact because they are already used by reservations,
     * closure, billing, reports, and historical data.
     */
    public function operationalLabel(): string
    {
        return match ($this) {
            self::SolicitudRegistrada, self::Solicitado => 'Solicitud registrada',
            self::Programada, self::ValidacionComercial, self::Reprogramado, self::Observado => 'Programada',
            self::Reservado, self::Reservada => 'Reservada',
            self::Preparacion, self::PreoperatorioConfirmado => 'Preparada',
            self::Internado => 'Internada',
            self::EnSala => 'En sala',
            self::EnCirugia => 'En cirugia',
            self::PendienteCierre => 'Cx finalizada',
            self::Cerrado, self::Cerrada => 'Cerrada',
            self::Conciliacion => 'Inspeccionada',
            self::Facturacion => 'Facturacion',
            self::Facturada => 'Cerrada total',
            self::Cancelado => 'Cancelada',
        };
    }

    /**
     * Return the fields that may be changed while the case is in this status.
     * An empty list means normal editing is locked.
     *
     * @return list<string>
     */
    public function editableFields(): array
    {
        return match ($this) {
            self::SolicitudRegistrada, self::Solicitado => [
                'institution_id',
                'doctor_id',
                'patient_name',
                'surgery_type_id',
                'scheduled_at',
                'request_origin',
                'material_requested',
                'notes',
            ],
            self::Programada => [
                'scheduled_at',
                'material_requested',
                'notes',
            ],
            self::Reservado, self::Reservada => ['notes'],
            default => [],
        };
    }

    public function isEditable(): bool
    {
        return $this->editableFields() !== [];
    }

    public function allowsReservation(): bool
    {
        return in_array($this, [
            self::SolicitudRegistrada,
            self::Solicitado,
            self::Programada,
            self::Reservado,
            self::Reservada,
        ], true);
    }
}
