@extends('layouts.ops')

@section('title', 'Administrar manual | OPS BIOMED MR8')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-sky-700">Administracion de contenido</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Manual Operativo OPS BIOMED MR8</h1>
                <p class="mt-2 text-sm text-slate-500">Gestiona tutoriales, categorias y contenido que vera el equipo operativo.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.manual.categories.create') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Nueva categoria</a>
                <a href="{{ route('admin.manual.create') }}" class="ops-button-primary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Nuevo tutorial</a>
            </div>
        </div>

        @if ($errors->any())
            <div class="ops-card border-l-4 border-l-red-600 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">
                <p class="font-semibold">No se pudo completar la accion.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="ops-card" aria-labelledby="manual-filters-heading">
            <div class="mb-4 flex items-center justify-between gap-4">
                <h2 id="manual-filters-heading" class="text-sm font-semibold text-slate-900">Filtrar tutoriales</h2>
                <a href="{{ route('manual.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ver manual publicado</a>
            </div>
            <form method="GET" class="grid gap-4 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label for="q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Buscar</label>
                    <input id="q" name="q" value="{{ request('q') }}" class="mt-1.5 block w-full border-slate-300 text-sm" placeholder="Titulo, resumen o slug">
                </div>
                <div>
                    <label for="category_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Categoria</label>
                    <select id="category_id" name="category_id" class="mt-1.5 block w-full border-slate-300 bg-white text-sm">
                        <option value="">Todas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="level" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Nivel</label>
                    <select id="level" name="level" class="mt-1.5 block w-full border-slate-300 bg-white text-sm">
                        <option value="">Todos</option>
                        @foreach ($levels as $value => $label)
                            <option value="{{ $value }}" @selected(request('level') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</label>
                    <select id="status" name="status" class="mt-1.5 block w-full border-slate-300 bg-white text-sm">
                        <option value="">Todos</option>
                        <option value="published" @selected(request('status') === 'published')>Publicados</option>
                        <option value="disabled" @selected(request('status') === 'disabled')>Desactivados</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 md:col-span-3">
                    <a href="{{ route('admin.manual.index') }}" class="ops-button-secondary inline-flex px-4 py-2 text-sm font-semibold">Limpiar</a>
                    <button type="submit" class="ops-button-primary inline-flex px-4 py-2 text-sm font-semibold">Filtrar</button>
                </div>
            </form>
        </section>

        <section class="ops-card overflow-hidden" aria-labelledby="articles-heading">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="articles-heading" class="text-base font-semibold text-slate-900">Tutoriales</h2>
                    <p class="mt-1 text-sm text-slate-500">Los desactivados no son visibles para usuarios normales.</p>
                </div>
                <span class="ops-badge-info">{{ $articles->total() }} registros</span>
            </div>
            @if ($articles->isEmpty())
                <div class="px-6 py-12 text-center text-sm text-slate-500">No hay tutoriales que coincidan con los filtros.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Tutorial</th>
                                <th class="px-5 py-3">Categoria / nivel</th>
                                <th class="px-5 py-3">Roles</th>
                                <th class="px-5 py-3">Estado</th>
                                <th class="px-5 py-3">Version</th>
                                <th class="px-5 py-3">Actualizado</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($articles as $article)
                                <tr class="align-top">
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-slate-900">{{ $article->title }}</p>
                                        <p class="mt-1 text-xs text-slate-500">/{{ $article->slug }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-slate-600">
                                        <p>{{ $article->category?->name ?: 'Sin categoria' }}</p>
                                        <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">{{ $levels[$article->level] ?? $article->level }}</p>
                                    </td>
                                    <td class="max-w-xs px-5 py-4 text-xs text-slate-600">{{ implode(', ', $article->roles_json ?? []) ?: 'Todos los roles' }}</td>
                                    <td class="px-5 py-4">
                                        <span class="{{ $article->active ? 'ops-badge-success' : 'ops-badge-neutral' }}">{{ $article->active ? 'Publicado' : 'Desactivado' }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-slate-600">{{ $article->version }}</td>
                                    <td class="px-5 py-4 text-xs text-slate-600">
                                        <p>{{ $article->updated_at?->format('d/m/Y H:i') }}</p>
                                        <p class="mt-1 text-slate-500">{{ $article->updatedBy?->name ?: 'Sistema' }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex min-w-[150px] flex-wrap justify-end gap-2">
                                            <a href="{{ route('admin.manual.edit', $article) }}" class="font-semibold text-sky-700 hover:text-sky-900">Editar</a>
                                            <a href="{{ route('admin.manual.preview', $article) }}" class="font-semibold text-slate-700 hover:text-sky-700">Vista previa</a>
                                            @if ($article->active)
                                                <form method="POST" action="{{ route('admin.manual.disable', $article) }}" onsubmit="return confirm('¿Desactivar este tutorial? Dejara de mostrarse en el manual publico.')">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="font-semibold text-amber-700 hover:text-amber-900">Desactivar</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.manual.publish', $article) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="font-semibold text-emerald-700 hover:text-emerald-900">Publicar</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.manual.destroy', $article) }}" onsubmit="return confirm('¿Eliminar este tutorial? Esta accion no se puede deshacer.')">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="confirm" value="1">
                                                <button type="submit" class="font-semibold text-red-700 hover:text-red-900">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-4">{{ $articles->links() }}</div>
            @endif
        </section>

        <section class="ops-card overflow-hidden" aria-labelledby="categories-heading">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 id="categories-heading" class="text-base font-semibold text-slate-900">Categorias</h2>
                    <p class="mt-1 text-sm text-slate-500">Una categoria con tutoriales no puede eliminarse; puede desactivarse.</p>
                </div>
                <a href="{{ route('admin.manual.categories.create') }}" class="font-semibold text-sky-700 hover:text-sky-900">Crear categoria</a>
            </div>
            @if ($categories->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-500">No hay categorias registradas.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Categoria</th><th class="px-5 py-3">Orden</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3">Tutoriales</th><th class="px-5 py-3"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($categories as $category)
                                <tr>
                                    <td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $category->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $category->slug }}</p></td>
                                    <td class="px-5 py-4 text-slate-600">{{ $category->sort_order }}</td>
                                    <td class="px-5 py-4"><span class="{{ $category->active ? 'ops-badge-success' : 'ops-badge-neutral' }}">{{ $category->active ? 'Activa' : 'Inactiva' }}</span></td>
                                    <td class="px-5 py-4 text-slate-600">{{ $category->articles_count ?? $category->articles()->count() }}</td>
                                    <td class="px-5 py-4 text-right"><a href="{{ route('admin.manual.categories.edit', $category) }}" class="font-semibold text-sky-700 hover:text-sky-900">Editar</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
