@extends('layouts.ops')

@section('title', $case->case_code.' | OPS BIOMED MR8')

@section('content')
    @php
        $statusClass = match ($case->status) {
            \App\Enums\CaseStatus::Cerrado => 'bg-emerald-50 text-emerald-700',
            \App\Enums\CaseStatus::Cancelado => 'bg-slate-100 text-slate-600',
            \App\Enums\CaseStatus::PendienteCierre => 'bg-amber-50 text-amber-700',
            default => 'bg-sky-50 text-sky-700',
        };
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('cases.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver a solicitudes</a>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950">{{ $case->case_code }}</h1>
                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $case->status->label() }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-500">Solicitud registrada el {{ $case->created_at?->format('d/m/Y H:i') }}.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('cases.control', $case) }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Control operativo</a>
                @can('update', $case)
                    @if ($case->status->isEditable())
                        <a href="{{ route('cases.edit', $case) }}" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-800">Editar solicitud</a>
                    @endif
                @endcan
                @can('reserve', $case)
                    <a href="{{ route('cases.reserve.create', $case) }}" class="inline-flex items-center justify-center bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">Reservar material</a>
                @endcan
                @can('prepare', $case)
                    @if (in_array($case->status, [
                        \App\Enums\CaseStatus::Reservado,
                        \App\Enums\CaseStatus::Reservada,
                        \App\Enums\CaseStatus::Preparacion,
                        \App\Enums\CaseStatus::PreoperatorioConfirmado,
                        \App\Enums\CaseStatus::Internado,
                    ], true))
                        <a href="{{ route('cases.preparation', $case) }}" class="inline-flex items-center justify-center border border-cyan-300 bg-cyan-50 px-4 py-2.5 text-sm font-semibold text-cyan-900 hover:bg-cyan-100">Preparar y despachar</a>
                    @endif
                @endcan
                @can('failures.create')
                    <a href="{{ route('failures.create', ['case_id' => $case->id]) }}" class="inline-flex items-center justify-center border border-rose-300 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-800 hover:bg-rose-100">Reportar falla</a>
                @endcan
                @can('close', $case)
                    @if (! in_array($case->status, [\App\Enums\CaseStatus::Cerrado, \App\Enums\CaseStatus::Cerrada, \App\Enums\CaseStatus::Facturada], true))
                        <a href="{{ route('cases.close.create', $case) }}" class="inline-flex items-center justify-center bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-700">Cerrar cirugia</a>
                    @endif
                @endcan
                @if (in_array($case->status, [\App\Enums\CaseStatus::Cerrado, \App\Enums\CaseStatus::Cerrada], true) && (auth()->user()->can('cases.close') || auth()->user()->can('billing.view') || auth()->user()->can('billing.update')))
                    <a href="{{ route('cases.reconciliation', $case) }}" class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Conciliar consumo</a>
                @endif
                @if (auth()->user()->can('billing.view') || auth()->user()->can('commercial.view'))
                    <a href="{{ route('billing.show', $case) }}" class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver facturacion</a>
                @endif
                <a href="{{ route('cases.create') }}" class="inline-flex items-center justify-center border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Nueva solicitud</a>
            </div>
        </div>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                <div><h2 class="font-semibold text-slate-950">Documentos y evidencias</h2><p class="mt-1 text-sm text-slate-500">Solicitud, consumo, fallas, devoluciones y respaldo administrativo.</p></div>
                @can('documents.view')<a href="{{ route('cases.documents.index', $case) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Gestionar documentos</a>@endcan
            </div>
            @can('documents.view')
                @include('documents._list', ['documents' => $case->documents])
            @else
                <p class="px-5 py-6 text-sm text-slate-500">No tienes permiso para consultar evidencias documentales.</p>
            @endcan
        </section>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Datos de la solicitud</h2></div>
            <dl class="grid gap-x-8 gap-y-6 px-5 py-6 sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Institucion</dt><dd class="mt-1 text-sm text-slate-900">{{ $case->institution?->name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Medico</dt><dd class="mt-1 text-sm text-slate-900">{{ $case->doctor?->name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Paciente</dt><dd class="mt-1 text-sm text-slate-900">{{ $case->patient?->full_name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo de cirugia</dt><dd class="mt-1 text-sm text-slate-900">{{ $case->surgeryType?->name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha y hora</dt><dd class="mt-1 text-sm text-slate-900">{{ $case->scheduled_at?->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Origen</dt><dd class="mt-1 text-sm capitalize text-slate-900">{{ str_replace('_', ' ', $case->request_origin) }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prioridad</dt><dd class="mt-1 text-sm capitalize text-slate-900">{{ $case->priority }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Material solicitado</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $case->procedure_name }}</dd></div>
                <div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observaciones</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $case->notes }}</dd></div>
            </dl>
        </section>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="font-semibold text-slate-950">Riesgo de ruptura de stock</h2>
                        <p class="mt-1 text-sm text-slate-500">Cobertura disponible para las combinaciones exactas del tipo de cirugia.</p>
                    </div>
                    <span class="text-sm font-semibold {{ $reservationOverview['complete'] ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ $reservationOverview['complete'] ? 'Reserva completa' : 'Reserva incompleta' }}
                    </span>
                </div>
            </div>

            @if ($reservationOverview['risks']->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-500">Este tipo de cirugia no tiene reglas de material configuradas.</p>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($reservationOverview['risks'] as $risk)
                        @php
                            $riskClass = match ($risk['risk']) {
                                'green' => 'bg-emerald-50 text-emerald-700',
                                'yellow' => 'bg-amber-50 text-amber-700',
                                default => 'bg-rose-50 text-rose-700',
                            };
                        @endphp
                        <div class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $risk['label'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $risk['available_net'] }} unidades netas · {{ $risk['coverage'] }} cirugias cubiertas · {{ $risk['reserved_for_case'] }} reservadas para este caso</p>
                            </div>
                            <span class="inline-flex w-fit px-2.5 py-1 text-xs font-semibold {{ $riskClass }}">{{ $risk['risk_label'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($case->reservations->where('status', 'active')->isNotEmpty())
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Reservas activas</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-5 py-3 font-semibold">Producto</th><th class="px-5 py-3 font-semibold">Lote</th><th class="px-5 py-3 font-semibold">Cantidad</th><th class="px-5 py-3 font-semibold">Reservado por</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($case->reservations->where('status', 'active') as $reservation)
                                <tr>
                                    <td class="px-5 py-4 text-slate-700">{{ $reservation->inventoryLot?->product?->name ?? $reservation->inventoryLot?->product?->product_code }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $reservation->inventoryLot?->lot ?? 'Sin lote' }}</td>
                                    <td class="px-5 py-4 font-semibold text-slate-900">{{ $reservation->quantity }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $reservation->reservedBy?->name ?? 'Usuario' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Reservas activas</h2></div>
                <p class="px-5 py-8 text-sm text-slate-500">No hay reservas activas.</p>
            </section>
        @endif

        @if ($case->materialsUsed->isNotEmpty())
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-950">Consumo y conciliacion</h2>
                    <p class="mt-1 text-sm text-slate-500">Detalle registrado durante el cierre de la cirugia.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Producto / lote</th>
                                <th class="px-5 py-3 font-semibold">Reservado</th>
                                <th class="px-5 py-3 font-semibold">Usado</th>
                                <th class="px-5 py-3 font-semibold">Abierto no usado</th>
                                <th class="px-5 py-3 font-semibold">Devuelto</th>
                                <th class="px-5 py-3 font-semibold">Falla</th>
                                <th class="px-5 py-3 font-semibold">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($case->materialsUsed as $material)
                                <tr>
                                    <td class="px-5 py-4 text-slate-700">{{ $material->inventoryLot?->product?->product_code }} <span class="block text-xs text-slate-500">{{ $material->inventoryLot?->lot ?? 'Sin lote' }}</span></td>
                                    <td class="px-5 py-4 text-slate-700">{{ $material->reserved_qty }}</td>
                                    <td class="px-5 py-4 font-semibold text-slate-900">{{ $material->used_qty }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $material->unused_opened_qty }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $material->returned_qty }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $material->failure_qty }}</td>
                                    <td class="px-5 py-4 {{ $material->difference_qty > 0 ? 'font-semibold text-rose-700' : 'text-slate-700' }}">{{ $material->difference_qty }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($case->reconciliation)
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col justify-between gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="font-semibold text-slate-950">Conciliacion</h2>
                        <p class="mt-1 text-sm text-slate-500">Conciliacion registrada el {{ $case->reconciliation->completed_at?->format('d/m/Y H:i') }}.</p>
                    </div>
                    <span class="inline-flex bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">{{ $case->reconciliation->status }}</span>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ([
                        ['label' => 'Reservado', 'value' => $case->reconciliation->total_reserved],
                        ['label' => 'Usado', 'value' => $case->reconciliation->total_used],
                        ['label' => 'Devuelto', 'value' => $case->reconciliation->total_returned],
                        ['label' => 'Abierto no usado', 'value' => $case->reconciliation->total_unused_opened],
                        ['label' => 'Falla', 'value' => $case->reconciliation->total_failure],
                        ['label' => 'Diferencia', 'value' => $case->reconciliation->total_difference],
                    ] as $total)
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $total['label'] }}</p><p class="mt-1 text-lg font-semibold text-slate-950">{{ $total['value'] }}</p></div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($canViewBilling && ($case->valuation || $case->billingRecord))
            <section class="grid gap-6 lg:grid-cols-2">
                <div class="border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Valorizacion preliminar</h2></div>
                    <dl class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</dt><dd class="mt-1 text-sm text-slate-900">{{ $case->billingRecord?->invoice_status ?? 'pendiente_valorizacion' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</dt><dd class="mt-1 text-lg font-semibold text-slate-950">S/ {{ number_format((float) ($case->billingRecord?->amount ?? $case->valuation?->total ?? 0), 2) }}</dd></div>
                    </dl>
                    @if ($case->valuation?->lines?->isNotEmpty())
                        <div class="border-t border-slate-200 px-5 py-4">
                            @foreach ($case->valuation->lines as $line)
                                <div class="flex items-center justify-between gap-4 py-2 text-sm">
                                    <span class="text-slate-700">{{ $line->product?->product_code }} x {{ $line->quantity_used }}</span>
                                    <span class="font-semibold text-slate-900">S/ {{ number_format((float) $line->subtotal, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Devoluciones e incidencias</h2></div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($case->returns as $caseReturn)
                            <div class="px-5 py-4 text-sm"><p class="font-semibold text-slate-900">Devolucion: <a href="{{ route('returns.show', $caseReturn) }}" class="text-sky-700 hover:text-sky-900">{{ $caseReturn->inventoryLot?->product?->product_code }}</a></p><p class="mt-1 text-slate-500">{{ $caseReturn->returned_qty }} unidades · {{ str_replace('_', ' ', ucfirst($caseReturn->condition)) }}{{ $caseReturn->inspectionResponsible ? ' · '.$caseReturn->inspectionResponsible->name : '' }}</p></div>
                        @empty
                            <p class="px-5 py-4 text-sm text-slate-500">No hay devoluciones registradas.</p>
                        @endforelse
                        @foreach ($case->failures as $failure)
                            <div class="px-5 py-4 text-sm"><div class="flex flex-col justify-between gap-2 sm:flex-row"><p class="font-semibold text-rose-700">Falla tecnica #{{ $failure->id }} · {{ ucfirst($failure->severity) }}</p><a href="{{ route('failures.show', $failure) }}" class="font-semibold text-sky-700 hover:text-sky-900">Ver reporte</a></div><p class="mt-1 text-slate-700">{{ $failure->description }}</p><p class="mt-1 text-xs text-slate-500">{{ $failure->responsibleTechnical?->name ? 'Responsable: '.$failure->responsibleTechnical->name : 'Sin responsable tecnico asignado' }}</p></div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($canAudit)
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Historial de auditoria</h2></div>
                @if ($auditLogs->isEmpty())
                    <p class="px-5 py-8 text-sm text-slate-500">No hay eventos de auditoria para esta solicitud.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($auditLogs as $audit)
                            <div class="px-5 py-4">
                                <div class="flex flex-col justify-between gap-2 sm:flex-row">
                                    <p class="text-sm font-semibold text-slate-900">{{ $audit->action }}</p>
                                    <p class="text-xs text-slate-500">{{ $audit->created_at?->format('d/m/Y H:i:s') }}</p>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">Por {{ $audit->user?->name ?: 'Sistema' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
