@extends('layouts.ops')

@section('title', 'Editar categoria de manual | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <div><a href="{{ route('admin.manual.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Volver a administracion</a><h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Editar categoria</h1><p class="mt-2 text-sm text-slate-500">Los cambios afectan la organizacion del manual publicado.</p></div>
        <form method="POST" action="{{ route('admin.manual.categories.update', $category) }}" class="ops-card space-y-6">
            @csrf
            @method('PATCH')
            @include('admin.manual.categories._form', ['category' => $category])
        </form>
        @if ($category->articles()->exists())
            <div class="ops-card border-l-4 border-l-amber-600 bg-amber-50 text-sm text-amber-900">Esta categoria tiene tutoriales asociados y no puede eliminarse.</div>
        @else
            <form method="POST" action="{{ route('admin.manual.categories.destroy', $category) }}" onsubmit="return confirm('¿Eliminar esta categoria?')" class="flex justify-end">
                @csrf
                @method('DELETE')
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="text-sm font-semibold text-red-700 hover:text-red-900">Eliminar categoria</button>
            </form>
        @endif
    </div>
@endsection
