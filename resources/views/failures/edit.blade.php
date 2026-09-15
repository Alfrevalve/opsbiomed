@extends('layouts.ops')

@section('title', 'Actualizar falla | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <a href="{{ route('failures.show', $failure) }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al reporte</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Actualizar seguimiento de falla</h1>
            <p class="mt-2 text-sm text-slate-500">El producto, lote y caso quedan protegidos para conservar la trazabilidad.</p>
        </div>
        @include('failures._form')
    </div>
@endsection
