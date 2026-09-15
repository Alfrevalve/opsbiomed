@extends('layouts.ops')

@section('title', 'Vista previa del tutorial | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div><a href="{{ route('admin.manual.edit', $article) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Editar tutorial</a><h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Vista previa</h1><p class="mt-2 text-sm text-slate-500">Revisa el contenido antes de dejarlo visible en el manual.</p></div>
            <span class="{{ $article->active ? 'ops-badge-success' : 'ops-badge-neutral' }}">{{ $article->active ? 'Publicado' : 'Desactivado' }}</span>
        </div>
        <article class="ops-card space-y-8">
            <header class="border-b border-slate-200 pb-6">
                <p class="text-sm font-medium text-sky-700">{{ $article->category?->name ?: 'Sin categoria' }} · {{ ucfirst($article->level) }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ $article->title }}</h2>
                <p class="mt-3 text-base leading-7 text-slate-600">{{ $article->summary }}</p>
                <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-500"><span class="ops-badge-info">Version {{ $article->version }}</span><span class="ops-badge-neutral">{{ $article->estimated_minutes }} min</span>@if ($article->module)<span class="ops-badge-neutral">{{ $article->module }}</span>@endif</div>
            </header>
            @if ($article->body)<section><h3 class="text-lg font-semibold text-slate-900">Contenido</h3><div class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $article->body }}</div></section>@endif
            @foreach ([['steps_json', 'Pasos'], ['prerequisites_json', 'Requisitos previos'], ['required_fields_json', 'Campos requeridos'], ['common_errors_json', 'Errores frecuentes']] as [$key, $heading])
                @if (!empty($article->{$key}))<section><h3 class="text-lg font-semibold text-slate-900">{{ $heading }}</h3><ol class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-slate-700">@foreach ($article->{$key} as $item)<li>{{ $item }}</li>@endforeach</ol></section>@endif
            @endforeach
            @if ($article->expected_result)<section class="border-l-4 border-l-emerald-600 bg-emerald-50 px-4 py-3"><h3 class="font-semibold text-emerald-900">Resultado esperado</h3><p class="mt-2 whitespace-pre-line text-sm text-emerald-800">{{ $article->expected_result }}</p></section>@endif
            @if ($article->blocked_action)<section class="border-l-4 border-l-amber-600 bg-amber-50 px-4 py-3"><h3 class="font-semibold text-amber-900">Advertencia operativa</h3><p class="mt-2 whitespace-pre-line text-sm text-amber-800">{{ $article->blocked_action }}</p></section>@endif
            <footer class="border-t border-slate-200 pt-5 text-xs text-slate-500">Ultima actualizacion: {{ $article->updated_at?->format('d/m/Y H:i') }} · {{ $article->updatedBy?->name ?: 'Sistema' }}</footer>
        </article>
    </div>
@endsection
