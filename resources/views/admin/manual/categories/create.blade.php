@extends('layouts.ops')

@section('title', 'Nueva categoria de manual | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <div><a href="{{ route('admin.manual.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Volver a administracion</a><h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Nueva categoria</h1><p class="mt-2 text-sm text-slate-500">Organiza los tutoriales por area de trabajo.</p></div>
        <form method="POST" action="{{ route('admin.manual.categories.store') }}" class="ops-card space-y-6">
            @csrf
            @include('admin.manual.categories._form')
        </form>
    </div>
@endsection
