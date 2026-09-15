@extends('layouts.ops')

@section('title', 'Nuevo medico | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6"><div><a href="{{ route('masters.doctors.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver a medicos</a><h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Nuevo medico</h1><p class="mt-2 text-sm text-slate-500">Registra el maestro clínico y su perfil comercial.</p></div><form method="POST" action="{{ route('masters.doctors.store') }}" class="space-y-8 border border-slate-200 bg-white p-5 shadow-sm sm:p-8">@csrf @if ($errors->any())<div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Revisa los campos marcados antes de guardar.</div>@endif @include('masters.doctors._form')</form></div>
@endsection
