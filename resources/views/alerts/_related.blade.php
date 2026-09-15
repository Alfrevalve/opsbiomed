@if ($slaAlerts->isNotEmpty())
    @php
        $statusLabels = [
            'open' => 'Abierta',
            'acknowledged' => 'Reconocida',
            'expired' => 'Vencida',
        ];
        $priorityClasses = [
            'critical' => 'ops-badge-danger',
            'high' => 'ops-badge-warning',
            'medium' => 'ops-badge-info',
            'low' => 'ops-badge-neutral',
        ];
    @endphp

    <section class="ops-card" aria-labelledby="related-sla-alerts-heading">
        <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
            <div>
                <p class="ops-eyebrow">Seguimiento de tiempos</p>
                <h2 id="related-sla-alerts-heading" class="ops-section-title mt-1 text-lg font-semibold">Alertas SLA relacionadas</h2>
                <p class="ops-muted mt-1 text-sm">Requieren seguimiento humano según el estado actual de la operación.</p>
            </div>
            <a href="{{ route('alerts.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ver centro de alertas</a>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach ($slaAlerts as $slaAlert)
                <a href="{{ route('alerts.show', $slaAlert) }}" class="flex flex-col gap-2 px-5 py-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $slaAlert->title }}</p>
                        <p class="ops-muted mt-1 text-xs">{{ $slaAlert->description }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="{{ $priorityClasses[$slaAlert->priority] ?? 'ops-badge-neutral' }}">{{ ucfirst($slaAlert->priority) }}</span>
                        <span class="ops-badge-neutral">{{ $statusLabels[$slaAlert->status] ?? ucfirst($slaAlert->status) }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
