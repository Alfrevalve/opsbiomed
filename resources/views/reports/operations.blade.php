@extends('layouts.ops')

@section('title', 'Reporte operativo | OPS BIOMED MR8')

@section('content')
    <style>@media print { header, .no-print { display: none !important; } main { max-width: none !important; padding: 0 !important; } .shadow-sm { box-shadow: none !important; } }</style>
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><a href="{{ route('reports.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900 no-print">Volver a reportes</a><h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Reporte operativo</h1><p class="mt-2 text-sm text-slate-500">Actividad quirurgica del {{ $filters['date_from'] }} al {{ $filters['date_to'] }}.</p></div>
            <div class="flex flex-wrap gap-3 no-print"><a href="{{ route('reports.export', array_merge(['type' => 'operations'], request()->query())) }}" class="border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Exportar CSV</a><a href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" class="bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Vista imprimible</a></div>
        </div>
        @include('reports.partials.filters', ['action' => route('reports.operations')])
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            @foreach ([['label' => 'Casos', 'value' => $report['metrics']['total_cases']], ['label' => 'Promedio solicitud a cierre', 'value' => $report['metrics']['average_closure_hours'].' h'], ['label' => 'Canceladas', 'value' => $report['metrics']['cancelled_cases']], ['label' => 'Pendientes', 'value' => $report['metrics']['pending_cases']], ['label' => 'Con fallas', 'value' => $report['metrics']['incident_cases']], ['label' => 'Con diferencias', 'value' => $report['metrics']['difference_cases']]] as $metric)
                <article class="border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">{{ $metric['label'] }}</p><p class="mt-3 text-2xl font-semibold text-slate-950">{{ $metric['value'] }}</p></article>
            @endforeach
        </section>
        <section class="grid gap-6 lg:grid-cols-2">
            @foreach ([['title' => 'Cirugias por fecha', 'rows' => $report['by_date']], ['title' => 'Cirugias por institucion', 'rows' => $report['by_institution']], ['title' => 'Cirugias por medico', 'rows' => $report['by_doctor']], ['title' => 'Casos por estado', 'rows' => $report['by_status']], ['title' => 'Responsable registrado', 'rows' => $report['by_responsible']]] as $group)
                <section class="border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">{{ $group['title'] }}</h2></div><div class="divide-y divide-slate-100">@forelse ($group['rows'] as $row)<div class="flex items-center justify-between gap-4 px-5 py-3 text-sm"><span class="text-slate-700">{{ $row['label'] }}</span><span class="font-semibold text-slate-950">{{ $row['cases'] }}</span></div>@empty<p class="px-5 py-5 text-sm text-slate-500">Sin datos para el periodo.</p>@endforelse</div></section>
            @endforeach
        </section>
        <section class="grid gap-6 lg:grid-cols-2">
            @foreach ([['title' => 'Casos con incidencias o fallas', 'rows' => $report['incident_cases_list']], ['title' => 'Casos con diferencia de consumo', 'rows' => $report['difference_cases_list']]] as $group)
                <section class="border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">{{ $group['title'] }}</h2></div><div class="divide-y divide-slate-100">@forelse ($group['rows'] as $row)<a href="{{ route('cases.show', ['case' => $row['case_id']]) }}" class="block px-5 py-4 hover:bg-slate-50"><p class="text-sm font-semibold text-sky-700">{{ $row['case_code'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $row['institution'] }} · {{ $row['doctor'] }} · {{ $row['scheduled_at'] }}</p><p class="mt-1 text-xs text-slate-600">{{ $row['status'] }} · Responsable: {{ $row['responsible'] }}</p></a>@empty<p class="px-5 py-5 text-sm text-slate-500">Sin incidencias para el periodo.</p>@endforelse</div></section>
            @endforeach
        </section>
        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col justify-between gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center"><div><h2 class="font-semibold text-slate-950">Fallas tecnicas por periodo</h2><p class="mt-1 text-sm text-slate-500">Incluye bloqueos, revisiones, liberaciones y bajas.</p></div><span class="text-sm font-semibold text-slate-700">Promedio de liberacion: {{ $report['failure_report']['metrics']['average_release_hours'] }} h</span></div>
            <div class="grid gap-6 p-5 lg:grid-cols-3">
                <div><h3 class="text-sm font-semibold text-slate-900">Por mes</h3><div class="mt-3 divide-y divide-slate-100">@forelse ($report['failure_report']['by_month'] as $row)<div class="flex justify-between gap-3 py-2 text-sm"><span class="text-slate-700">{{ $row['label'] }}</span><span class="font-semibold text-slate-900">{{ $row['failures'] }}</span></div>@empty<p class="py-2 text-sm text-slate-500">Sin fallas.</p>@endforelse</div></div>
                <div><h3 class="text-sm font-semibold text-slate-900">Por producto</h3><div class="mt-3 divide-y divide-slate-100">@forelse ($report['failure_report']['by_product']->take(8) as $row)<div class="flex justify-between gap-3 py-2 text-sm"><span class="text-slate-700">{{ $row['label'] }}</span><span class="font-semibold text-rose-700">{{ $row['failures'] }}</span></div>@empty<p class="py-2 text-sm text-slate-500">Sin fallas.</p>@endforelse</div></div>
                <div><h3 class="text-sm font-semibold text-slate-900">Por institucion</h3><div class="mt-3 divide-y divide-slate-100">@forelse ($report['failure_report']['by_institution']->take(8) as $row)<div class="flex justify-between gap-3 py-2 text-sm"><span class="text-slate-700">{{ $row['label'] }}</span><span class="font-semibold text-rose-700">{{ $row['failures'] }}</span></div>@empty<p class="py-2 text-sm text-slate-500">Sin fallas.</p>@endforelse</div></div>
            </div>
            <div class="border-t border-slate-200 px-5 py-4"><h3 class="text-sm font-semibold text-slate-900">Productos con fallas repetidas</h3><div class="mt-3 flex flex-wrap gap-2">@forelse ($report['failure_report']['repeated_products'] as $row)<span class="border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700">{{ $row['label'] }} ({{ $row['failures'] }})</span>@empty<span class="text-sm text-slate-500">No hay repeticion registrada.</span>@endforelse</div></div>
        </section>
    </div>
@endsection
