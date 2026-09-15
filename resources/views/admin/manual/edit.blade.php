@extends('layouts.ops')

@section('title', 'Editar tutorial | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('admin.manual.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Volver a administracion del manual</a>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Editar tutorial</h1>
                <p class="mt-2 text-sm text-slate-500">Actualiza el contenido y revisa la vista previa antes de publicar.</p>
            </div>
            <a href="{{ route('admin.manual.preview', $article) }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Vista previa</a>
        </div>
        <form method="POST" action="{{ route('admin.manual.update', $article) }}" class="ops-card space-y-8">
            @csrf
            @method('PUT')
            @include('admin.manual._form', ['article' => $article])
        </form>
    </div>
@endsection
