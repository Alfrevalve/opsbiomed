@extends('layouts.ops')

@section('title', 'Conflictos de agenda | OPS BIOMED MR8')

@section('content')
    @php
        $severityClasses = [
            'critico' => 'ops-badge-danger',
            'alto' => 'ops-badge-warning',
            'medio' => 'ops-badge-info',
            'bajo' => 'ops-badge-neutral',
        ];
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="ops-eyebrow">Agenda quirurgica</p>
                <h1 class="ops-section-title mt-1 text-2xl font-semibold">Conflictos y alertas de recursos</h1>
                <p class="ops-muted mt-2 text-sm">Del {{ $from->format('d/m/Y') }} al {{ $until->format('d/m/Y') }}.</p>
            </div>
            <a href="{{ route('schedule.index') }}" class="ops-button-secondary inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">Volver a agenda</a>
        </header>

        <section class="flex flex-col gap-3 border border-slate-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
            <form method="GET" action="{{ route('schedule.conflicts') }}" class="flex items-center gap-2">
                <label for="conflict-date" class="ops-muted text-sm font-medium">Semana desde</label>
                <input id="conflict-date" name="date" type="date" value="{{ $selectedDate->toDateString() }}" class="border-slate-300 text-sm text-slate-900 focus:border-cyan-700 focus:ring-cyan-700">
                <button type="submit" class="ops-button-secondary px-3 py-2 text-sm font-semibold">Actualizar</button>
            </form>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="ops-badge-danger">{{ $summary['critical_conflicts'] }} criticos</span>
                <span class="ops-badge-warning">{{ $summary['high_conflicts'] }} altos</span>
            </div>
        </section>

        <section class="ops-card" aria-labelledby="conflicts-heading">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div>
                    <p class="ops-eyebrow">Control preventivo</p>
                    <h2 id="conflicts-heading" class="ops-section-title mt-1 text-lg font-semibold">Conflictos detectados</h2>
                </div>
                <span class="ops-badge-neutral">{{ $conflicts->count() }} alertas</span>
            </div>

            @if ($conflicts->isEmpty())
                <p class="ops-muted px-5 py-8 text-sm">No hay cruces ni alertas de agenda para el periodo seleccionado.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Conflicto</th>
                                <th class="px-5 py-3">Caso afectado</th>
                                <th class="px-5 py-3">Recurso afectado</th>
                                <th class="px-5 py-3">Severidad</th>
                                <th class="px-5 py-3">Accion sugerida</th>
                                <th class="px-5 py-3"><span class="sr-only">Control</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($conflicts as $conflict)
                                <tr class="align-top">
                                    <td class="px-5 py-4 font-semibold text-slate-900">{{ \Illuminate\Support\Str::headline($conflict['type']) }}</td>
                                    <td class="px-5 py-4">
                                        <a href="{{ route('cases.control', $conflict['case']) }}" class="font-semibold text-sky-700 hover:text-sky-900">{{ $conflict['case']->case_code }}</a>
                                        <p class="ops-muted mt-1 text-xs">{{ $conflict['case']->scheduled_at?->format('d/m/Y H:i') }}</p>
                                        @if (count($conflict['related_cases']) > 1)
                                            <p class="ops-muted mt-1 text-xs">Cruza con: {{ collect($conflict['related_cases'])->reject(fn (string $code): bool => $code === $conflict['case']->case_code)->implode(', ') }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">{{ $conflict['resource'] }}</td>
                                    <td class="px-5 py-4">
                                        <span class="{{ $severityClasses[$conflict['severity']] ?? 'ops-badge-neutral' }}">{{ ucfirst($conflict['severity']) }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">{{ $conflict['suggestion'] }}</td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="{{ route('cases.control', $conflict['case']) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Abrir control</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
