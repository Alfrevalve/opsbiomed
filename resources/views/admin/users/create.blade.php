@extends('layouts.ops')

@section('title', 'Nuevo usuario | Administracion OPS BIOMED')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver a usuarios</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Crear usuario</h1>
            <p class="mt-2 text-sm text-slate-500">La cuenta se crea con un rol Spatie y una password temporal.</p>
        </div>
        @include('admin.users._form')
    </div>
@endsection
