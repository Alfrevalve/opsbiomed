@extends('layouts.ops')

@section('title', 'Agenda quirurgica | OPS BIOMED MR8')

@section('content')
    @php
        $reservationBadgeClasses = [
            true => 'ops-badge-success',
            false => 'ops-badge-warning',
            null => 'ops-badge-neutral',
        ];
        $riskBadgeClasses = [
            'red' => 'ops-badge-danger',
            'yellow' => 'ops-badge-warning',
            'green' => 'ops-badge-success',
            'gray' => 'ops-badge-neutral',
        ];
        $resourceTypeLabels = \App\Services\Operations\SurgeryScheduleService::resourceTypeLabels();
        $groups = $cases->groupBy(fn ($case): string => $case->scheduled_at?->format('Y-m-d') ?? 'Sin fecha');
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="ops-eyebrow">Operacion quirurgica</p>
                <h1 class="ops-section-title mt-1 text-2xl font-semibold">Agenda y disponibilidad de recursos</h1>
                <p class="ops-muted mt-2 text-sm">{{ $subtitle }}</p>
            </div>
            <a href="{{ route('schedule.conflicts', ['date' => $selectedDate->toDateString()]) }}" class="ops-button-secondary inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">
                Ver conflictos
            </a>
        </header>

        <section class="flex flex-col gap-3 border border-slate-200 bg-white p-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('schedule.index') }}" @class(['ops-button-primary' => $mode === 'next48', 'ops-button-secondary' => $mode !== 'next48', 'inline-flex items-center justify-center px-3 py-2 text-sm font-semibold'])>Proximas 48 h</a>
                <a href="{{ route('schedule.day', ['date' => $selectedDate->toDateString()]) }}" @class(['ops-button-primary' => $mode === 'day', 'ops-button-secondary' => $mode !== 'day', 'inline-flex items-center justify-center px-3 py-2 text-sm font-semibold'])>Dia</a>
                <a href="{{ route('schedule.week', ['date' => $selectedDate->toDateString()]) }}" @class(['ops-button-primary' => $mode === 'week', 'ops-button-secondary' => $mode !== 'week', 'inline-flex items-center justify-center px-3 py-2 text-sm font-semibold'])>Semana</a>
            </div>

            <form method="GET" action="{{ $mode === 'week' ? route('schedule.week') : route('schedule.day') }}" class="flex items-center gap-2">
                <label for="schedule-date" class="ops-muted text-sm font-medium">Fecha</label>
                <input id="schedule-date" name="date" type="date" value="{{ $selectedDate->toDateString() }}" class="border-slate-300 text-sm text-slate-900 focus:border-cyan-700 focus:ring-cyan-700">
                <button type="submit" class="ops-button-secondary px-3 py-2 text-sm font-semibold">Ir</button>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Resumen de agenda">
            <article class="ops-card p-4">
                <p class="ops-muted text-xs font-semibold uppercase tracking-wide">Cirugias</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['total_cases']) }}</p>
            </article>
            <article class="ops-card border-rose-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Conflictos criticos</p>
                <p class="mt-2 text-2xl font-semibold text-rose-800">{{ number_format($summary['critical_conflicts']) }}</p>
            </article>
            <article class="ops-card border-amber-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Alertas altas</p>
                <p class="mt-2 text-2xl font-semibold text-amber-800">{{ number_format($summary['high_conflicts']) }}</p>
            </article>
            <article class="ops-card border-amber-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Sin instrumentista</p>
                <p class="mt-2 text-2xl font-semibold text-amber-800">{{ number_format($summary['missing_instrumentist']) }}</p>
            </article>
            <article class="ops-card border-amber-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Reserva incompleta</p>
                <p class="mt-2 text-2xl font-semibold text-amber-800">{{ number_format($summary['incomplete_reservations']) }}</p>
            </article>
        </section>

        <section class="ops-card" aria-labelledby="agenda-list-heading">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <p class="ops-eyebrow">Programacion</p>
                    <h2 id="agenda-list-heading" class="ops-section-title mt-1 text-lg font-semibold">{{ $title }}</h2>
                </div>
                <span class="ops-badge-neutral">{{ $cases->count() }} casos</span>
            </div>

            @if ($cases->isEmpty())
                <p class="ops-muted px-5 py-8 text-sm">No hay cirugias programadas para este periodo.</p>
            @else
                <div class="divide-y divide-slate-200">
                    @foreach ($groups as $date => $dateCases)
                        <div class="border-b border-slate-100 bg-slate-50 px-5 py-3">
                            <h3 class="text-sm font-semibold text-slate-800">
                                {{ $date === 'Sin fecha' ? $date : \Carbon\Carbon::parse($date)->translatedFormat('l d \d\e F') }}
                            </h3>
                        </div>

                        <div class="hidden overflow-x-auto lg:block">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-5 py-3">Caso / hora</th>
                                        <th class="px-5 py-3">Atencion</th>
                                        <th class="px-5 py-3">Instrumentista</th>
                                        <th class="px-5 py-3">Recursos</th>
                                        <th class="px-5 py-3">Reserva</th>
                                        <th class="px-5 py-3">Riesgo</th>
                                        <th class="px-5 py-3"><span class="sr-only">Accion</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($dateCases as $case)
                                        @php($context = $contexts->get($case->id))
                                        <tr class="align-top">
                                            <td class="px-5 py-4">
                                                <a href="{{ route('cases.control', $case) }}" class="font-semibold text-sky-700 hover:text-sky-900">{{ $case->case_code }}</a>
                                                <p class="ops-muted mt-1 text-xs">{{ $case->scheduled_at?->format('H:i') ?? '--:--' }} / {{ $case->status->operationalLabel() }}</p>
                                            </td>
                                            <td class="px-5 py-4">
                                                <p class="font-medium text-slate-900">{{ $case->institution?->name ?: 'Institucion no registrada' }}</p>
                                                <p class="ops-muted mt-1 text-xs">{{ $case->doctor?->name ?: 'Medico no registrado' }} / Paciente {{ $context['patient_initials'] ?? '--' }}</p>
                                                <p class="ops-muted mt-1 text-xs">{{ $case->surgeryType?->name ?: 'Tipo por confirmar' }}</p>
                                            </td>
                                            <td class="px-5 py-4">
                                                <p class="font-medium text-slate-900">{{ $case->assignedInstrumentist?->name ?: 'Sin asignar' }}</p>
                                            </td>
                                            <td class="px-5 py-4">
                                                @forelse ($case->resourceAssignments as $assignment)
                                                    <p class="ops-muted text-xs">
                                                        {{ $resourceTypeLabels[$assignment->resource_type] ?? \Illuminate\Support\Str::headline($assignment->resource_type) }}:
                                                        {{ $assignment->inventoryLot?->product?->product_code ?: 'Sin lote' }}
                                                        @if ($assignment->inventoryLot?->serial || $assignment->inventoryLot?->lot)
                                                            / {{ $assignment->inventoryLot?->serial ?: $assignment->inventoryLot?->lot }}
                                                        @endif
                                                    </p>
                                                @empty
                                                    <span class="ops-muted text-xs">Sin recursos asignados</span>
                                                @endforelse
                                            </td>
                                            <td class="px-5 py-4">
                                                <span class="{{ $reservationBadgeClasses[$context['reservation_complete']] ?? 'ops-badge-neutral' }}">{{ $context['reservation_label'] ?? 'Sin datos' }}</span>
                                            </td>
                                            <td class="px-5 py-4">
                                                <span class="{{ $riskBadgeClasses[$context['risk'] ?? 'gray'] }}">{{ $context['risk_label'] ?? 'No aplica' }}</span>
                                                @if ($slaAlertsByCase->get($case->id)?->isNotEmpty())
                                                    <span class="mt-2 block ops-badge-danger">{{ $slaAlertsByCase->get($case->id)->count() }} alerta(s) SLA</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-4 text-right">
                                                <a href="{{ route('cases.control', $case) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Control</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="divide-y divide-slate-100 lg:hidden">
                            @foreach ($dateCases as $case)
                                @php($context = $contexts->get($case->id))
                                <article class="space-y-3 px-5 py-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <a href="{{ route('cases.control', $case) }}" class="font-semibold text-sky-700">{{ $case->case_code }}</a>
                                            <p class="ops-muted mt-1 text-xs">{{ $case->scheduled_at?->format('H:i') ?? '--:--' }} / {{ $case->status->operationalLabel() }}</p>
                                        </div>
                                        <span class="{{ $riskBadgeClasses[$context['risk'] ?? 'gray'] }}">{{ $context['risk_label'] ?? 'No aplica' }}</span>
                                    </div>
                                    <div class="grid gap-2 text-sm">
                                        <p><span class="ops-muted">Atencion:</span> {{ $case->institution?->name ?: 'Institucion no registrada' }}</p>
                                        <p><span class="ops-muted">Medico:</span> {{ $case->doctor?->name ?: 'No registrado' }} / Paciente {{ $context['patient_initials'] ?? '--' }}</p>
                                        <p><span class="ops-muted">Tipo:</span> {{ $case->surgeryType?->name ?: 'Por confirmar' }}</p>
                                        <p><span class="ops-muted">Instrumentista:</span> {{ $case->assignedInstrumentist?->name ?: 'Sin asignar' }}</p>
                                        <p><span class="ops-muted">Recursos:</span> {{ $case->resourceAssignments->count() ? $case->resourceAssignments->count().' asignado(s)' : 'Sin asignar' }}</p>
                                    </div>
                                    <span class="{{ $reservationBadgeClasses[$context['reservation_complete']] ?? 'ops-badge-neutral' }}">{{ $context['reservation_label'] ?? 'Sin datos' }}</span>
                                    @if ($slaAlertsByCase->get($case->id)?->isNotEmpty())
                                        <span class="ops-badge-danger">{{ $slaAlertsByCase->get($case->id)->count() }} alerta(s) SLA</span>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
