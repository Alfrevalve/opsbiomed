@extends('layouts.ops')

@section('title', $alert->title.' | Alertas SLA')

@section('content')
    @php
        $priorityClasses = ['critical' => 'ops-badge-danger', 'high' => 'ops-badge-warning', 'medium' => 'ops-badge-info', 'low' => 'ops-badge-neutral'];
        $statusClasses = ['open' => 'ops-badge-danger', 'acknowledged' => 'ops-badge-warning', 'resolved' => 'ops-badge-success', 'dismissed' => 'ops-badge-neutral', 'expired' => 'ops-badge-danger'];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('alerts.index') }}" class="text-sm font-semibold text-cyan-800 hover:text-cyan-950">← Volver a alertas</a>
            <span class="font-mono text-xs text-slate-400">{{ $alert->alert_code }}</span>
        </div>

        <header class="ops-card p-5 sm:p-6">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <p class="ops-eyebrow">{{ $alert->module }} · {{ $alert->sla?->name }}</p>
                    <h1 class="ops-section-title mt-2 text-2xl font-semibold">{{ $alert->title }}</h1>
                    <p class="ops-muted mt-3 max-w-3xl text-sm leading-6">{{ $alert->description }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="{{ $priorityClasses[$alert->priority] ?? 'ops-badge-neutral' }}">{{ $priorityLabels[$alert->priority] ?? ucfirst($alert->priority) }}</span>
                    <span class="{{ $statusClasses[$alert->status] ?? 'ops-badge-neutral' }}">{{ $statusLabels[$alert->status] ?? ucfirst($alert->status) }}</span>
                </div>
            </div>
            <dl class="mt-6 grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Detectada</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $alert->detected_at?->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Vencimiento</dt><dd class="mt-1 text-sm font-semibold {{ $alert->isOverdue() ? 'text-rose-700' : 'text-slate-900' }}">{{ $alert->due_at?->format('d/m/Y H:i') ?: 'Sin vencimiento' }}</dd></div>
                <div><dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Responsable</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $alert->responsibleUser?->name ?: $alert->responsible_role ?: 'Sin asignar' }}</dd></div>
                <div><dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Entidad</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $alert->metadata['case_code'] ?? class_basename((string) $alert->alertable_type).' #'.$alert->alertable_id }}</dd></div>
            </dl>
        </header>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.7fr)]">
            <div class="space-y-6">
                <section class="ops-card p-5" aria-labelledby="actions-title">
                    <h2 id="actions-title" class="font-semibold text-slate-950">Acción humana</h2>
                    <p class="ops-muted mt-1 text-sm">Reconocer, resolver o descartar no modifica automáticamente el caso ni el inventario.</p>
                    @if ($relatedUrl)
                        <a href="{{ $relatedUrl }}" class="ops-button-secondary mt-4 inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Abrir registro relacionado</a>
                    @endif
                    @if (in_array($alert->status, ['open', 'acknowledged', 'expired'], true))
                        <div class="mt-5 grid gap-4 lg:grid-cols-3">
                            @can('acknowledge', $alert)
                                @if ($alert->status === 'open')
                                    <form method="POST" action="{{ route('alerts.acknowledge', $alert) }}" class="border border-amber-200 bg-amber-50 p-4">
                                        @csrf
                                        <label class="block text-sm font-medium text-amber-950">Nota opcional<input name="note" maxlength="1000" class="mt-2 block w-full border-amber-300 bg-white text-sm" /></label>
                                        <button type="submit" class="mt-3 w-full bg-amber-700 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-800">Reconocer</button>
                                    </form>
                                @endif
                            @endcan
                            @can('resolve', $alert)
                                <form method="POST" action="{{ route('alerts.resolve', $alert) }}" class="border border-emerald-200 bg-emerald-50 p-4">
                                    @csrf
                                    <label class="block text-sm font-medium text-emerald-950">Nota de resolución<input name="note" maxlength="1000" class="mt-2 block w-full border-emerald-300 bg-white text-sm" /></label>
                                    <button type="submit" class="mt-3 w-full bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Resolver</button>
                                </form>
                            @endcan
                            @can('dismiss', $alert)
                                <form method="POST" action="{{ route('alerts.dismiss', $alert) }}" class="border border-slate-200 bg-slate-50 p-4">
                                    @csrf
                                    <label class="block text-sm font-medium text-slate-800">Motivo opcional<input name="note" maxlength="1000" class="mt-2 block w-full border-slate-300 bg-white text-sm" /></label>
                                    <button type="submit" class="mt-3 w-full bg-slate-700 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Descartar</button>
                                </form>
                            @endcan
                        </div>
                    @else
                        <p class="mt-5 text-sm font-semibold text-emerald-800">Esta alerta ya no tiene acciones pendientes.</p>
                    @endif
                </section>

                <section class="ops-card p-5" aria-labelledby="history-title">
                    <h2 id="history-title" class="font-semibold text-slate-950">Historial de cambios</h2>
                    @if ($auditLogs->isEmpty())
                        <p class="ops-muted mt-4 text-sm">No hay movimientos auditados para esta alerta.</p>
                    @else
                        <ol class="mt-4 space-y-4">
                            @foreach ($auditLogs as $audit)
                                <li class="border-l-2 border-cyan-300 pl-4">
                                    <p class="text-sm font-semibold text-slate-900">{{ $audit->action }}</p>
                                    <p class="ops-muted mt-1 text-xs">{{ $audit->user?->name ?: 'Sistema' }} · {{ $audit->created_at?->format('d/m/Y H:i') }}</p>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </div>

            <aside class="space-y-6">
                <section class="ops-card p-5" aria-labelledby="sla-title">
                    <h2 id="sla-title" class="font-semibold text-slate-950">Regla SLA</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="ops-muted">Código</dt><dd class="font-mono text-right text-slate-800">{{ $alert->sla?->code }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="ops-muted">Objetivo</dt><dd class="text-right font-semibold text-slate-800">{{ $alert->sla?->target_minutes }} minutos</dd></div>
                        <div class="flex justify-between gap-4"><dt class="ops-muted">Evento inicial</dt><dd class="text-right text-slate-800">{{ $alert->sla?->trigger_event ?: 'No definido' }}</dd></div>
                    </dl>
                </section>
                <section class="border border-blue-200 bg-blue-50 p-5">
                    <h2 class="font-semibold text-blue-950">Criterio de uso</h2>
                    <p class="mt-3 text-sm leading-6 text-blue-900">La alerta orienta la atención del equipo. La decisión operativa, clínica, técnica o administrativa siempre requiere validación humana.</p>
                </section>
            </aside>
        </div>
    </div>
@endsection
