@extends('layouts.ops')

@section('title', 'Detalle de inventario | OPS BIOMED MR8')

@section('content')
    @php
        $statusLabels = [
            'apto' => 'Disponible',
            'reservado' => 'Reservado',
            'bloqueado' => 'Bloqueado',
            'cuarentena' => 'Cuarentena',
            'falla_preventiva' => 'Falla preventiva',
            'vencido' => 'Vencido',
            'desvalorizado' => 'Desvalorizado',
            'observado' => 'Observado',
        ];
        $statusValue = $lot->status?->value ?? (string) $lot->status;
        $statusLabel = $statusLabels[$statusValue] ?? ucfirst($statusValue);
        $reservedActive = (int) $lot->reservations->where('status', 'active')->sum('quantity');
        $availableNet = max(0, (int) $lot->quantity - $reservedActive);
    @endphp

    <div class="space-y-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('inventory.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al inventario</a>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ $lot->product?->product_code }} <span class="font-normal text-slate-500">/ {{ $lot->lot ?: 'Sin lote' }}</span></h1>
                <p class="mt-2 text-sm text-slate-500">{{ $lot->product?->name }} en {{ $lot->warehouse?->name }}.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @can('update', $lot)
                    <a href="{{ route('inventory.edit', $lot) }}" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Editar datos</a>
                @endcan
                @can('adjust', $lot)
                    <a href="{{ route('inventory.adjust.create', $lot) }}" class="inline-flex items-center justify-center border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-800 hover:bg-amber-100">Ajustar stock</a>
                @endcan
                @can('trace.print')
                    <a href="{{ route('trace.labels.lots', ['lots' => $lot->id]) }}" class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver etiqueta</a>
                @endcan
                @can('failures.create')
                    <a href="{{ route('failures.create', ['inventory_lot_id' => $lot->id]) }}" class="inline-flex items-center justify-center border border-rose-300 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-800 hover:bg-rose-100">Reportar falla</a>
                @endcan
            </div>
        </div>

        @can('alerts.view')
            @include('alerts._related', ['slaAlerts' => $slaAlerts])
        @endcan

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen del lote">
            @foreach ([
                ['label' => 'Stock total', 'value' => $lot->quantity, 'dot' => 'bg-sky-500'],
                ['label' => 'Reservado activo', 'value' => $reservedActive, 'dot' => 'bg-amber-500'],
                ['label' => 'Disponible neto', 'value' => $availableNet, 'dot' => 'bg-emerald-500'],
                ['label' => 'Estado', 'value' => $statusLabel, 'dot' => $lot->eligible_flag ? 'bg-emerald-500' : 'bg-rose-500'],
            ] as $metric)
                <article class="border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-500">{{ $metric['label'] }}</p>
                        <span class="h-2.5 w-2.5 rounded-full {{ $metric['dot'] }}" aria-hidden="true"></span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold tracking-tight text-slate-950">{{ $metric['value'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-950">Datos del producto y lote</h2>
                </div>
                <dl class="grid gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Codigo</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->product?->product_code }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Clasificacion</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->product?->classification ?? 'Sin clasificar' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lote</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->lot ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Serie</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->serial ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vencimiento</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->expiry?->format('d/m/Y') ?? 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vencimiento requerido</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->product?->expiry_required ? 'Si' : 'No' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Registro sanitario</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->product?->regulatory_record ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vencimiento regsan</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->product?->regulatory_expiry?->format('d/m/Y') ?? 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Almacen</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->warehouse?->name }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ubicacion</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->location ?: 'Sin ubicacion' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Codigo trazable</dt><dd class="mt-1 break-all font-mono text-sm text-slate-900">{{ $lot->trace_code ?: 'Pendiente de generar' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Motivo de bloqueo</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->block_reason ?: 'Sin bloqueo registrado' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado tecnico</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->failures->first()?->status ? str_replace('_', ' ', ucfirst($lot->failures->first()->status)) : 'Sin falla abierta' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ultima falla</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->failures->first()?->created_at?->format('d/m/Y H:i') ?? 'Sin reportes' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bloqueado por falla</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->status?->value === 'falla_preventiva' ? 'Si, requiere liberacion tecnica' : 'No' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observaciones</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $lot->observations ?: 'Sin observaciones' }}</dd></div>
                </dl>
            </div>

            <div class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-950">Stock del producto por almacen</h2>
                    <p class="mt-1 text-sm text-slate-500">El disponible neto descuenta reservas activas.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-5 py-3 font-semibold">Almacen</th><th class="px-5 py-3 font-semibold">Total</th><th class="px-5 py-3 font-semibold">Reservado</th><th class="px-5 py-3 font-semibold">Neto</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($productLots as $productLot)
                                <tr>
                                    <td class="px-5 py-3 text-slate-700">{{ $productLot->warehouse?->name }}<p class="text-xs text-slate-500">{{ $productLot->lot ?: 'Sin lote' }}</p></td>
                                    <td class="px-5 py-3 text-slate-700">{{ $productLot->quantity }}</td>
                                    <td class="px-5 py-3 text-slate-700">{{ $productLot->reserved_active ?? 0 }}</td>
                                    <td class="px-5 py-3 font-semibold text-slate-900">{{ $productLot->available_net }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if ($negativeIssues->isNotEmpty())
            <section class="border border-orange-200 bg-orange-50 shadow-sm">
                <div class="border-b border-orange-200 px-5 py-4">
                    <h2 class="font-semibold text-orange-900">Inconsistencias de importacion</h2>
                    <p class="mt-1 text-sm text-orange-800">Estas cantidades negativas no forman parte del stock disponible.</p>
                </div>
                <div class="divide-y divide-orange-100">
                    @foreach ($negativeIssues as $issue)
                        <div class="flex flex-col justify-between gap-2 px-5 py-4 sm:flex-row sm:items-center">
                            <div>
                                <p class="text-sm font-semibold text-orange-900">{{ $issue->warehouse_name }}</p>
                                <p class="mt-1 text-xs text-orange-800">{{ $issue->message }}</p>
                                <p class="mt-1 text-xs text-orange-700">Importacion: {{ $issue->catalogImport?->original_filename }}</p>
                            </div>
                            <span class="text-sm font-semibold text-orange-900">{{ $issue->quantity }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Reservas y casos vinculados</h2>
            </div>
            @if ($lot->reservations->isEmpty())
                <p class="px-5 py-8 text-sm text-slate-500">Este lote no tiene reservas registradas.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-5 py-3 font-semibold">Solicitud</th><th class="px-5 py-3 font-semibold">Cantidad</th><th class="px-5 py-3 font-semibold">Estado</th><th class="px-5 py-3 font-semibold">Responsable</th><th class="px-5 py-3 font-semibold">Fecha</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($lot->reservations as $reservation)
                                <tr>
                                    <td class="px-5 py-3"><a href="{{ $reservation->case ? route('cases.show', $reservation->case) : '#' }}" class="font-semibold text-sky-700">{{ $reservation->case?->case_code ?: 'Caso no disponible' }}</a><p class="text-xs text-slate-500">{{ $reservation->case?->institution?->name }}</p></td>
                                    <td class="px-5 py-3 text-slate-700">{{ $reservation->quantity }}</td>
                                    <td class="px-5 py-3 text-slate-700">{{ ucfirst($reservation->status) }}</td>
                                    <td class="px-5 py-3 text-slate-700">{{ $reservation->reservedBy?->name ?: 'Usuario no disponible' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-700">{{ $reservation->created_at?->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Ajustes de inventario</h2>
            </div>
            @if ($lot->adjustments->isEmpty())
                <p class="px-5 py-8 text-sm text-slate-500">No hay ajustes registrados.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-5 py-3 font-semibold">Tipo</th><th class="px-5 py-3 font-semibold">Cantidad</th><th class="px-5 py-3 font-semibold">Motivo</th><th class="px-5 py-3 font-semibold">Responsable</th><th class="px-5 py-3 font-semibold">Fecha</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($lot->adjustments as $adjustment)
                                <tr><td class="px-5 py-3 text-slate-700">{{ str_replace('_', ' ', ucfirst($adjustment->adjustment_type)) }}</td><td class="px-5 py-3 font-semibold {{ $adjustment->quantity_adjustment < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $adjustment->quantity_adjustment > 0 ? '+' : '' }}{{ $adjustment->quantity_adjustment }}</td><td class="max-w-sm px-5 py-3 text-slate-700">{{ $adjustment->reason }}</td><td class="px-5 py-3 text-slate-700">{{ $adjustment->responsibleUser?->name ?: 'Usuario no disponible' }}</td><td class="whitespace-nowrap px-5 py-3 text-slate-700">{{ $adjustment->adjusted_at?->format('d/m/Y H:i') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col justify-between gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="font-semibold text-slate-950">Fallas tecnicas asociadas</h2>
                    <p class="mt-1 text-sm text-slate-500">Estado tecnico y trazabilidad del lote.</p>
                </div>
                @if ($lot->failures->isNotEmpty())
                    <span class="text-sm font-semibold {{ $lot->status?->value === 'falla_preventiva' ? 'text-rose-700' : 'text-slate-600' }}">{{ $lot->failures->count() }} reporte(s)</span>
                @endif
            </div>
            @if ($lot->failures->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-500">No hay fallas reportadas para este lote.</p>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($lot->failures as $failure)
                        <div class="flex flex-col justify-between gap-3 px-5 py-4 sm:flex-row sm:items-center">
                            <div><p class="text-sm font-semibold text-slate-900">{{ str_replace('_', ' ', ucfirst($failure->failure_type ?: 'falla tecnica')) }} / {{ ucfirst($failure->severity) }}</p><p class="mt-1 text-xs text-slate-500">{{ $failure->created_at?->format('d/m/Y H:i') }} · {{ $failure->case?->case_code ?: 'Registro manual' }} · {{ $failure->reportedBy?->name ?: 'Usuario no disponible' }}</p></div>
                            <a href="{{ route('failures.show', $failure) }}" class="font-semibold text-sky-700 hover:text-sky-900">Ver reporte</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        @can('returns.view')
        <section class="border border-slate-200 bg-white shadow-sm">

            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="font-semibold text-slate-950">Devoluciones postoperatorias</h2>
                    <p class="mt-1 text-sm text-slate-500">Inspecciones y decisiones sobre el retorno de este lote.</p>
                </div>
                @if ($lot->returns->isNotEmpty())
                    <span class="text-sm font-semibold text-slate-600">{{ $lot->returns->count() }} registro(s)</span>
                @endif
            </div>
            @if ($lot->returns->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-500">No hay devoluciones asociadas a este lote.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-5 py-3 font-semibold">Caso</th><th class="px-5 py-3 font-semibold">Cantidad</th><th class="px-5 py-3 font-semibold">Estado</th><th class="px-5 py-3 font-semibold">Ultima inspeccion</th><th class="px-5 py-3 font-semibold">Responsable</th><th class="px-5 py-3 font-semibold"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($lot->returns as $caseReturn)
                                <tr>
                                    <td class="px-5 py-3"><p class="font-semibold text-slate-900">{{ $caseReturn->case?->case_code ?: 'Caso no disponible' }}</p><p class="mt-1 text-xs text-slate-500">{{ $caseReturn->case?->institution?->name }}</p></td>
                                    <td class="px-5 py-3 text-slate-700">{{ $caseReturn->returned_qty }}</td>
                                    <td class="px-5 py-3 text-slate-700">{{ str_replace('_', ' ', ucfirst($caseReturn->condition)) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-700">{{ $caseReturn->inspection_date?->format('d/m/Y H:i') ?: 'Pendiente' }}</td>
                                    <td class="px-5 py-3 text-slate-700">{{ $caseReturn->inspectionResponsible?->name ?: $caseReturn->inspectedBy?->name ?: 'Pendiente' }}</td>
                                    <td class="px-5 py-3"><a href="{{ route('returns.show', $caseReturn) }}" class="font-semibold text-sky-700 hover:text-sky-900">Ver devolucion</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        @endcan

        @can('documents.view')
        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center"><div><h2 class="font-semibold text-slate-950">Documentos y evidencias del lote</h2><p class="mt-1 text-sm text-slate-500">Guias, recepcion, fallas, inspecciones y respaldos de inventario.</p></div><a href="{{ route('documents.index', ['documentable_type' => 'inventory_lot']) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ir al repositorio</a></div>
            @include('documents._list', ['documents' => $lot->documents])
        </section>
        @endcan

        @if ($canAudit)
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-950">Historial de auditoria</h2>
                </div>
                @if ($auditLogs->isEmpty())
                    <p class="px-5 py-8 text-sm text-slate-500">No hay eventos de auditoria para este lote.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($auditLogs as $audit)
                            <div class="px-5 py-4">
                                <div class="flex flex-col justify-between gap-2 sm:flex-row">
                                    <p class="text-sm font-semibold text-slate-900">{{ $audit->action }}</p>
                                    <p class="text-xs text-slate-500">{{ $audit->created_at?->format('d/m/Y H:i:s') }}</p>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">Por {{ $audit->user?->name ?: 'Sistema' }}</p>
                                <div class="mt-3 grid gap-3 text-xs lg:grid-cols-2">
                                    <pre class="overflow-x-auto bg-slate-50 p-3 text-slate-600">{{ json_encode($audit->before ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    <pre class="overflow-x-auto bg-slate-50 p-3 text-slate-600">{{ json_encode($audit->after ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
