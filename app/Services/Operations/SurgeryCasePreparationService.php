<?php

namespace App\Services\Operations;

use App\Enums\CaseStatus;
use App\Models\CaseMaterialSent;
use App\Models\CasePreparation;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Traceability\TraceCodeService;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SurgeryCasePreparationService
{
    private const PREPARATION_STATUSES = [
        CaseStatus::Reservado,
        CaseStatus::Reservada,
        CaseStatus::Preparacion,
        CaseStatus::PreoperatorioConfirmado,
        CaseStatus::Internado,
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TraceCodeService $traceCodeService,
    ) {}

    /**
     * @return array{
     *     required: bool,
     *     preparation: ?CasePreparation,
     *     checklist_complete: bool,
     *     delivery_evidence_complete: bool,
     *     dispatch_complete: bool,
     *     ready_for_room: bool,
     *     rows: Collection<int, array{reservation: Reservation, material_sent: ?CaseMaterialSent, dispatched: bool}>
     * }
     */
    public function summary(SurgeryCase $surgeryCase): array
    {
        $surgeryCase->loadMissing([
            'preparation',
            'reservations.inventoryLot.product',
            'reservations.inventoryLot.warehouse',
            'materialsSent.reservation',
            'materialsSent.inventoryLot.product',
            'materialsSent.sentBy',
        ]);

        $preparation = $surgeryCase->preparation;
        $activeReservations = $surgeryCase->reservations
            ->where('status', 'active')
            ->values();
        $materialsSentByReservation = $surgeryCase->materialsSent
            ->filter(fn (CaseMaterialSent $materialSent): bool => $materialSent->reservation_id !== null)
            ->keyBy('reservation_id');
        $rows = $activeReservations->map(function (Reservation $reservation) use ($materialsSentByReservation): array {
            /** @var ?CaseMaterialSent $materialSent */
            $materialSent = $materialsSentByReservation->get($reservation->id);
            $dispatched = $materialSent !== null
                && (int) $materialSent->quantity === (int) $reservation->quantity
                && $materialSent->sent_by !== null
                && $materialSent->sent_at !== null;

            return [
                'reservation' => $reservation,
                'material_sent' => $materialSent,
                'dispatched' => $dispatched,
            ];
        })->values();
        $checklistComplete = $preparation !== null
            && $preparation->institution_confirmed
            && $preparation->doctor_confirmed
            && $preparation->schedule_confirmed
            && $preparation->material_confirmed
            && $preparation->documents_confirmed
            && $preparation->prepared_by !== null
            && $preparation->prepared_at !== null;
        $deliveryEvidenceComplete = $preparation !== null
            && (filled($preparation->guide_number) || filled($preparation->delivery_evidence_reference));
        $dispatchComplete = $rows->isNotEmpty()
            && $rows->every(fn (array $row): bool => $row['dispatched']);

        return [
            'required' => in_array($surgeryCase->status, self::PREPARATION_STATUSES, true),
            'preparation' => $preparation,
            'checklist_complete' => $checklistComplete,
            'delivery_evidence_complete' => $deliveryEvidenceComplete,
            'dispatch_complete' => $dispatchComplete,
            'ready_for_room' => $checklistComplete && $deliveryEvidenceComplete && $dispatchComplete,
            'rows' => $rows,
        ];
    }

    public function isReadyForRoom(SurgeryCase $surgeryCase): bool
    {
        return $this->summary($surgeryCase)['ready_for_room'];
    }

    /**
     * @param  array{
     *     institution_confirmed: bool,
     *     doctor_confirmed: bool,
     *     schedule_confirmed: bool,
     *     material_confirmed: bool,
     *     documents_confirmed: bool,
     *     guide_number?: ?string,
     *     delivery_evidence_reference?: ?string,
     *     notes?: ?string,
     *     materials: array<int, array{reservation_id: int, verified: bool, quantity: int}>
     * }  $data
     */
    public function prepareAndDispatch(SurgeryCase $surgeryCase, array $data, User $user): CasePreparation
    {
        return DB::transaction(function () use ($surgeryCase, $data, $user): CasePreparation {
            $case = SurgeryCase::query()
                ->lockForUpdate()
                ->findOrFail($surgeryCase->id);

            if (! in_array($case->status, self::PREPARATION_STATUSES, true)) {
                throw new DomainException('El caso no se encuentra en una etapa que permita preparar y despachar material.');
            }

            $reservations = Reservation::query()
                ->where('case_id', $case->id)
                ->where('status', 'active')
                ->with(['inventoryLot.product', 'inventoryLot.warehouse'])
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                throw new DomainException('No hay reservas activas para preparar el despacho de esta cirugia.');
            }

            $materials = collect($data['materials']);
            $materialsByReservation = $materials->keyBy(
                fn (array $material): int => (int) $material['reservation_id'],
            );

            if ($materials->count() !== $materialsByReservation->count()
                || $materialsByReservation->count() !== $reservations->count()) {
                throw new DomainException('El despacho debe incluir exactamente una fila por cada reserva activa.');
            }

            foreach ($reservations as $reservation) {
                /** @var ?array{reservation_id: int, verified: bool, quantity: int} $material */
                $material = $materialsByReservation->get($reservation->id);

                if ($material === null) {
                    throw new DomainException('Falta confirmar el despacho de una reserva activa.');
                }

                if (! $material['verified']) {
                    throw new DomainException('Confirme la verificacion fisica de cada material antes de despachar.');
                }

                if ((int) $material['quantity'] !== (int) $reservation->quantity) {
                    throw new DomainException('La cantidad fisica despachada debe coincidir exactamente con la cantidad reservada.');
                }
            }

            if (blank($data['guide_number'] ?? null) && blank($data['delivery_evidence_reference'] ?? null)) {
                throw new DomainException('Registre el numero de guia o una referencia de evidencia de despacho.');
            }

            $preparation = CasePreparation::query()
                ->where('case_id', $case->id)
                ->lockForUpdate()
                ->first();
            $before = $preparation?->only([
                'institution_confirmed',
                'doctor_confirmed',
                'schedule_confirmed',
                'material_confirmed',
                'documents_confirmed',
                'guide_number',
                'delivery_evidence_reference',
                'notes',
            ]) ?? [];
            $preparationData = [
                'institution_confirmed' => $data['institution_confirmed'],
                'doctor_confirmed' => $data['doctor_confirmed'],
                'schedule_confirmed' => $data['schedule_confirmed'],
                'material_confirmed' => $data['material_confirmed'],
                'documents_confirmed' => $data['documents_confirmed'],
                'guide_number' => $data['guide_number'] ?? null,
                'delivery_evidence_reference' => $data['delivery_evidence_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'prepared_by' => $user->id,
                'prepared_at' => now(),
                'dispatched_by' => $user->id,
                'dispatched_at' => now(),
            ];

            if ($preparation === null) {
                $preparation = CasePreparation::create([
                    'case_id' => $case->id,
                    ...$preparationData,
                ]);
            } else {
                $preparation->update($preparationData);
            }

            foreach ($reservations as $reservation) {
                $materialSent = CaseMaterialSent::query()
                    ->where('case_id', $case->id)
                    ->where('reservation_id', $reservation->id)
                    ->lockForUpdate()
                    ->first();
                $materialBefore = $materialSent?->only(['quantity', 'guide_number', 'sent_at', 'sent_by']) ?? [];
                $materialData = [
                    'inventory_lot_id' => $reservation->inventory_lot_id,
                    'quantity' => $reservation->quantity,
                    'guide_number' => $data['guide_number'] ?? null,
                    'sent_at' => now(),
                    'sent_by' => $user->id,
                ];

                if ($materialSent === null) {
                    $materialSent = CaseMaterialSent::create([
                        'case_id' => $case->id,
                        'reservation_id' => $reservation->id,
                        ...$materialData,
                    ]);
                } else {
                    $materialSent->update($materialData);
                }

                $this->auditLogger->record('case.material.dispatched', $materialSent, $materialBefore, [
                    'case_id' => $case->id,
                    'reservation_id' => $reservation->id,
                    'inventory_lot_id' => $reservation->inventory_lot_id,
                    'quantity' => $reservation->quantity,
                    'guide_number' => $data['guide_number'] ?? null,
                    'sent_by' => $user->id,
                ]);
                $this->traceCodeService->record('dispatched', $reservation, $case->id, [
                    'inventory_lot_id' => $reservation->inventory_lot_id,
                    'quantity' => $reservation->quantity,
                    'guide_number' => $data['guide_number'] ?? null,
                ]);
            }

            $this->auditLogger->record('case.preparation.completed', $preparation, $before, [
                'case_id' => $case->id,
                'active_reservations' => $reservations->count(),
                'guide_number' => $preparation->guide_number,
                'delivery_evidence_reference' => $preparation->delivery_evidence_reference,
                'prepared_by' => $user->id,
                'dispatched_by' => $user->id,
            ]);

            return $preparation->refresh();
        });
    }

    /** @return Collection<int, SurgeryCase> */
    public function upcomingPendingCases(CarbonInterface $from, CarbonInterface $until): Collection
    {
        return SurgeryCase::query()
            ->with(['preparation', 'reservations', 'materialsSent'])
            ->whereIn('status', array_map(
                fn (CaseStatus $status): string => $status->value,
                self::PREPARATION_STATUSES,
            ))
            ->whereBetween('scheduled_at', [$from, $until])
            ->orderBy('scheduled_at')
            ->get()
            ->filter(fn (SurgeryCase $case): bool => ! $this->isReadyForRoom($case))
            ->values();
    }
}
