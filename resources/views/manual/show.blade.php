@extends('layouts.ops')

@section('title', $article->title.' | Manual OPS BIOMED MR8')

@section('content')
    @php
        $levelLabels = ['basico' => 'Básico', 'operativo' => 'Operativo', 'supervision' => 'Supervisión', 'administracion' => 'Administración'];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <a href="{{ route('manual.index') }}" class="font-semibold text-cyan-800 hover:text-cyan-950">← Volver al manual</a>
            <span class="text-slate-500">Rol actual: <strong class="text-slate-800">{{ $roleName ?: 'Usuario operativo' }}</strong></span>
        </div>

        <header class="border-b border-slate-200 pb-6">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                <span>{{ $article->category?->name }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ $levelLabels[$article->level] ?? ucfirst($article->level) }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ $article->estimated_minutes }} minutos</span>
            </div>
            <h1 class="ops-section-title mt-3 text-2xl font-semibold">{{ $article->title }}</h1>
            <p class="mt-3 max-w-4xl text-base leading-7 text-slate-600">{{ $article->summary }}</p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                @if ($progress->completed_at)
                    <span class="ops-badge-success">Tutorial leído</span>
                @else
                    <span class="ops-badge-info">Pendiente de lectura</span>
                @endif
                <span class="text-xs text-slate-500">Acceso según rol y permiso del módulo.</span>
            </div>
        </header>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.7fr)]">
            <main class="space-y-6">
                <section class="ops-card p-5 sm:p-6" aria-labelledby="article-body-title">
                    <h2 id="article-body-title" class="text-lg font-semibold text-slate-950">Qué debes saber</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-700">{{ $article->body }}</p>
                </section>

                <section class="ops-card p-5 sm:p-6" aria-labelledby="steps-title">
                    <h2 id="steps-title" class="text-lg font-semibold text-slate-950">Pasos del procedimiento</h2>
                    @if (! empty($article->steps_json))
                        <ol class="mt-5 space-y-4">
                            @foreach ($article->steps_json as $step)
                                <li class="flex items-start gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-cyan-50 text-xs font-bold text-cyan-800">{{ $loop->iteration }}</span>
                                    <span class="pt-1 text-sm leading-6 text-slate-700">{{ $step }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="mt-4 text-sm text-slate-500">No hay pasos publicados para este tutorial.</p>
                    @endif
                </section>

                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="ops-card p-5" aria-labelledby="errors-title">
                        <h2 id="errors-title" class="font-semibold text-slate-950">Errores frecuentes</h2>
                        @if (! empty($article->common_errors_json))
                            <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-700">
                                @foreach ($article->common_errors_json as $error)
                                    <li class="flex gap-2"><span class="font-bold text-amber-700">!</span><span>{{ $error }}</span></li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-3 text-sm text-slate-500">No hay errores frecuentes registrados.</p>
                        @endif
                    </section>
                    <section class="border border-amber-200 bg-amber-50 p-5" aria-labelledby="blocked-title">
                        <h2 id="blocked-title" class="font-semibold text-amber-900">Si el proceso se bloquea</h2>
                        <p class="mt-3 text-sm leading-6 text-amber-900">{{ $article->blocked_action ?: 'Detén la operación y escala al responsable.' }}</p>
                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-amber-800">Escalamiento</p>
                        <p class="mt-1 text-sm font-semibold text-amber-950">{{ $article->escalation_role ?: 'Jefe de Linea' }}</p>
                    </section>
                </div>

                <section class="ops-card p-5 sm:p-6" aria-labelledby="result-title">
                    <h2 id="result-title" class="text-lg font-semibold text-slate-950">Resultado esperado</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-700">{{ $article->expected_result }}</p>
                </section>
            </main>

            <aside class="space-y-6">
                <section class="ops-card p-5" aria-labelledby="before-title">
                    <h2 id="before-title" class="font-semibold text-slate-950">Antes de comenzar</h2>
                    @if (! empty($article->prerequisites_json))
                        <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-700">
                            @foreach ($article->prerequisites_json as $item)
                                <li class="flex gap-2"><span class="text-cyan-700">•</span><span>{{ $item }}</span></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-3 text-sm text-slate-500">No requiere información previa adicional.</p>
                    @endif
                </section>

                <section class="ops-card p-5" aria-labelledby="roles-title">
                    <h2 id="roles-title" class="font-semibold text-slate-950">Roles autorizados</h2>
                    @if (! empty($article->roles_json))
                        <p class="mt-3 text-sm leading-6 text-slate-700">{{ implode(', ', $article->roles_json) }}</p>
                    @elseif ($article->permission)
                        <p class="mt-3 text-sm leading-6 text-slate-700">Usuarios con el permiso operativo <span class="font-semibold">{{ $article->permission }}</span>.</p>
                    @else
                        <p class="mt-3 text-sm leading-6 text-slate-700">Todos los usuarios autenticados.</p>
                    @endif
                </section>

                <section class="ops-card p-5" aria-labelledby="fields-title">
                    <h2 id="fields-title" class="font-semibold text-slate-950">Campos obligatorios</h2>
                    @if (! empty($article->required_fields_json))
                        <ul class="mt-4 space-y-2 text-sm text-slate-700">
                            @foreach ($article->required_fields_json as $field)
                                <li class="border-b border-slate-100 pb-2">{{ $field }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-3 text-sm text-slate-500">Depende de la pantalla y del permiso operativo.</p>
                    @endif
                </section>

                <section class="border border-cyan-200 bg-cyan-50 p-5" aria-labelledby="action-title">
                    <h2 id="action-title" class="font-semibold text-cyan-950">Continuar</h2>
                    <div class="mt-4 space-y-3">
                        @if ($moduleUrl)
                            <a href="{{ $moduleUrl }}" class="ops-button-primary inline-flex w-full items-center justify-center px-4 py-2.5 text-sm font-semibold">Ir al módulo</a>
                        @endif
                        @if (! $progress->completed_at)
                            <form method="POST" action="{{ route('manual.read', $article) }}">
                                @csrf
                                <button type="submit" class="ops-button-secondary inline-flex w-full items-center justify-center px-4 py-2.5 text-sm font-semibold">Marcar como leído</button>
                            </form>
                        @else
                            <p class="text-center text-sm font-semibold text-emerald-800">Lectura completada el {{ $progress->completed_at->format('d/m/Y H:i') }}.</p>
                        @endif
                    </div>
                </section>
            </aside>
        </div>

        @if ($relatedArticles->isNotEmpty())
            <section class="ops-card p-5 sm:p-6" aria-labelledby="related-title">
                <h2 id="related-title" class="text-lg font-semibold text-slate-950">También puedes revisar</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($relatedArticles as $related)
                        <a href="{{ route('manual.show', $related->slug) }}" class="border border-slate-200 p-4 hover:border-cyan-300 hover:bg-cyan-50">
                            <span class="block text-sm font-semibold text-slate-800">{{ $related->title }}</span>
                            <span class="mt-2 block text-xs leading-5 text-slate-500">{{ $related->summary }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
