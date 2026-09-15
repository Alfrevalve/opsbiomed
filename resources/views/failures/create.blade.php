@extends('layouts.ops')

@section('title', 'Reportar falla | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <a href="{{ route('failures.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver a fallas</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Reportar falla tecnica</h1>
            <p class="mt-2 text-sm text-slate-500">Registra el evento, su trazabilidad y las acciones tomadas.</p>
        </div>
        @include('failures._form')
    </div>
@endsection
