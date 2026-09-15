<?php

namespace App\Services\Traceability;

use App\Models\CaseReturn;
use App\Models\Failure;
use App\Models\InventoryLot;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\TraceEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

class TraceCodeService
{
    /** @var array<class-string<Model>, array{label: string, prefix: string}> */
    private const TRACEABLE_TYPES = [
        InventoryLot::class => ['label' => 'Lote de inventario', 'prefix' => 'LOT'],
        SurgeryCase::class => ['label' => 'Caso quirurgico', 'prefix' => 'CASE'],
        Reservation::class => ['label' => 'Reserva', 'prefix' => 'RES'],
        CaseReturn::class => ['label' => 'Devolucion', 'prefix' => 'RET'],
        Failure::class => ['label' => 'Falla tecnica', 'prefix' => 'FAIL'],
    ];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function ensureFor(Model $model): string
    {
        $this->definitionFor($model);
        $currentCode = $this->normalize((string) $model->getAttribute('trace_code'));

        if ($currentCode !== '') {
            return $currentCode;
        }

        $baseCode = $this->baseCodeFor($model);
        $traceCode = $baseCode;
        $suffix = 1;

        while ($this->codeExists($traceCode, $model)) {
            $traceCode = substr($baseCode, 0, 150).'-'.$suffix;
            $suffix++;
        }

        $model->forceFill(['trace_code' => $traceCode])->saveQuietly();

        return $traceCode;
    }

    public function normalize(?string $traceCode): string
    {
        $normalized = Str::upper(trim((string) $traceCode));

        return preg_replace('/\s+/', '', $normalized) ?? '';
    }

    /**
     * @return array{trace_code: string, entity_type: string, title: string, state: string, location: ?string, details: array<string, string>, actions: list<array{label: string, href: string}>}|null
     */
    public function scan(string $traceCode, User $user): ?array
    {
        $normalizedCode = $this->normalize($traceCode);
        $record = $this->findByTraceCode($normalizedCode);

        $this->record(
            eventType: 'scanned',
            traceable: $record,
            surgeryCaseId: $this->surgeryCaseIdFor($record),
            context: [
                'resolved' => $record !== null,
                'entity_type' => $record === null ? null : $this->definitionFor($record)['label'],
            ],
            traceCode: $normalizedCode,
            userId: $user->id,
        );

        if ($record === null) {
            return null;
        }

        return $this->scanResultFor($record, $user);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $eventType,
        ?Model $traceable = null,
        ?int $surgeryCaseId = null,
        array $context = [],
        ?string $notes = null,
        ?string $traceCode = null,
        ?int $userId = null,
    ): TraceEvent {
        $resolvedCode = $traceable === null
            ? $this->normalize($traceCode)
            : $this->ensureFor($traceable);

        if ($resolvedCode === '') {
            throw new RuntimeException('No se puede registrar un evento de trazabilidad sin codigo.');
        }

        $event = TraceEvent::create([
            'trace_code' => $resolvedCode,
            'traceable_type' => $traceable === null ? null : $traceable::class,
            'traceable_id' => $traceable?->getKey(),
            'surgery_case_id' => $surgeryCaseId ?? $this->surgeryCaseIdFor($traceable),
            'user_id' => $userId ?? auth()->id(),
            'event_type' => $eventType,
            'context' => $context,
            'notes' => $notes,
            'created_at' => now(),
        ]);

        if ($eventType === 'scanned') {
            $this->auditLogger->record('trace.scanned', $traceable, [], [
                'trace_code' => $resolvedCode,
                ...$context,
            ]);
        }

        return $event;
    }

    public function recordLabelPrinted(Model $traceable, ?int $surgeryCaseId = null): TraceEvent
    {
        $event = $this->record(
            eventType: 'label_printed',
            traceable: $traceable,
            surgeryCaseId: $surgeryCaseId,
            context: ['format' => 'printable_label'],
        );
        $this->auditLogger->record('trace.label_printed', $traceable, [], [
            'trace_code' => $event->trace_code,
            'surgery_case_id' => $event->surgery_case_id,
        ]);

        return $event;
    }

