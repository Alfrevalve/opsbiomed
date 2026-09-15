@extends('layouts.ops')

@section('title', 'Nueva institucion | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div><a href="{{ route('masters.institutions.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver a instituciones</a><h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Nueva institucion</h1><p class="mt-2 text-sm text-slate-500">Registra la cuenta operativa y sus condiciones comerciales.</p></div>
        <form method="POST" action="{{ route('masters.institutions.store') }}" class="space-y-8 border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            @csrf
            @if ($errors->any())<div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Revisa los campos marcados antes de guardar.</div>@endif
            @include('masters.institutions._form')
        </form>
    </div>
@endsection
