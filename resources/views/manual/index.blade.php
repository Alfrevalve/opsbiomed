@extends('layouts.ops')

@section('title', 'Manual Operativo | OPS BIOMED MR8')

@section('content')
    @php
        $levelLabels = $levelOptions;
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col justify-between gap-5 border-b border-slate-200 pb-6 xl:flex-row xl:items-end">
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center border border-cyan-200 bg-cyan-50">
                    <img src="{{ asset('images/ops-biomed-logo.png') }}" alt="OPS BIOMED MR8" class="h-10 w-10 object-contain" />
                </div>
                <div>
                    <p class="ops-eyebrow text-sm font-semibold uppercase tracking-[0.14em]">Ayuda y operación</p>
                    <h1 class="ops-section-title mt-2 text-2xl font-semibold">Manual Operativo OPS BIOMED MR8</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Aprende a utilizar cada módulo según tu rol y responsabilidad.</p>
                    <p class="mt-2 text-xs font-semibold text-slate-500">Rol actual: <span class="text-slate-800">{{ $roleName ?: 'Usuario operativo' }}</span></p>
                </div>
            </div>
            <a href="{{ route('manual.quick-start') }}" class="ops-button-primary inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold">Inicio rápido</a>
        </header>

        <section class="ops-card p-5 sm:p-6" aria-labelledby="manual-search-title">
            <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
                <div>
                    <h2 id="manual-search-title" class="text-lg font-semibold text-slate-950">¿Qué necesitas hacer?</h2>
                    <p class="mt-1 text-sm text-slate-500">Busca por título, módulo, categoría o palabra clave.</p>
                </div>
                <form method="GET" action="{{ route('manual.search') }}" class="flex w-full gap-2 lg:max-w-xl">
                    <label for="manual-q" class="sr-only">Buscar en el manual</label>
                    <input id="manual-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Ej. reservar lote, devolución, factura" class="block min-w-0 flex-1 border-slate-300 text-sm focus:border-cyan-700 focus:ring-cyan-700" />
                    <button type="submit" class="ops-button-primary shrink-0 px-4 py-2 text-sm font-semibold">Buscar</button>
                </form>
            </div>

            @if ($quickActions !== [])
                <div class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    @foreach ($quickActions as $action)
                        <a href="{{ route('manual.show', $action['slug']) }}" class="flex items-center justify-between gap-3 border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700 hover:border-cyan-300 hover:bg-cyan-50 hover:text-cyan-900">
                            <span>{{ $action['label'] }}</span>
                            <span aria-hidden="true">→</span>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="mt-5 border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">No hay acciones rápidas disponibles para tu rol y permisos.</p>
            @endif
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(300px,0.72fr)]">
            <div class="ops-card p-5 sm:p-6">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div>
                        <p class="ops-eyebrow text-xs font-semibold uppercase tracking-[0.14em]">Ruta recomendada</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-950">{{ $roleName ?: 'Usuario operativo' }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Secuencia sugerida para tu trabajo diario.</p>
                    </div>
                    <span class="ops-badge-info">{{ count($roleGuide) }} pasos</span>
                </div>
                <ol class="mt-5 grid gap-3 md:grid-cols-2">
                    @foreach ($roleGuide as $index => $guide)
                        @php($guideArticle = $guideArticles->get($guide['slug']))
                        <li class="flex items-start gap-3 border border-slate-200 p-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-cyan-50 text-xs font-bold text-cyan-800">{{ $index + 1 }}</span>
                            <div class="min-w-0">
                                @if ($guideArticle)
                                    <a href="{{ route('manual.show', $guideArticle->slug) }}" class="text-sm font-semibold text-slate-800 hover:text-cyan-800">{{ $guide['step'] }}</a>
                                @else
                                    <span class="text-sm font-semibold text-slate-700">{{ $guide['step'] }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="ops-card p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="ops-eyebrow text-xs font-semibold uppercase tracking-[0.14em]">Progreso</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-950">Avance por categoría</h2>
                    </div>
                    <x-nav.icon name="check-circle" class="h-6 w-6 text-emerald-700" />
                </div>
                <div class="mt-5 space-y-4">
                    @foreach ($categories as $category)
                        @php($progress = $categoryProgress->get($category->id, ['total' => 0, 'completed' => 0, 'percentage' => 0]))
                        @if ($progress['total'] > 0)
                            <div>
                                <div class="flex items-center justify-between gap-3 text-xs">
                                    <span class="truncate font-semibold text-slate-700">{{ $category->name }}</span>
                                    <span class="shrink-0 text-slate-500">{{ $progress['completed'] }}/{{ $progress['total'] }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 bg-slate-100"><div class="h-1.5 bg-teal-700" style="width: {{ $progress['percentage'] }}%"></div></div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>

        <section class="ops-card p-5 sm:p-6" aria-labelledby="manual-filters-title">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 id="manual-filters-title" class="text-lg font-semibold text-slate-950">Biblioteca de tutoriales</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $articles->count() }} tutoriales disponibles para tu acceso.</p>
                </div>
                @if ($isSearch || $isQuickStart)
                    <a href="{{ route('manual.index') }}" class="text-sm font-semibold text-cyan-800 hover:text-cyan-950">Ver todo</a>
                @endif
            </div>

            <form method="GET" action="{{ $isSearch ? route('manual.search') : route('manual.index') }}" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label for="manual-level" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Nivel</label>
                    <select id="manual-level" name="level" class="mt-1.5 block w-full border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($levelOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['level'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="manual-category" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Categoría</label>
                    <select id="manual-category" name="category" class="mt-1.5 block w-full border-slate-300 text-sm">
                        <option value="">Todas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="manual-role" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Rol</label>
                    <select id="manual-role" name="role" class="mt-1.5 block w-full border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($roleOptions as $role)
                            <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="manual-module" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Módulo</label>
                    <select id="manual-module" name="module" class="mt-1.5 block w-full border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($moduleOptions as $module)
                            <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-3">
                    <label class="flex items-center gap-2 pb-2 text-sm text-slate-700"><input type="checkbox" name="recommended" value="1" @checked(filter_var($filters['recommended'] ?? false, FILTER_VALIDATE_BOOLEAN)) class="border-slate-300 text-teal-700 focus:ring-teal-700" /> Solo recomendados</label>
                    <button type="submit" class="ops-button-secondary ml-auto px-4 py-2 text-sm font-semibold">Filtrar</button>
                </div>
                @if (! empty($filters['q']))
                    <input type="hidden" name="q" value="{{ $filters['q'] }}" />
                @endif
            </form>

            @if ($articles->isEmpty())
                <div class="mt-5 border border-slate-200 bg-slate-50 px-5 py-8 text-center text-sm text-slate-600">No hay tutoriales que coincidan con los filtros seleccionados.</div>
            @else
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($articles as $article)
                        @php($articleProgress = $progressByArticle->get($article->id))
                        <article class="flex h-full flex-col border border-slate-200 bg-white p-5 hover:border-cyan-300">
                            <div class="flex items-start justify-between gap-3">
                                <span class="ops-badge-info">{{ $levelLabels[$article->level] ?? ucfirst($article->level) }}</span>
                                @if ($articleProgress?->completed_at)
                                    <span class="ops-badge-success">Leído</span>
                                @endif
                            </div>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $article->category?->name }}</p>
                            <h3 class="mt-2 text-base font-semibold text-slate-950"><a href="{{ route('manual.show', $article->slug) }}" class="hover:text-cyan-800">{{ $article->title }}</a></h3>
                            <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">{{ $article->summary }}</p>
                            <div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4 text-xs text-slate-500">
                                <span>{{ $article->estimated_minutes }} min · {{ $article->module }}</span>
                                <a href="{{ route('manual.show', $article->slug) }}" class="font-semibold text-cyan-800 hover:text-cyan-950">Abrir tutorial</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($recentProgress->isNotEmpty())
            <section class="ops-card p-5 sm:p-6" aria-labelledby="recent-manual-title">
                <h2 id="recent-manual-title" class="text-lg font-semibold text-slate-950">Últimos tutoriales consultados</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($recentProgress as $item)
                        <a href="{{ route('manual.show', $item->article->slug) }}" class="border border-slate-200 px-4 py-3 hover:border-cyan-300 hover:bg-cyan-50">
                            <span class="block text-sm font-semibold text-slate-800">{{ $item->article->title }}</span>
                            <span class="mt-1 block text-xs text-slate-500">{{ $item->last_viewed_at?->format('d/m/Y H:i') }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