    /** @return array<class-string<Model>, array{label: string, prefix: string}> */
    public function supportedTypes(): array
    {
        return self::TRACEABLE_TYPES;
    }

    private function baseCodeFor(Model $model): string
    {
        $definition = $this->definitionFor($model);

        $traceCode = match (true) {
            $model instanceof InventoryLot => $definition['prefix'].'-'.$model->getKey().'-'.$this->segment($model->product?->product_code, 'SIN-CODIGO'),
            $model instanceof SurgeryCase => $definition['prefix'].'-'.$model->getKey().'-'.$this->segment($model->case_code, 'SIN-CASO'),
            default => $definition['prefix'].'-'.$model->getKey(),
        };

        return substr($traceCode, 0, 160);
    }

    private function segment(?string $value, string $fallback): string
    {
        $segment = Str::upper(Str::slug((string) $value, '-'));

        return $segment === '' ? $fallback : $segment;
    }

    private function codeExists(string $traceCode, Model $except): bool
    {
        foreach (array_keys(self::TRACEABLE_TYPES) as $modelClass) {
            $query = $modelClass::query()->where('trace_code', $traceCode);

            if ($except instanceof $modelClass) {
                $query->whereKeyNot($except->getKey());
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    private function findByTraceCode(string $traceCode): ?Model
    {
        foreach (array_keys(self::TRACEABLE_TYPES) as $modelClass) {
            $record = $modelClass::query()
                ->with($this->relationsFor($modelClass))
                ->where('trace_code', $traceCode)
                ->first();

            if ($record !== null) {
                return $record;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function relationsFor(string $modelClass): array
    {
        return match ($modelClass) {
            InventoryLot::class => ['product', 'warehouse'],
            SurgeryCase::class => ['institution', 'doctor', 'patient', 'surgeryType'],
            Reservation::class => ['case.institution', 'inventoryLot.product', 'inventoryLot.warehouse'],
            CaseReturn::class => ['case.institution', 'inventoryLot.product', 'inventoryLot.warehouse'],
            Failure::class => ['case.institution', 'inventoryLot.product', 'inventoryLot.warehouse', 'product'],
            default => [],
        };
    }

    /**
     * @return array{trace_code: string, entity_type: string, title: string, state: string, location: ?string, details: array<string, string>, actions: list<array{label: string, href: string}>}
     */
    private function scanResultFor(Model $record, User $user): array
    {
        $definition = $this->definitionFor($record);

        return [
            'trace_code' => $this->ensureFor($record),
            'entity_type' => $definition['label'],
            'title' => $this->titleFor($record),
            'state' => $this->stateFor($record),
            'location' => $this->locationFor($record),
            'details' => $this->detailsFor($record),
            'actions' => $this->actionsFor($record, $user),
        ];
    }

    private function titleFor(Model $record): string
    {
        return match (true) {
            $record instanceof InventoryLot => $record->product?->name ?: 'Lote sin producto',
            $record instanceof SurgeryCase => $record->case_code,
            $record instanceof Reservation => 'Reserva #'.$record->id.' / '.($record->case?->case_code ?: 'Caso no disponible'),
            $record instanceof CaseReturn => 'Devolucion #'.$record->id.' / '.($record->case?->case_code ?: 'Caso no disponible'),
            $record instanceof Failure => 'Falla #'.$record->id.' / '.($record->product?->product_code ?: $record->inventoryLot?->product?->product_code ?: 'Producto no especificado'),
            default => 'Registro trazable',
        };
    }

    private function stateFor(Model $record): string
    {
        $value = match (true) {
            $record instanceof InventoryLot => $record->status,
            $record instanceof SurgeryCase => $record->status,
            $record instanceof Reservation => $record->status,
            $record instanceof CaseReturn => $record->condition,
            $record instanceof Failure => $record->status,
            default => null,
        };

        $state = $value instanceof BackedEnum ? $value->value : (string) $value;

        return Str::headline($state ?: 'sin estado');
    }

    private function locationFor(Model $record): ?string
    {
        return match (true) {
            $record instanceof InventoryLot => $record->warehouse?->name,
            $record instanceof Reservation => $record->inventoryLot?->warehouse?->name,
            $record instanceof CaseReturn => $record->inventoryLot?->warehouse?->name,
            $record instanceof Failure => $record->inventoryLot?->warehouse?->name,
            default => null,
        };
    }

    /** @return array<string, string> */
    private function detailsFor(Model $record): array
    {
        return match (true) {
            $record instanceof InventoryLot => array_filter([
                'Producto' => $record->product?->name,
                'Codigo' => $record->product?->product_code,
                'Lote' => $record->lot ?: 'Sin lote',
                'Serie' => $record->serial ?: 'No aplica',
                'Vencimiento' => $record->expiry?->format('d/m/Y') ?: 'No registrado',
                'Almacen' => $record->warehouse?->name,
                'Ubicacion' => $record->location ?: 'No registrada',
            ]),
            $record instanceof SurgeryCase => array_filter([
                'Institucion' => $record->institution?->name,
                'Medico' => $record->doctor?->name,
                'Paciente' => $this->initials($record->patient?->full_name),
                'Tipo de cirugia' => $record->surgeryType?->name,
                'Programada' => $record->scheduled_at?->format('d/m/Y H:i'),
            ]),
            $record instanceof Reservation => array_filter([
                'Caso' => $record->case?->case_code,
                'Producto' => $record->inventoryLot?->product?->product_code,
                'Lote' => $record->inventoryLot?->lot ?: 'Sin lote',
                'Cantidad reservada' => (string) $record->quantity,
                'Almacen' => $record->inventoryLot?->warehouse?->name,
            ]),
            $record instanceof CaseReturn => array_filter([
                'Caso' => $record->case?->case_code,
                'Producto' => $record->inventoryLot?->product?->product_code,
                'Lote' => $record->inventoryLot?->lot ?: 'Sin lote',
                'Cantidad devuelta' => (string) $record->returned_qty,
                'Resultado de inspeccion' => $record->inspection_result ? Str::headline($record->inspection_result) : 'Pendiente',
            ]),
            $record instanceof Failure => array_filter([
                'Caso' => $record->case?->case_code,
                'Producto' => $record->product?->product_code ?: $record->inventoryLot?->product?->product_code,
                'Lote' => $record->inventoryLot?->lot ?: null,
                'Severidad' => Str::headline($record->severity),
                'Momento' => Str::headline($record->occurrence_moment),
            ]),
            default => [],
        };
    }

    /** @return list<array{label: string, href: string}> */
    private function actionsFor(Model $record, User $user): array
    {
        if ($record instanceof InventoryLot && $user->can('inventory.view')) {
            return [['label' => 'Ver lote', 'href' => route('inventory.show', $record)]];
        }

        if ($record instanceof SurgeryCase && $user->can('cases.view')) {
            return [['label' => 'Abrir control operativo', 'href' => route('cases.control', $record)]];
        }

        if ($record instanceof Reservation && $record->case !== null && $user->can('cases.view')) {
            return [['label' => 'Abrir control del caso', 'href' => route('cases.control', $record->case)]];
        }

        if ($record instanceof CaseReturn && $user->can('returns.view')) {
            return [['label' => 'Ver devolucion', 'href' => route('returns.show', $record)]];
        }

        if ($record instanceof Failure && $user->can('failures.view')) {
            return [['label' => 'Ver falla', 'href' => route('failures.show', $record)]];
        }

        return [];
    }

    private function surgeryCaseIdFor(?Model $record): ?int
    {
        return match (true) {
            $record instanceof SurgeryCase => $record->id,
            $record instanceof Reservation => $record->case_id,
            $record instanceof CaseReturn => $record->case_id,
            $record instanceof Failure => $record->case_id,
            default => null,
        };
    }

    /** @return array{label: string, prefix: string} */
    private function definitionFor(Model $model): array
    {
        $definition = self::TRACEABLE_TYPES[$model::class] ?? null;

        if ($definition === null) {
            throw new RuntimeException('El modelo no es compatible con trazabilidad.');
        }

        return $definition;
    }

    private function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name)) ?: [];
        $initials = collect($words)
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->implode('.');

        return $initials === '' ? 'No registrado' : $initials.'.';
    }
}
