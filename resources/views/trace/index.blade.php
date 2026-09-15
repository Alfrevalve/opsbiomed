@extends('layouts.ops')

@section('title', 'Escanear trazabilidad | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <p class="ops-eyebrow">Trazabilidad operativa</p>
            <h1 class="ops-section-title mt-1 text-2xl font-semibold">Escanear codigo</h1>
            <p class="ops-muted mt-2 text-sm">Identifica lotes, casos, reservas, devoluciones y fallas sin modificar inventario ni estados operativos.</p>
        </div>

        <section class="ops-card p-5">
            <form method="POST" action="{{ route('trace.scan') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                @csrf
                <label class="block flex-1 text-sm font-semibold text-slate-800" for="trace_code">
                    Escanear o ingresar codigo
                    <input
                        id="trace_code"
                        name="trace_code"
                        type="text"
                        value="{{ old('trace_code', $traceCode ?? '') }}"
                        maxlength="160"
                        autofocus
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        required
                        class="mt-2 block w-full border-slate-300 font-mono text-base uppercase shadow-sm focus:border-sky-600 focus:ring-sky-600"
                        placeholder="LOT-123-MR8-9BA30"
                    >
                </label>
                <button type="submit" class="ops-button-primary inline-flex min-h-11 items-center justify-center px-5 py-2.5 text-sm font-semibold">Consultar</button>
            </form>
            @error('trace_code')
                <p class="mt-2 text-sm font-medium text-rose-700">{{ $message }}</p>
            @enderror
            <p class="ops-muted mt-3 text-xs">Compatible con lectores fisicos que envian Enter al finalizar el codigo.</p>
        </section>

        @if (isset($traceCode) && $scanResult === null)
            <section class="border border-rose-200 bg-rose-50 px-5 py-4 text-rose-800" role="alert">
                <p class="font-semibold">Codigo no encontrado</p>
                <p class="mt-1 text-sm">No se encontro un registro para <span class="font-mono">{{ $traceCode }}</span>. Verifica la etiqueta o busca el lote en inventario.</p>
            </section>
        @endif

        @if ($scanResult !== null)
            <section class="ops-card overflow-hidden" aria-live="polite">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-start">
                    <div>
                        <p class="ops-eyebrow">Resultado de escaneo</p>
                        <h2 class="ops-section-title mt-1 text-xl font-semibold">{{ $scanResult['title'] }}</h2>
                        <p class="ops-muted mt-1 text-sm">{{ $scanResult['entity_type'] }}</p>
                    </div>
                    <span class="ops-badge-info">{{ $scanResult['state'] }}</span>
                </div>

                <div class="grid gap-5 p-5 sm:grid-cols-2">
                    <div class="border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
                        <p class="ops-muted text-xs font-semibold uppercase tracking-wide">Codigo trazable</p>
                        <p class="mt-2 break-all font-mono text-lg font-semibold text-slate-900">{{ $scanResult['trace_code'] }}</p>
                    </div>
                    @foreach ($scanResult['details'] as $label => $value)
                        <div>
                            <p class="ops-muted text-xs font-semibold uppercase tracking-wide">{{ $label }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $value }}</p>
                        </div>
                    @endforeach
                    @if ($scanResult['location'])
                        <div>
                            <p class="ops-muted text-xs font-semibold uppercase tracking-wide">Ubicacion operativa</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $scanResult['location'] }}</p>
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
                    <p class="ops-muted text-xs">El escaneo queda auditado y no realiza movimientos de stock.</p>
                    @if ($scanResult['actions'] !== [])
                        <div class="flex flex-wrap gap-2">
                            @foreach ($scanResult['actions'] as $action)
                                <a href="{{ $action['href'] }}" class="ops-button-secondary inline-flex items-center justify-center px-3 py-2 text-sm font-semibold">{{ $action['label'] }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endif
    </div>
@endsection
