@extends('layouts.ops')

@section('title', 'Nuevo tutorial | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <a href="{{ route('admin.manual.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Volver a administracion del manual</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Nuevo tutorial</h1>
            <p class="mt-2 text-sm text-slate-500">Crea contenido operativo en español para el equipo autorizado.</p>
        </div>
        <form method="POST" action="{{ route('admin.manual.store') }}" class="ops-card space-y-8">
            @csrf
            @include('admin.manual._form')
        </form>
    </div>
@endsection
