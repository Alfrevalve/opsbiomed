@extends('layouts.ops')

@section('title', 'Reportes | OPS BIOMED MR8')

@section('content')
    @php
        $labels = [
            'operations' => ['title' => 'Reporte operativo', 'description' => 'Cirugias, estados, tiempos de cierre e incidencias.'],
            'commercial' => ['title' => 'Reporte comercial', 'description' => 'Consumo por cuenta, medicos clave y oportunidades comerciales.'],
            'billing' => ['title' => 'Facturacion y cobranza', 'description' => 'Valorizacion, facturas, saldos y antiguedad de deuda.'],
            'inventory' => ['title' => 'Reporte de inventario', 'description' => 'Cobertura MR8, alertas y forecast de reposicion.'],
        ];
    @endphp

    <div class="space-y-8">
        <div>
            <p class="text-sm font-medium text-sky-700">Control ejecutivo</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Reportes OPS BIOMED</h1>
            <p class="mt-2 text-sm text-slate-500">Selecciona el tablero que corresponde a tu responsabilidad operativa.</p>
        </div>
        <section class="grid gap-5 sm:grid-cols-2">
            @foreach ($sections as $section)
                <a href="{{ route('reports.'.$section) }}" class="border border-slate-200 bg-white p-6 shadow-sm transition hover:border-sky-300 hover:shadow-md">
                    <p class="text-lg font-semibold text-slate-950">{{ $labels[$section]['title'] }}</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $labels[$section]['description'] }}</p>
                    <span class="mt-5 inline-flex text-sm font-semibold text-sky-700">Abrir reporte</span>
                </a>
            @endforeach
        </section>
    </div>
@endsection
