@extends('layouts.ops')

@section('title', 'Cobertura quirurgica MR8 | OPS BIOMED')

@section('content')
    @php
        $riskClasses = [
            'green' => 'bg-emerald-50 text-emerald-700',
            'yellow' => 'bg-amber-50 text-amber-700',
            'red' => 'bg-rose-50 text-rose-700',
        ];
        $requirementLabels = [
            'critica' => 'Critica',
            'backup' => 'Backup',
            'segun_caso' => 'Segun caso',
        ];
        $formatDimension = fn (mixed $value, string $unit): string => $value === null
            ? 'Cualquier'
            : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').' '.$unit;
    @endphp

    <div class="space-y-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('inventory.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al inventario</a>
                <p class="mt-3 text-sm font-medium text-sky-700">Control de ruptura por kit</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Cobertura quirurgica MR8</h1>
                <p class="mt-2 text-sm text-slate-500">Solo se cuenta stock elegible inmediato, descontando reservas activas.</p>
            </div>
            <div class="text-sm text-slate-500">
                <p>Minimo: <span class="font-semibold text-slate-900">{{ config('ops-biomed.min_surgeries', 3) }} cirugias</span></p>
                <p class="mt-1">Objetivo: <span class="font-semibold text-slate-900">{{ config('ops-biomed.target_surgeries', 5) }} cirugias</span></p>
            </div>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6" aria-label="Resumen de cobertura">
            @foreach ([
                ['label' => 'Combinaciones en rojo', 'value' => $coverage['red'], 'dot' => 'bg-rose-600'],
                ['label' => 'Combinaciones en amarillo', 'value' => $coverage['yellow'], 'dot' => 'bg-amber-500'],
                ['label' => 'Tipos con cobertura completa', 'value' => $coverage['complete_types'], 'dot' => 'bg-emerald-500'],
                ['label' => 'Tipos en riesgo', 'value' => $coverage['at_risk_types'], 'dot' => 'bg-rose-500'],
                ['label' => 'Productos criticos bajo minimo', 'value' => $coverage['critical_under_minimum'], 'dot' => 'bg-rose-600'],
                ['label' => 'Productos bajo objetivo', 'value' => $coverage['under_target'], 'dot' => 'bg-amber-500'],
            ] as $metric)
                <article class="border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-500">{{ $metric['label'] }}</p>
                        <span class="h-2.5 w-2.5 rounded-full {{ $metric['dot'] }}" aria-hidden="true"></span>
                    </div>
                    <p class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">{{ $metric['value'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="border border-slate-200 bg-white shadow-sm" aria-label="Semaforo de cobertura">
            <div class="flex flex-wrap items-center gap-4 border-b border-slate-200 px-5 py-4 text-sm">
                <span class="font-semibold text-slate-900">Semaforo</span>
                <span class="inline-flex items-center gap-2 text-emerald-700"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Verde: {{ config('ops-biomed.target_surgeries', 5) }} o mas</span>
                <span class="inline-flex items-center gap-2 text-amber-700"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>Amarillo: {{ config('ops-biomed.min_surgeries', 3) }} a {{ config('ops-biomed.target_surgeries', 5) - 1 }}</span>
                <span class="inline-flex items-center gap-2 text-rose-700"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>Rojo: menos de {{ config('ops-biomed.min_surgeries', 3) }}</span>
                <span class="inline-flex items-center gap-2 text-slate-500"><span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span>Gris: no aplica</span>
            </div>
        </section>

        @forelse ($coverage['types'] as $type)
            <section id="coverage-{{ $type['code'] }}" class="border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="font-semibold text-slate-950">{{ $type['name'] }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $type['rows']->count() }} reglas requeridas · {{ $type['green'] }} verdes · {{ $type['yellow'] }} amarillas · {{ $type['red'] }} rojas</p>
                    </div>
                    <span class="inline-flex w-fit px-2.5 py-1 text-xs font-semibold {{ $type['complete'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $type['complete'] ? 'Cobertura completa' : 'Tipo en riesgo' }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[1250px] divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Longitud</th>
                                <th class="px-5 py-3 font-semibold">Diametro</th>
                                <th class="px-5 py-3 font-semibold">Tipo de fresa</th>
                                <th class="px-5 py-3 font-semibold">Componente</th>
                                <th class="px-5 py-3 font-semibold">Requisito</th>
                                <th class="px-5 py-3 font-semibold">Stock elegible inmediato</th>
                                <th class="px-5 py-3 font-semibold">Reservado</th>
                                <th class="px-5 py-3 font-semibold">Stock neto</th>
                                <th class="px-5 py-3 font-semibold">Minimo</th>
                                <th class="px-5 py-3 font-semibold">Objetivo</th>
                                <th class="px-5 py-3 font-semibold">Semaforo</th>
                                <th class="px-5 py-3 font-semibold">Recomendacion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($type['rows'] as $row)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $formatDimension($row['length_cm'], 'cm') }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $formatDimension($row['diameter_mm'], 'mm') }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ ucfirst($row['cut_type']) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ ucfirst($row['component_type']) }}</td>
                                    <td class="max-w-xs px-5 py-4 text-slate-700">
                                        <span class="font-semibold">{{ $requirementLabels[$row['requirement']] ?? ucfirst($row['requirement']) }}</span>
                                        @if ($row['notes'])
                                            <p class="mt-1 text-xs text-slate-500">{{ $row['notes'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 font-semibold text-slate-900">{{ $row['stock_elegible'] }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $row['reserved_active'] }}</td>
                                    <td class="px-5 py-4 font-semibold text-slate-900">{{ $row['available_net'] }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $row['minimum'] }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $row['target'] }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex px-2.5 py-1 text-xs font-semibold {{ $riskClasses[$row['risk']] }}">{{ $row['risk_label'] }}</span>
                                    </td>
                                    <td class="max-w-sm px-5 py-4 text-xs text-slate-600">
                                        <p>{{ $row['recommendation'] }}</p>
                                        @if ($row['has_inconsistency'])
                                            <p class="mt-1 font-semibold text-orange-700">Hay una inconsistencia negativa asociada.</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <section class="border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
                No hay tipos de cirugia activos con reglas de kit configuradas.
            </section>
        @endforelse
    </div>
@endsection
