@extends('layouts.ops')

@section('title', 'Falla tecnica #'.$failure->id.' | OPS BIOMED MR8')

@section('content')
    @php
        $status = $failure->status;
        $statusClass = match ($status) {
            'bloqueada' => 'bg-rose-50 text-rose-700',
            'reportada', 'pendiente_repuesto' => 'bg-amber-50 text-amber-700',
            'en_revision' => 'bg-sky-50 text-sky-700',
            'liberada' => 'bg-emerald-50 text-emerald-700',
            'dada_de_baja', 'cerrada' => 'bg-slate-100 text-slate-600',
            default => 'bg-rose-50 text-rose-700',
        };
        $isTerminal = in_array($status, ['liberada', 'dada_de_baja', 'cerrada'], true);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('failures.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver a fallas</a>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Reporte de falla #{{ $failure->id }}</h1>
                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $labels['statuses'][$status] ?? ucfirst($status) }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-500">Registrada el {{ $failure->created_at?->format('d/m/Y H:i') }} por {{ $failure->reportedBy?->name ?: 'Usuario no disponible' }}.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @can('update', $failure)
                    @unless ($isTerminal)
                        <a href="{{ route('failures.edit', $failure) }}" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Actualizar seguimiento</a>
                    @endunless
                @endcan
                @if ($failure->inventoryLot)
                    <a href="{{ route('inventory.show', $failure->inventoryLot) }}" class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver lote</a>
                @endif
                @if ($failure->case)
                    <a href="{{ route('cases.show', $failure->case) }}" class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver caso</a>
                @endif
            </div>
        </div>

        @can('alerts.view')
            @include('alerts._related', ['slaAlerts' => $slaAlerts])
        @endcan

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['label' => 'Producto', 'value' => $failure->product?->product_code ?? $failure->inventoryLot?->product?->product_code ?? 'No especificado', 'dot' => 'bg-sky-500'],
                ['label' => 'Lote / serie', 'value' => ($failure->inventoryLot?->lot ?: 'Sin lote').($failure->inventoryLot?->serial ? ' / '.$failure->inventoryLot->serial : ''), 'dot' => 'bg-amber-500'],
                ['label' => 'Severidad', 'value' => $labels['severities'][$failure->severity] ?? ucfirst($failure->severity), 'dot' => in_array($failure->severity, ['alta', 'critica'], true) ? 'bg-rose-500' : 'bg-slate-400'],
                ['label' => 'Bloqueo preventivo', 'value' => $failure->preventive_block ? 'Activo' : 'No activo', 'dot' => $failure->preventive_block ? 'bg-rose-500' : 'bg-emerald-500'],
            ] as $metric)
                <article class="border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between gap-3"><p class="text-sm font-medium text-slate-500">{{ $metric['label'] }}</p><span class="h-2.5 w-2.5 rounded-full {{ $metric['dot'] }}"></span></div><p class="mt-4 text-lg font-semibold text-slate-950">{{ $metric['value'] }}</p></article>
            @endforeach
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Datos del reporte</h2></div>
                <dl class="grid gap-x-6 gap-y-5 p-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo de falla</dt><dd class="mt-1 text-sm text-slate-900">{{ $labels['types'][$failure->failure_type] ?? str_replace('_', ' ', ucfirst($failure->failure_type)) }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Momento</dt><dd class="mt-1 text-sm text-slate-900">{{ $labels['moments'][$failure->occurrence_moment] ?? str_replace('_', ' ', ucfirst($failure->occurrence_moment)) }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Almacen</dt><dd class="mt-1 text-sm text-slate-900">{{ $failure->inventoryLot?->warehouse?->name ?: 'No aplica' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Caso</dt><dd class="mt-1 text-sm text-slate-900">{{ $failure->case?->case_code ?: 'Registro manual' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable tecnico</dt><dd class="mt-1 text-sm text-slate-900">{{ $failure->responsibleTechnical?->name ?: 'Sin asignar' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Proveedor / reposicion</dt><dd class="mt-1 text-sm text-slate-900">{{ $failure->requires_supplier ? 'Proveedor' : 'Sin proveedor' }} / {{ $failure->requires_replacement ? 'Requiere reposicion' : 'Sin reposicion marcada' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descripcion</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $failure->description }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Accion inmediata</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $failure->action_taken ?: 'Sin accion registrada' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Evidencia / referencia</dt><dd class="mt-1 break-words text-sm text-slate-900">{{ $failure->evidence_reference ?: 'Sin evidencia adjunta' }}</dd></div>
                </dl>
            </div>
            <div class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Revision tecnica</h2></div>
                <dl class="space-y-5 p-5">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Diagnostico</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $failure->diagnosis ?: 'Pendiente de diagnostico' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Causa probable</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $failure->probable_cause ?: 'Pendiente de causa probable' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Accion correctiva</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $failure->corrective_action ?: 'Pendiente de accion correctiva' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ultima revision</dt><dd class="mt-1 text-sm text-slate-900">{{ $failure->reviewed_at?->format('d/m/Y H:i') ?: 'Pendiente' }}{{ $failure->reviewedBy ? ' / '.$failure->reviewedBy->name : '' }}</dd></div>
                    @if ($failure->released_at)<div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Liberacion</dt><dd class="mt-1 text-sm text-slate-900">{{ $failure->released_at->format('d/m/Y H:i') }} / {{ $failure->releasedBy?->name ?: 'Usuario no disponible' }}</dd></div>@endif
                    @if ($failure->retired_at)<div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Baja</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $failure->retired_at->format('d/m/Y H:i') }} / {{ $failure->retirement_reason }}</dd></div>@endif
                </dl>
            </div>
        </section>

        @can('release', $failure)
            @if (! in_array($status, ['liberada', 'dada_de_baja', 'cerrada'], true))
                <section class="border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
                    <h2 class="font-semibold text-emerald-950">Liberacion tecnica</h2>
                    <p class="mt-1 text-sm text-emerald-800">La liberacion requiere diagnostico, accion correctiva y evidencia tecnica. Un lote vencido no puede liberarse.</p>
                    <form method="POST" action="{{ route('failures.release', $failure) }}" class="mt-5 grid gap-4 md:grid-cols-3">
                        @csrf
                        <div><label for="release_diagnosis" class="block text-sm font-medium text-slate-700">Diagnostico final</label><textarea id="release_diagnosis" name="diagnosis" rows="3" required class="mt-1.5 block w-full border-slate-300 text-sm"></textarea></div>
                        <div><label for="release_action" class="block text-sm font-medium text-slate-700">Accion correctiva</label><textarea id="release_action" name="corrective_action" rows="3" required class="mt-1.5 block w-full border-slate-300 text-sm"></textarea></div>
                        <div><label for="release_notes" class="block text-sm font-medium text-slate-700">Evidencia / comentario tecnico</label><textarea id="release_notes" name="release_notes" rows="3" required class="mt-1.5 block w-full border-slate-300 text-sm"></textarea></div>
                        <div class="md:col-span-3"><button type="submit" class="inline-flex items-center justify-center bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Liberar falla</button></div>
                    </form>
                </section>
            @endif
        @endcan

        @can('retire', $failure)
            @if (! in_array($status, ['dada_de_baja', 'cerrada'], true))
                <section class="border border-rose-200 bg-rose-50/60 p-5 shadow-sm">
                    <h2 class="font-semibold text-rose-950">Dar de baja</h2>
                    <form method="POST" action="{{ route('failures.retire', $failure) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                        @csrf
                        <div><label for="retirement_reason" class="block text-sm font-medium text-slate-700">Motivo</label><textarea id="retirement_reason" name="retirement_reason" rows="3" required class="mt-1.5 block w-full border-slate-300 text-sm"></textarea></div>
                        <div><label for="retirement_evidence" class="block text-sm font-medium text-slate-700">Evidencia</label><textarea id="retirement_evidence" name="retirement_evidence" rows="3" required class="mt-1.5 block w-full border-slate-300 text-sm"></textarea></div>
                        <div class="md:col-span-2"><button type="submit" class="inline-flex items-center justify-center bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-800">Dar de baja lote</button></div>
                    </form>
                </section>
            @endif
        @endcan

        @can('close', $failure)
            @if (in_array($status, ['liberada', 'dada_de_baja'], true))
                <section class="border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-semibold text-slate-950">Cerrar reporte</h2>
                    <form method="POST" action="{{ route('failures.close', $failure) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                        @csrf
                        <div class="flex-1"><label for="close_action" class="block text-sm font-medium text-slate-700">Comentario de cierre</label><input id="close_action" name="action_taken" type="text" maxlength="2000" class="mt-1.5 block w-full border-slate-300 text-sm"></div>
                        <button type="submit" class="inline-flex items-center justify-center bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Cerrar reporte</button>
                    </form>
                </section>
            @endif
        @endcan

        @can('documents.view')
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center"><div><h2 class="font-semibold text-slate-950">Documentos y evidencias de falla</h2><p class="mt-1 text-sm text-slate-500">Reporte técnico, fotografías y evidencia de liberación o baja.</p></div><a href="{{ route('documents.index', ['documentable_type' => 'failure']) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ir al repositorio</a></div>
                @include('documents._list', ['documents' => $failure->documents])
            </section>
        @endcan

        @if ($canAudit)
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Historial de auditoria</h2></div>
                @if ($auditLogs->isEmpty())
                    <p class="px-5 py-8 text-sm text-slate-500">No hay eventos de auditoria para esta falla.</p>
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
