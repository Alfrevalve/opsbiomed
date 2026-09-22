<div>
    <!-- He who is contented is rich. - Laozi -->
</div>
@extends('layouts.ops')

@section('title', 'Control operativo | '.$case->case_code)

@section('content')
    @php
        $caseStatusClass = match ($case->status) {
            \App\Enums\CaseStatus::Cerrado,
            \App\Enums\CaseStatus::Cerrada,
            \App\Enums\CaseStatus::Facturada => 'ops-badge-success',
            \App\Enums\CaseStatus::PendienteCierre,
            \App\Enums\CaseStatus::Observado => 'ops-badge-warning',
            \App\Enums\CaseStatus::Cancelado => 'ops-badge-neutral',
            default => 'ops-badge-info',
        };
        $timelineClasses = [
            'completed' => 'border-emerald-200 bg-emerald-50',
            'current' => 'border-blue-200 bg-blue-50',
            'pending' => 'border-slate-200 bg-white',
            'blocked' => 'border-rose-200 bg-rose-50',
        ];
        $timelineBadgeClasses = [
            'completed' => 'ops-badge-success',
            'current' => 'ops-badge-info',
            'pending' => 'ops-badge-neutral',
            'blocked' => 'ops-badge-danger',
        ];
        $timelineLabels = [
            'completed' => 'Completada',
            'current' => 'Actual',
            'pending' => 'Pendiente',
            'blocked' => 'Bloqueada',
        ];
        $alertClasses = [
            'danger' => 'border-rose-200 bg-rose-50',
            'warning' => 'border-amber-200 bg-amber-50',
            'info' => 'border-blue-200 bg-blue-50',
        ];
        $alertTextClasses = [
            'danger' => 'text-rose-800',
            'warning' => 'text-amber-800',
            'info' => 'text-blue-800',
        ];
        $documentTypeLabels = [
            'solicitud' => 'Solicitud',
            'guia_internamiento' => 'Guia de internamiento',
            'cargo_recepcion' => 'Cargo de recepcion',
            'evidencia_consumo' => 'Evidencia de consumo',
            'hoja_consumo' => 'Hoja de consumo',
            'reporte_falla' => 'Reporte de falla',
            'evidencia_falla' => 'Evidencia de falla',
            'evidencia_devolucion' => 'Evidencia de devolucion',
            'inspeccion' => 'Inspeccion',
            'orden_compra' => 'Orden de compra',
            'factura' => 'Factura',
            'boleta' => 'Boleta',
            'aprobacion_costo_cero' => 'Aprobacion de costo cero',
            'solicitud_pago' => 'Solicitud de pago',
            'comprobante_pago' => 'Comprobante de pago',
            'otro' => 'Otro',
        ];
        $documentStatusClasses = [
            'pendiente' => 'ops-badge-warning',
            'cargado' => 'ops-badge-info',
            'validado' => 'ops-badge-success',
            'observado' => 'ops-badge-warning',
            'rechazado' => 'ops-badge-danger',
        ];
        $lotStatusClasses = [
            'apto' => 'ops-badge-success',
            'reservado' => 'ops-badge-info',
            'bloqueado' => 'ops-badge-danger',
            'cuarentena' => 'ops-badge-warning',
            'falla_preventiva' => 'ops-badge-danger',
            'vencido' => 'ops-badge-danger',
            'desvalorizado' => 'ops-badge-neutral',
            'observado' => 'ops-badge-warning',
        ];
        $currency = $billing?->currency ?? $case->valuation?->currency ?? 'PEN';
        $currencyPrefix = $currency === 'PEN' ? 'S/' : $currency;
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
            <div>
                <a href="{{ route('cases.show', $case) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Volver al detalle del caso</a>
                <p class="ops-eyebrow mt-4">Cirugia MR8</p>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h1 class="ops-section-title text-2xl font-semibold">Control operativo por cirugia</h1>
                    <span class="{{ $caseStatusClass }}">{{ $case->status->operationalLabel() }}</span>
                </div>
                <p class="ops-muted mt-2 text-sm">{{ $case->case_code }} / {{ $case->scheduled_at?->format('d/m/Y H:i') ?: 'Fecha por confirmar' }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @can('update', $case)
                    @if ($case->status->isEditable())
                        <a href="{{ route('cases.edit', $case) }}" class="ops-button-secondary inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">Editar solicitud</a>
                    @endif
                @endcan
                @can('reserve', $case)
                    <a href="{{ route('cases.reserve.create', $case) }}" class="ops-button-primary inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">Reservar material</a>
                @endcan
                @can('trace.print')
                    <a href="{{ route('trace.labels.cases', $case) }}" class="ops-button-secondary inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">Etiquetas del caso</a>
                @endcan
                @can('reservations.release')
                    @if ($case->status !== \App\Enums\CaseStatus::Cancelado)
                        <a href="{{ route('cases.cancel.form', $case) }}" class="ops-button-danger inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">Cancelar caso y revisar reservas</a>
                    @endif
                @endcan
                @if ($canPrepare && $preparationSummary['required'])
                    <a href="{{ route('cases.preparation', $case) }}" class="ops-button-secondary inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">Preparar y despachar</a>
                @endif
                @can('close', $case)
                    @if (! in_array($case->status, [\App\Enums\CaseStatus::Cerrado, \App\Enums\CaseStatus::Cerrada, \App\Enums\CaseStatus::Facturada], true))
                        <a href="{{ route('cases.close.create', $case) }}" class="ops-button-primary inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">Cerrar cirugia</a>
                    @endif
                @endcan
                @if (in_array($case->status, [\App\Enums\CaseStatus::Cerrado, \App\Enums\CaseStatus::Cerrada], true) && (auth()->user()?->can('cases.close') || auth()->user()?->can('billing.view') || auth()->user()?->can('billing.update')))
                    <a href="{{ route('cases.reconciliation', $case) }}" class="ops-button-secondary inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">Conciliar</a>
                @endif
                @if ($canViewDocuments && auth()->user()?->can('documents.upload'))
                    <a href="{{ route('cases.documents.index', $case) }}" class="ops-button-secondary inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">Subir documento</a>
                @endif
                @can('failures.create')
                    <a href="{{ route('failures.create', ['case_id' => $case->id]) }}" class="ops-button-danger inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">Reportar falla</a>
                @endcan
                @if ($canViewBilling)
                    <a href="{{ route('billing.show', $case) }}" class="ops-button-secondary inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">Ver facturacion</a>
                @endif
                @can('reports.view')
                    <a href="{{ route('reports.operations') }}" class="ops-button-secondary inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">Ver reportes</a>
                @endcan
            </div>
        </div>

        <section class="ops-card" aria-labelledby="case-summary-heading">
            <div class="border-b border-slate-200 px-5 py-4">
                <p class="ops-eyebrow">Caso</p>
                <h2 id="case-summary-heading" class="ops-section-title mt-1 text-lg font-semibold">Resumen operativo</h2>
            </div>
            <dl class="grid gap-x-6 gap-y-5 px-5 py-5 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Institucion</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $case->institution?->name ?: 'No registrada' }}</dd>
                </div>
                <div>
                    <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Medico</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $case->doctor?->name ?: 'No registrado' }}</dd>
                </div>
                <div>
                    <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Paciente</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $case->patient?->full_name ?: 'No registrado' }}</dd>
                </div>
                <div>
                    <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Tipo de cirugia</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $case->surgeryType?->name ?: 'No registrado' }}</dd>
                </div>
                <div>
                    <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Fecha y hora programada</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $case->scheduled_at?->format('d/m/Y H:i') ?: 'Por confirmar' }}</dd>
                </div>
                <div>
                    <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Estado actual</dt>
                    <dd class="mt-1"><span class="{{ $caseStatusClass }}">{{ $case->status->operationalLabel() }}</span></dd>
                </div>
                <div>
                    <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Responsable</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $case->createdBy?->name ?: 'No asignado' }}</dd>
                </div>
                <div>
                    <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Cobertura de reserva</dt>
                    <dd class="mt-1">
                        <span class="{{ $reservationOverview['complete'] ? 'ops-badge-success' : 'ops-badge-warning' }}">
                            {{ $reservationOverview['risks']->isEmpty() ? 'Sin reglas de kit' : ($reservationOverview['complete'] ? 'Completa' : 'Incompleta') }}
                        </span>
                    </dd>
                </div>
                @if ($preparationSummary['required'] || $preparationSummary['preparation'] !== null)
                    <div>
                        <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Preoperatorio y despacho</dt>
                        <dd class="mt-1">
                            <span class="{{ $preparationSummary['ready_for_room'] ? 'ops-badge-success' : 'ops-badge-warning' }}">
                                {{ $preparationSummary['ready_for_room'] ? 'Listo para sala' : 'Pendiente' }}
                            </span>
                        </dd>
                    </div>
                @endif
            </dl>
        </section>

        @if ($canViewSchedule)
            <section class="ops-card" aria-labelledby="schedule-resources-heading">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="ops-eyebrow">Agenda y recursos</p>
                        <h2 id="schedule-resources-heading" class="ops-section-title mt-1 text-lg font-semibold">Disponibilidad operativa</h2>
                    </div>
                    <a href="{{ route('schedule.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Abrir agenda</a>
                </div>

                <div class="grid gap-4 border-b border-slate-200 p-5 lg:grid-cols-3">
                    <div>
                        <p class="ops-muted text-xs font-semibold uppercase tracking-wide">Instrumentista asignado</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $case->assignedInstrumentist?->name ?: 'Sin asignar' }}</p>
                        @if ($case->assigned_at)
                            <p class="ops-muted mt-1 text-xs">Actualizado {{ $case->assigned_at->format('d/m/Y H:i') }} por {{ $case->assignedBy?->name ?: 'Sistema' }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="ops-muted text-xs font-semibold uppercase tracking-wide">Estado de reserva</p>
                        <p class="mt-1">
                            <span class="{{ match ($scheduleContext['reservation_complete'] ?? null) { true => 'ops-badge-success', false => 'ops-badge-warning', default => 'ops-badge-neutral' } }}">
                                {{ $scheduleContext['reservation_label'] ?? 'Sin datos' }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="ops-muted text-xs font-semibold uppercase tracking-wide">Semaforo de riesgo</p>
                        <p class="mt-1">
                            <span class="{{ match ($scheduleContext['risk'] ?? 'gray') { 'red' => 'ops-badge-danger', 'yellow' => 'ops-badge-warning', 'green' => 'ops-badge-success', default => 'ops-badge-neutral' } }}">
                                {{ $scheduleContext['risk_label'] ?? 'No aplica' }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-900">Recursos asignados</h3>
                            <span class="ops-badge-neutral">{{ $case->resourceAssignments->count() }} recursos</span>
                        </div>
                        @if ($case->resourceAssignments->isEmpty())
                            <p class="ops-muted mt-3 text-sm">No hay equipos, motores, consolas o sets asignados al caso.</p>
                        @else
                            <div class="mt-3 divide-y divide-slate-100 border border-slate-200">
                                @foreach ($case->resourceAssignments as $assignment)
                                    <div class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ $resourceTypeLabels[$assignment->resource_type] ?? \Illuminate\Support\Str::headline($assignment->resource_type) }}</p>
                                            <p class="ops-muted mt-1 text-xs">
                                                {{ $assignment->inventoryLot?->product?->name ?: 'Recurso sin lote asociado' }}
                                                @if ($assignment->inventoryLot?->product?->product_code)
                                                    / {{ $assignment->inventoryLot->product->product_code }}
                                                @endif
                                                @if ($assignment->inventoryLot?->serial || $assignment->inventoryLot?->lot)
                                                    / {{ $assignment->inventoryLot?->serial ?: $assignment->inventoryLot?->lot }}
                                                @endif
                                            </p>
                                        </div>
                                        @if ($canManageSchedule)
                                            <form method="POST" action="{{ route('cases.resources.destroy', [$case, $assignment]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm font-semibold text-rose-700 hover:text-rose-900">Retirar</button>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Alertas de cruce</h3>
                        @if ($scheduleConflicts->isEmpty())
                            <p class="ops-muted mt-3 text-sm">No se detectaron cruces de instrumentista, recursos o reserva para este caso.</p>
                        @else
                            <div class="mt-3 space-y-2">
                                @foreach ($scheduleConflicts as $conflict)
                                    <article @class([
                                        'border p-3',
                                        'border-rose-200 bg-rose-50' => $conflict['severity'] === 'critico',
                                        'border-amber-200 bg-amber-50' => $conflict['severity'] !== 'critico',
                                    ])>
                                        <p class="text-sm font-semibold text-slate-900">{{ \Illuminate\Support\Str::headline($conflict['type']) }}</p>
                                        <p class="mt-1 text-xs text-slate-700">{{ $conflict['suggestion'] }}</p>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                @if ($canManageSchedule)
                    <div class="grid gap-5 border-t border-slate-200 p-5 xl:grid-cols-2">
                        <form method="POST" action="{{ route('cases.schedule.instrumentist.update', $case) }}" class="border border-slate-200 p-4">
                            @csrf
                            @method('PATCH')
                            <label for="assigned_instrumentist_id" class="text-sm font-semibold text-slate-800">Asignar instrumentista</label>
                            <select id="assigned_instrumentist_id" name="assigned_instrumentist_id" class="mt-2 block w-full border-slate-300 text-sm text-slate-900 focus:border-cyan-700 focus:ring-cyan-700">
                                <option value="">Sin instrumentista asignado</option>
                                @foreach ($instrumentists as $instrumentist)
                                    <option value="{{ $instrumentist->id }}" @selected((string) old('assigned_instrumentist_id', $case->assigned_instrumentist_id) === (string) $instrumentist->id)>{{ $instrumentist->name }}</option>
                                @endforeach
                            </select>
                            @error('assigned_instrumentist_id')
                                <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="ops-button-primary mt-4 px-3 py-2 text-sm font-semibold">Guardar instrumentista</button>
                        </form>

                        <form method="POST" action="{{ route('cases.resources.store', $case) }}" class="border border-slate-200 p-4">
                            @csrf
                            <label for="resource_type" class="text-sm font-semibold text-slate-800">Asignar recurso reutilizable</label>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                <select id="resource_type" name="resource_type" required class="border-slate-300 text-sm text-slate-900 focus:border-cyan-700 focus:ring-cyan-700">
                                    <option value="">Tipo de recurso</option>
                                    @foreach ($resourceTypeLabels as $value => $label)
                                        <option value="{{ $value }}" @selected(old('resource_type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select id="inventory_lot_id" name="inventory_lot_id" class="border-slate-300 text-sm text-slate-900 focus:border-cyan-700 focus:ring-cyan-700">
                                    <option value="">Sin lote asociado</option>
                                    @foreach ($availableResources as $resourceLot)
                                        <option value="{{ $resourceLot->id }}" @selected((string) old('inventory_lot_id') === (string) $resourceLot->id)>
                                            {{ $resourceLot->product?->product_code }} / {{ $resourceLot->serial ?: $resourceLot->lot ?: 'Sin serie' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('resource_type')
                                <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
                            @enderror
                            @error('inventory_lot_id')
                                <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
                            @enderror
                            @error('resource_assignment')
                                <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="ops-button-primary mt-4 px-3 py-2 text-sm font-semibold">Asignar recurso</button>
                        </form>
                    </div>
                @endif
            </section>
        @endif

        <section class="ops-card" aria-labelledby="timeline-heading">
            <div class="border-b border-slate-200 px-5 py-4">
                <p class="ops-eyebrow">Seguimiento</p>
                <h2 id="timeline-heading" class="ops-section-title mt-1 text-lg font-semibold">Linea de tiempo operativa</h2>
            </div>
            <ol class="grid gap-3 px-5 py-5 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($timeline as $stage)
                    <li class="border p-4 {{ $timelineClasses[$stage['state']] }}">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-900">{{ $stage['label'] }}</p>
                            <span class="{{ $timelineBadgeClasses[$stage['state']] }}">{{ $timelineLabels[$stage['state']] }}</span>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        @if ($canTransition)
            <section class="ops-card" aria-labelledby="transition-heading">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="ops-eyebrow">Seguimiento en tiempo real</p>
                        <h2 id="transition-heading" class="ops-section-title mt-1 text-lg font-semibold">Avance operativo</h2>
                    </div>
                    <span class="{{ $caseStatusClass }}">Estado actual: {{ $case->status->operationalLabel() }}</span>
                </div>

                @if ($errors->has('transition'))
                    <div class="border-b border-rose-200 bg-rose-50 px-5 py-3 text-sm font-medium text-rose-800" role="alert">
                        {{ $errors->first('transition') }}
                    </div>
                @endif

                <div class="p-5">
                    @if ($transitionOptions->isEmpty())
                        <p class="ops-muted text-sm">{{ $transitionNotice ?: 'No hay un cambio de estado disponible para este caso.' }}</p>
                    @else
                        <form method="POST" action="{{ route('cases.transition', $case) }}" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_auto] lg:items-end">
                            @csrf
                            <input type="hidden" name="override" value="0">

                            <div>
                                <label for="target_status" class="text-sm font-semibold text-slate-800">Siguiente estado permitido</label>
                                <select id="target_status" name="target_status" required class="mt-2 block w-full border-slate-300 text-sm text-slate-900 focus:border-cyan-700 focus:ring-cyan-700">
                                    <option value="">Seleccione un estado</option>
                                    @foreach ($transitionOptions as $transition)
                                        <option value="{{ $transition['value'] }}" @selected(old('target_status') === $transition['value'])>
                                            {{ $transition['label'] }}{{ $transition['requires_override'] ? ' (override)' : '' }}{{ $transition['requires_observation'] ? ' *' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('target_status')
                                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="transition_observation" class="text-sm font-semibold text-slate-800">Observacion operativa</label>
                                <textarea id="transition_observation" name="observation" rows="3" maxlength="2000" class="mt-2 block w-full border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-700 focus:ring-cyan-700" placeholder="Registre el contexto operativo, especialmente en cancelaciones u overrides.">{{ old('observation') }}</textarea>
                                <p class="ops-muted mt-1 text-xs">La observacion es obligatoria para cancelaciones, excepciones de valorizacion y overrides autorizados.</p>
                                @error('observation')
                                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-3">
                                @if ($canOverrideTransition)
                                    <label class="flex items-start gap-2 text-sm text-slate-700">
                                        <input type="checkbox" name="override" value="1" @checked(old('override')) class="mt-0.5 rounded border-slate-300 text-cyan-700 focus:ring-cyan-700">
                                        <span>Aplicar override autorizado</span>
                                    </label>
                                @endif
                                @error('override')
                                    <p class="text-sm text-rose-700">{{ $message }}</p>
                                @enderror
                                <button type="submit" class="ops-button-primary inline-flex w-full items-center justify-center px-4 py-2.5 text-sm font-semibold lg:w-auto">Actualizar estado</button>
                            </div>
                        </form>

                        @if ($transitionNotice)
                            <p class="ops-muted mt-4 border-l-2 border-cyan-700 pl-3 text-sm">{{ $transitionNotice }}</p>
                        @endif
                    @endif
                </div>

                <div class="border-t border-slate-200 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-900">Ultimas transiciones</h3>
                    <div class="mt-3 space-y-3">
                        @forelse ($statusTransitions as $statusTransition)
                            @php
                                $transitionAfter = $statusTransition->after ?? [];
                                $previousLabel = $transitionAfter['previous_label'] ?? $transitionAfter['previous_status'] ?? 'Estado anterior';
                                $newLabel = $transitionAfter['new_label'] ?? $transitionAfter['new_status'] ?? 'Estado actualizado';
                            @endphp
                            <div class="flex flex-col justify-between gap-2 border border-slate-200 p-3 sm:flex-row sm:items-start">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $previousLabel }} <span class="ops-muted">a</span> {{ $newLabel }}</p>
                                    <p class="ops-muted mt-1 text-xs">Por {{ $statusTransition->user?->name ?: 'Sistema' }}</p>
                                    @if (filled($transitionAfter['observation'] ?? null))
                                        <p class="mt-2 text-sm text-slate-700">{{ $transitionAfter['observation'] }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 sm:flex-col sm:items-end">
                                    @if (($transitionAfter['override'] ?? false) === true)
                                        <span class="ops-badge-warning">Override</span>
                                    @endif
                                    <time class="ops-muted text-xs">{{ $statusTransition->created_at?->format('d/m/Y H:i') ?: 'Sin fecha' }}</time>
                                </div>
                            </div>
                        @empty
                            <p class="ops-muted text-sm">Aun no hay transiciones operativas registradas para este caso.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        @endif

        <section class="ops-card" aria-labelledby="alerts-heading">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <p class="ops-eyebrow">Prioridad del caso</p>
                    <h2 id="alerts-heading" class="ops-section-title mt-1 text-lg font-semibold">Alertas operativas</h2>
                </div>
                <span class="{{ $controlAlerts->isEmpty() ? 'ops-badge-success' : 'ops-badge-warning' }}">{{ $controlAlerts->isEmpty() ? 'Sin alertas activas' : $controlAlerts->count().' alertas' }}</span>
            </div>
            @if ($controlAlerts->isEmpty())
                <p class="ops-muted px-5 py-7 text-sm">El caso no tiene alertas operativas activas para el perfil actual.</p>
            @else
                <div class="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($controlAlerts as $alert)
                        <article class="border p-4 {{ $alertClasses[$alert['level']] }}">
                            <p class="font-semibold {{ $alertTextClasses[$alert['level']] }}">{{ $alert['title'] }}</p>
                            <p class="mt-1 text-sm leading-6 {{ $alertTextClasses[$alert['level']] }}">{{ $alert['description'] }}</p>
                            @if ($alert['href'])
                                <a href="{{ $alert['href'] }}" class="mt-3 inline-flex text-sm font-semibold {{ $alertTextClasses[$alert['level']] }} hover:underline">Ver detalle</a>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="ops-card" aria-labelledby="materials-heading">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <p class="ops-eyebrow">Trazabilidad de material</p>
                    <h2 id="materials-heading" class="ops-section-title mt-1 text-lg font-semibold">Material quirurgico</h2>
                </div>
                <span class="ops-badge-neutral">{{ $materialRows->count() }} lotes</span>
            </div>
            @if ($materialRows->isEmpty())
                <p class="ops-muted px-5 py-7 text-sm">No hay material reservado ni consumo registrado para este caso.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead>
                            <tr>
                                <th class="px-5 py-3 font-semibold">Producto</th>
                                <th class="px-5 py-3 font-semibold">Codigo</th>
                                <th class="px-5 py-3 font-semibold">Lote / serie</th>
                                <th class="px-5 py-3 text-right font-semibold">Reservada</th>
                                <th class="px-5 py-3 text-right font-semibold">Usada</th>
                                <th class="px-5 py-3 text-right font-semibold">Devuelta</th>
                                <th class="px-5 py-3 text-right font-semibold">Falla</th>
                                <th class="px-5 py-3 font-semibold">Diferencia</th>
                                <th class="px-5 py-3 font-semibold">Estado del lote</th>
                                @if ($canViewBilling)
                                    <th class="px-5 py-3 text-right font-semibold">Precio</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($materialRows as $row)
                                @php
                                    $lotStatus = $row['lot']?->status?->value ?? 'observado';
                                    $difference = $row['difference_qty'];
                                @endphp
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-slate-900">{{ $row['product']?->name ?: 'Producto no registrado' }}</p>
                                        @if ($canViewInventory && $row['lot'])
                                            <a href="{{ route('inventory.show', $row['lot']) }}" class="mt-1 inline-flex text-xs font-semibold text-sky-700 hover:text-sky-900">Ver lote</a>
                                        @endif
                                        @can('reservations.release')
                                            @if ($row['reservation']?->status === 'active')
                                                <a href="{{ route('reservations.release.form', $row['reservation']) }}" class="mt-1 inline-flex text-xs font-semibold text-rose-700 hover:text-rose-900">Revisar liberacion</a>
                                            @endif
                                        @endcan
                                    </td>
                                    <td class="px-5 py-4 font-mono text-xs text-slate-700">{{ $row['product']?->product_code ?: 'Sin codigo' }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $row['lot']?->lot ?: 'Sin lote' }}{{ $row['lot']?->serial ? ' / '.$row['lot']->serial : '' }}</td>
                                    <td class="px-5 py-4 text-right font-semibold text-slate-900">{{ number_format($row['reserved_qty']) }}</td>
                                    <td class="px-5 py-4 text-right text-slate-700">{{ number_format($row['used_qty']) }}</td>
                                    <td class="px-5 py-4 text-right text-slate-700">{{ number_format($row['returned_qty']) }}</td>
                                    <td class="px-5 py-4 text-right text-slate-700">{{ number_format($row['failure_qty']) }}</td>
                                    <td class="px-5 py-4">
                                        @if (! $row['has_consumption'])
                                            <span class="ops-badge-neutral">Pendiente de cierre</span>
                                        @elseif ($difference > 0)
                                            <span class="ops-badge-warning">{{ number_format($difference) }} pendiente</span>
                                        @else
                                            <span class="ops-badge-success">Conciliada</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4"><span class="{{ $lotStatusClasses[$lotStatus] ?? 'ops-badge-neutral' }}">{{ str_replace('_', ' ', $lotStatus) }}</span></td>
                                    @if ($canViewBilling)
                                        <td class="px-5 py-4 text-right font-semibold text-slate-900">{{ $row['unit_price'] !== null ? $currencyPrefix.' '.number_format((float) $row['unit_price'], 2) : 'Sin valor' }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($canViewDocuments || $canViewFailures || $canViewReturns)
            <div class="grid gap-6 xl:grid-cols-2">
                @if ($canViewDocuments)
                    <section class="ops-card" aria-labelledby="documents-heading">
                        <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                            <div>
                                <p class="ops-eyebrow">Evidencias</p>
                                <h2 id="documents-heading" class="ops-section-title mt-1 text-lg font-semibold">Documentos asociados</h2>
                            </div>
                            <a href="{{ route('cases.documents.index', $case) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Gestionar documentos</a>
                        </div>
                        @if ($case->documents->isEmpty())
                            <p class="ops-muted px-5 py-7 text-sm">No hay documentos cargados para este caso.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                    <thead>
                                        <tr>
                                            <th class="px-5 py-3 font-semibold">Tipo</th>
                                            <th class="px-5 py-3 font-semibold">Estado</th>
                                            <th class="px-5 py-3 font-semibold">Cargado por</th>
                                            <th class="px-5 py-3 font-semibold">Fecha</th>
                                            <th class="px-5 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($case->documents as $document)
                                            <tr>
                                                <td class="px-5 py-4">
                                                    <p class="font-semibold text-slate-900">{{ $documentTypeLabels[$document->document_type] ?? str_replace('_', ' ', ucfirst($document->document_type)) }}</p>
                                                    <p class="ops-muted mt-1 text-xs">{{ $document->title }}</p>
                                                </td>
                                                <td class="px-5 py-4"><span class="{{ $documentStatusClasses[$document->status] ?? 'ops-badge-neutral' }}">{{ ucfirst($document->status) }}</span></td>
                                                <td class="px-5 py-4 text-slate-700">{{ $document->uploadedBy?->name ?: 'Sistema' }}</td>
                                                <td class="px-5 py-4 text-slate-700">{{ $document->created_at?->format('d/m/Y H:i') ?: 'Sin fecha' }}</td>
                                                <td class="px-5 py-4 text-right">
                                                    <div class="flex flex-wrap justify-end gap-3">
                                                        <a href="{{ route('documents.show', $document) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ver</a>
                                                        @if ($document->file_path)
                                                            <a href="{{ route('documents.download', $document) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Descargar</a>
                                                        @elseif ($document->link_url && \Illuminate\Support\Str::startsWith($document->link_url, ['http://', 'https://']))
                                                            <a href="{{ $document->link_url }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Abrir</a>
                                                        @elseif ($document->link_url)
                                                            <span class="text-sm text-slate-500">Referencia registrada</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>
                @endif

                @if ($canViewFailures || $canViewReturns)
                    <section class="ops-card" aria-labelledby="technical-heading">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <p class="ops-eyebrow">Calidad tecnica</p>
                            <h2 id="technical-heading" class="ops-section-title mt-1 text-lg font-semibold">Fallas y devoluciones</h2>
                        </div>
                        <div class="divide-y divide-slate-200">
                            @if ($canViewFailures)
                                <div class="p-5">
                                    <div class="flex items-center justify-between gap-3">
                                        <h3 class="text-sm font-semibold text-slate-900">Fallas asociadas</h3>
                                        <span class="{{ $openFailures->isEmpty() ? 'ops-badge-success' : 'ops-badge-danger' }}">{{ $openFailures->count() }} abiertas</span>
                                    </div>
                                    <div class="mt-3 space-y-3">
                                        @forelse ($case->failures as $failure)
                                            <div class="border border-slate-200 p-3">
                                                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                                                    <div>
                                                        <p class="font-semibold text-slate-900">{{ $failure->product?->product_code ?: $failure->inventoryLot?->product?->product_code ?: 'Producto no especificado' }}</p>
                                                        <p class="ops-muted mt-1 text-xs">{{ $failure->inventoryLot?->lot ?: 'Sin lote' }} / {{ str_replace('_', ' ', $failure->failure_type) }}</p>
                                                    </div>
                                                    <span class="{{ in_array($failure->status, ['bloqueada', 'reportada', 'pendiente_repuesto'], true) ? 'ops-badge-danger' : 'ops-badge-info' }}">{{ str_replace('_', ' ', $failure->status) }}</span>
                                                </div>
                                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                                    <span class="ops-muted text-xs">Lote: {{ str_replace('_', ' ', $failure->inventoryLot?->status?->value ?? 'sin estado') }}</span>
                                                    <a href="{{ route('failures.show', $failure) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ver falla</a>
                                                </div>
                                            </div>
                                        @empty
                                            <p class="ops-muted text-sm">No hay fallas asociadas a este caso.</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endif

                            @if ($canViewReturns)
                                <div class="p-5">
                                    <div class="flex items-center justify-between gap-3">
                                        <h3 class="text-sm font-semibold text-slate-900">Devoluciones</h3>
                                        <span class="{{ $pendingReturns->isEmpty() ? 'ops-badge-success' : 'ops-badge-warning' }}">{{ $pendingReturns->count() }} pendientes</span>
                                    </div>
                                    <div class="mt-3 space-y-3">
                                        @forelse ($case->returns as $return)
                                            <div class="border border-slate-200 p-3">
                                                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                                                    <div>
                                                        <p class="font-semibold text-slate-900">{{ $return->inventoryLot?->product?->product_code ?: 'Producto no registrado' }}</p>
                                                        <p class="ops-muted mt-1 text-xs">{{ $return->inventoryLot?->lot ?: 'Sin lote' }} / {{ number_format($return->returned_qty) }} unidad(es)</p>
                                                    </div>
                                                    <span class="{{ $return->condition === 'pendiente_inspeccion' ? 'ops-badge-warning' : 'ops-badge-success' }}">{{ str_replace('_', ' ', $return->condition) }}</span>
                                                </div>
                                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                                    <span class="ops-muted text-xs">{{ $return->inspection_result ? str_replace('_', ' ', $return->inspection_result) : 'Resultado pendiente' }}</span>
                                                    <a href="{{ route('returns.show', $return) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ver devolucion</a>
                                                </div>
                                            </div>
                                        @empty
                                            <p class="ops-muted text-sm">No hay devoluciones registradas para este caso.</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>
                @endif
            </div>
        @endif

        @if ($canViewBilling)
            <section class="ops-card" aria-labelledby="billing-heading">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="ops-eyebrow">Administracion</p>
                        <h2 id="billing-heading" class="ops-section-title mt-1 text-lg font-semibold">Facturacion y cobranza</h2>
                    </div>
                    <a href="{{ route('billing.show', $case) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Abrir facturacion</a>
                </div>
                @if ($billing || $case->valuation)
                    <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="border border-slate-200 p-4">
                            <p class="ops-muted text-xs font-semibold uppercase tracking-wide">Valorizacion</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $currencyPrefix }} {{ number_format((float) ($case->valuation?->total ?? $billing?->amount ?? 0), 2) }}</p>
                        </div>
                        <div class="border border-slate-200 p-4">
                            <p class="ops-muted text-xs font-semibold uppercase tracking-wide">Costo cero</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $case->valuation?->approval?->status ? str_replace('_', ' ', $case->valuation->approval->status) : 'No aplica' }}</p>
                        </div>
                        <div class="border border-slate-200 p-4">
                            <p class="ops-muted text-xs font-semibold uppercase tracking-wide">OC</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $billing?->purchase_order ?: 'Pendiente' }}</p>
                        </div>
                        <div class="border border-slate-200 p-4">
                            <p class="ops-muted text-xs font-semibold uppercase tracking-wide">Factura</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $billing?->invoice_number ?: 'Pendiente' }}</p>
                        </div>
                        <div class="border border-slate-200 p-4">
                            <p class="ops-muted text-xs font-semibold uppercase tracking-wide">Saldo</p>
                            <p class="mt-2 text-lg font-semibold {{ $billing?->balance > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $currencyPrefix }} {{ number_format((float) ($billing?->balance ?? 0), 2) }}</p>
                            <p class="ops-muted mt-1 text-xs">{{ $billing?->payment_status ? str_replace('_', ' ', $billing->payment_status) : 'Sin registro de pago' }}</p>
                        </div>
                    </div>
                @else
                    <p class="ops-muted px-5 py-7 text-sm">El caso aun no tiene valorizacion ni registro de facturacion.</p>
                @endif
            </section>
        @endif

        @if ($canAudit)
            <section class="ops-card" aria-labelledby="audit-heading">
                <div class="border-b border-slate-200 px-5 py-4">
                    <p class="ops-eyebrow">Trazabilidad</p>
                    <h2 id="audit-heading" class="ops-section-title mt-1 text-lg font-semibold">Auditoria reciente</h2>
                </div>
                @if ($auditEntries->isEmpty())
                    <p class="ops-muted px-5 py-7 text-sm">No hay movimientos auditados para este caso.</p>
                @else
                    <div class="divide-y divide-slate-200">
                        @foreach ($auditEntries as $entry)
                            <div class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $entry['action'] }}</p>
                                    <p class="ops-muted mt-1 text-sm">{{ $entry['summary'] }}</p>
                                    <p class="ops-muted mt-1 text-xs">Por {{ $entry['user'] }}</p>
                                </div>
                                <time class="ops-muted shrink-0 text-xs">{{ $entry['created_at']?->format('d/m/Y H:i:s') ?: 'Sin fecha' }}</time>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
