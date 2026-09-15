@extends('layouts.ops')

@section('title', 'Detalle de devolucion | OPS BIOMED MR8')

@section('content')
    @php
        $conditionLabels = [
            'pendiente_inspeccion' => 'Pendiente de inspeccion',
            'inspeccionado' => 'Inspeccionado',
            'liberado' => 'Liberado',
            'cuarentena' => 'Cuarentena',
            'bloqueado' => 'Bloqueado',
            'desvalorizado' => 'Desvalorizado',
            'dado_de_baja' => 'Dado de baja',
        ];
        $conditionClass = match ($return->condition) {
            'liberado' => 'bg-emerald-50 text-emerald-700',
            'cuarentena' => 'bg-amber-50 text-amber-700',
            'bloqueado' => 'bg-rose-50 text-rose-700',
            'desvalorizado', 'dado_de_baja' => 'bg-orange-50 text-orange-700',
            default => 'bg-sky-50 text-sky-700',
        };
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('returns.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver a devoluciones</a>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Devolucion #{{ $return->id }}</h1>
                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold {{ $conditionClass }}">{{ $conditionLabels[$return->condition] ?? ucfirst(str_replace('_', ' ', $return->condition)) }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-500">Registrada el {{ $return->created_at?->format('d/m/Y H:i') }}.</p>
            </div>
            @if ($return->condition === 'pendiente_inspeccion')
                @can('inspect', $return)
                    <a href="{{ route('returns.inspect.create', $return) }}" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Inspeccionar devolucion</a>
                @endcan
            @endif
        </div>

        @can('alerts.view')
            @include('alerts._related', ['slaAlerts' => $slaAlerts])
        @endcan

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Datos de la cirugia</h2></div>
                <dl class="grid gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Caso</dt><dd class="mt-1 text-sm text-slate-900"><a href="{{ $return->case ? route('cases.show', $return->case) : '#' }}" class="font-semibold text-sky-700">{{ $return->case?->case_code ?: 'No disponible' }}</a></dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Institucion</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->case?->institution?->name ?: 'No disponible' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Medico</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->case?->doctor?->name ?: 'No disponible' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Paciente</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->case?->patient?->full_name ?: 'No disponible' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha de cirugia</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->case?->scheduled_at?->format('d/m/Y H:i') ?: 'No disponible' }}</dd></div>
                </dl>
            </div>
            <div class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Producto y lote</h2></div>
                <dl class="grid gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Producto</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->inventoryLot?->product?->name ?: 'No disponible' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Codigo</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->inventoryLot?->product?->product_code ?: 'No disponible' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lote</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->inventoryLot?->lot ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Serie</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->inventoryLot?->serial ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vencimiento</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->inventoryLot?->expiry?->format('d/m/Y') ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Almacen</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->inventoryLot?->warehouse?->name ?: 'No disponible' }}</dd></div>
                </dl>
            </div>
        </section>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Conciliacion de cantidades</h2></div>
            <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ([
                    ['label' => 'Cantidad reservada', 'value' => $materialUsed?->reserved_qty ?? 'N/A'],
                    ['label' => 'Cantidad usada', 'value' => $materialUsed?->used_qty ?? 'N/A'],
                    ['label' => 'Cantidad devuelta', 'value' => $return->returned_qty],
                    ['label' => 'Abierta no usada', 'value' => $materialUsed?->unused_opened_qty ?? 'N/A'],
                    ['label' => 'Falla', 'value' => $materialUsed?->failure_qty ?? 'N/A'],
                ] as $quantity)
                    <div class="border border-slate-200 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $quantity['label'] }}</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ $quantity['value'] }}</p></div>
                @endforeach
            </div>
            @if ($materialUsed?->difference_qty > 0)
                <div class="mx-5 mb-5 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Diferencia registrada: {{ $materialUsed->difference_qty }}. {{ $materialUsed->difference_reason }}</div>
            @endif
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Resultado de inspeccion</h2></div>
                <dl class="grid gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resultado</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $inspectionResults[$return->inspection_result] ?? 'Pendiente' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha inspeccion</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->inspection_date?->format('d/m/Y H:i') ?: 'Pendiente' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->inspectionResponsible?->name ?: $return->inspectedBy?->name ?: 'Pendiente' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observaciones</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $return->inspection_observations ?: 'Sin observaciones' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Evidencia</dt><dd class="mt-1 break-words text-sm text-slate-900">{{ $return->inspection_evidence_reference ?: 'Sin evidencia adicional' }}</dd></div>
                </dl>
            </div>
            <div class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Evidencia y fallas asociadas</h2></div>
                <div class="space-y-4 p-5 text-sm">
                    <div><p class="font-semibold text-slate-700">Evidencia de consumo/cierre</p><p class="mt-1 text-slate-600">{{ $materialUsed?->evidence_description ?: 'Sin descripcion registrada' }}</p>@if ($materialUsed?->evidence_reference)<p class="mt-1 break-words text-xs text-slate-500">Referencia: {{ $materialUsed->evidence_reference }}</p>@endif</div>
                    @if ($return->technicalFailure)
                        <div class="border border-rose-200 bg-rose-50 p-4"><p class="font-semibold text-rose-800">Falla tecnica vinculada</p><p class="mt-1 text-rose-700">{{ $return->technicalFailure->description }}</p><a href="{{ route('failures.show', $return->technicalFailure) }}" class="mt-2 inline-block font-semibold text-rose-800 hover:text-rose-900">Ver reporte de falla</a></div>
                    @else
                        <p class="text-slate-500">No hay falla tecnica creada desde esta inspeccion.</p>
                    @endif
                </div>
            </div>
        </section>

        @can('documents.view')
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center"><div><h2 class="font-semibold text-slate-950">Documentos de devolucion</h2><p class="mt-1 text-sm text-slate-500">Evidencia de retorno, inspeccion y trazabilidad del lote.</p></div><a href="{{ route('documents.index', ['documentable_type' => 'return']) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ir al repositorio</a></div>
                @include('documents._list', ['documents' => $return->documents])
            </section>
        @endcan

        @if ($canAudit)
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Historial de auditoria</h2></div>
                @if ($auditLogs->isEmpty())
                    <p class="px-5 py-8 text-sm text-slate-500">No hay eventos de auditoria para esta devolucion.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($auditLogs as $audit)
                            <div class="px-5 py-4"><div class="flex flex-col justify-between gap-2 sm:flex-row"><p class="text-sm font-semibold text-slate-900">{{ $audit->action }}</p><p class="text-xs text-slate-500">{{ $audit->created_at?->format('d/m/Y H:i:s') }}</p></div><p class="mt-1 text-xs text-slate-500">Por {{ $audit->user?->name ?: 'Sistema' }}</p></div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
