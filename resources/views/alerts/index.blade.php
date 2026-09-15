@extends('layouts.ops')

@section('title', 'Alertas SLA | OPS BIOMED MR8')

@section('content')
    @php
        $priorityClasses = [
            'critical' => 'ops-badge-danger',
            'high' => 'ops-badge-warning',
            'medium' => 'ops-badge-info',
            'low' => 'ops-badge-neutral',
        ];
        $statusClasses = [
            'open' => 'ops-badge-danger',
            'acknowledged' => 'ops-badge-warning',
            'resolved' => 'ops-badge-success',
            'dismissed' => 'ops-badge-neutral',
            'expired' => 'ops-badge-danger',
        ];
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="ops-eyebrow text-sm font-semibold uppercase tracking-[0.14em]">Control de cumplimiento operativo</p>
                <h1 class="ops-section-title mt-2 text-2xl font-semibold">Alertas SLA</h1>
                <p class="ops-muted mt-2 text-sm">Seguimiento de tiempos límite, responsables y acciones humanas pendientes.</p>
            </div>
            <span class="ops-badge-info">Apoyo operativo, no decisión automática</span>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6" aria-label="Resumen de alertas">
            @foreach ([
                ['label' => 'Abiertas', 'value' => $counters['open'], 'class' => 'border-sky-200 bg-sky-50 text-sky-900'],
                ['label' => 'Críticas', 'value' => $counters['critical'], 'class' => 'border-rose-200 bg-rose-50 text-rose-900'],
                ['label' => 'Vencidas', 'value' => $counters['expired'], 'class' => 'border-orange-200 bg-orange-50 text-orange-900'],
                ['label' => 'Próximas 24 h', 'value' => $counters['due_soon'], 'class' => 'border-amber-200 bg-amber-50 text-amber-900'],
                ['label' => 'Reconocidas', 'value' => $counters['acknowledged'], 'class' => 'border-violet-200 bg-violet-50 text-violet-900'],
                ['label' => 'Resueltas', 'value' => $counters['resolved'], 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-900'],
            ] as $metric)
                <article class="border p-4 {{ $metric['class'] }}">
                    <p class="text-sm font-medium">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ number_format((int) $metric['value']) }}</p>
                </article>
            @endforeach
        </section>

        <section class="ops-card p-5" aria-labelledby="alert-filters-title">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 id="alert-filters-title" class="font-semibold text-slate-950">Filtros</h2>
                    <p class="ops-muted mt-1 text-sm">Prioriza las alertas que requieren intervención.</p>
                </div>
                <a href="{{ route('alerts.index') }}" class="text-sm font-semibold text-cyan-800 hover:text-cyan-950">Limpiar</a>
            </div>
            <form method="GET" action="{{ route('alerts.index') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <label class="text-sm font-medium text-slate-700">Módulo
                    <select name="module" class="mt-1.5 block w-full border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Prioridad
                    <select name="priority" class="mt-1.5 block w-full border-slate-300 text-sm">
                        <option value="">Todas</option>
                        @foreach ($priorityLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Estado
                    <select name="status" class="mt-1.5 block w-full border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Responsable
                    <select name="responsible_user_id" class="mt-1.5 block w-full border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($responsibleUsers as $responsibleUser)
                            <option value="{{ $responsibleUser->id }}" @selected((string) ($filters['responsible_user_id'] ?? '') === (string) $responsibleUser->id)>{{ $responsibleUser->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Rol responsable
                    <select name="responsible_role" class="mt-1.5 block w-full border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($responsibleRoles as $role)
                            <option value="{{ $role }}" @selected(($filters['responsible_role'] ?? '') === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Desde
                    <input name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="mt-1.5 block w-full border-slate-300 text-sm" />
                </label>
                <div class="flex items-end"><button type="submit" class="ops-button-primary w-full px-4 py-2.5 text-sm font-semibold">Aplicar</button></div>
                <label class="text-sm font-medium text-slate-700 lg:col-start-5 xl:col-start-5">Hasta
                    <input name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="mt-1.5 block w-full border-slate-300 text-sm" />
                </label>
            </form>
        </section>

        <section class="ops-card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Alertas registradas</h2>
                <p class="ops-muted mt-1 text-sm">{{ $alerts->total() }} resultado(s), ordenados por prioridad y detección.</p>
            </div>
            @if ($alerts->isEmpty())
                <div class="px-5 py-12 text-center">
                    <h3 class="font-semibold text-slate-950">No hay alertas para los filtros seleccionados.</h3>
                    <p class="ops-muted mt-2 text-sm">Ejecuta la evaluación SLA o revisa los criterios de búsqueda.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Alerta</th>
                                <th class="px-5 py-3 font-semibold">Módulo</th>
                                <th class="px-5 py-3 font-semibold">Prioridad</th>
                                <th class="px-5 py-3 font-semibold">Estado</th>
                                <th class="px-5 py-3 font-semibold">Vencimiento</th>
                                <th class="px-5 py-3 font-semibold">Responsable</th>
                                <th class="px-5 py-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($alerts as $alert)
                                <tr class="align-top hover:bg-slate-50">
                                    <td class="px-5 py-4">
                                        <a href="{{ route('alerts.show', $alert) }}" class="font-semibold text-cyan-800 hover:text-cyan-950">{{ $alert->title }}</a>
                                        <p class="ops-muted mt-1 max-w-md text-xs">{{ $alert->description }}</p>
                                        <p class="mt-2 font-mono text-[11px] text-slate-400">{{ $alert->alert_code }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">{{ $alert->module }}</td>
                                    <td class="px-5 py-4"><span class="{{ $priorityClasses[$alert->priority] ?? 'ops-badge-neutral' }}">{{ $priorityLabels[$alert->priority] ?? ucfirst($alert->priority) }}</span></td>
                                    <td class="px-5 py-4"><span class="{{ $statusClasses[$alert->status] ?? 'ops-badge-neutral' }}">{{ $statusLabels[$alert->status] ?? ucfirst($alert->status) }}</span></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">
                                        {{ $alert->due_at?->format('d/m/Y H:i') ?: 'Sin vencimiento' }}
                                        @if ($alert->isOverdue())
                                            <span class="mt-1 block text-xs font-semibold text-rose-700">Tiempo vencido</span>
                                        @elseif ($alert->isDueSoon())
                                            <span class="mt-1 block text-xs font-semibold text-amber-700">Próxima a vencer</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">{{ $alert->responsibleUser?->name ?: $alert->responsible_role ?: 'Sin asignar' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right"><a href="{{ route('alerts.show', $alert) }}" class="font-semibold text-cyan-800 hover:text-cyan-950">Ver detalle</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-4">{{ $alerts->links() }}</div>
            @endif
        </section>
    </div>
@endsection
