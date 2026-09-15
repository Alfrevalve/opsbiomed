<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiquetas de lotes | OPS BIOMED MR8</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .trace-print-controls { display: none !important; }
            body { background: #ffffff !important; }
        }
    </style>
</head>
<body class="ops-page min-h-screen">
    <main class="mx-auto max-w-7xl p-5 sm:p-8">
        <div class="trace-print-controls mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="ops-eyebrow">Trazabilidad operativa</p>
                <h1 class="ops-section-title mt-1 text-2xl font-semibold">Etiquetas de lotes</h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('inventory.index') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2 text-sm font-semibold">Volver a inventario</a>
                <button type="button" onclick="window.print()" class="ops-button-primary inline-flex items-center justify-center px-4 py-2 text-sm font-semibold">Imprimir etiquetas</button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($lots as $lot)
                <article class="border-2 border-slate-700 bg-white p-5 text-slate-900" style="break-inside: avoid;">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-300 pb-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-600">OPS BIOMED MR8</p>
                            <p class="mt-1 text-sm font-semibold">{{ $lot->product?->product_code ?: 'Sin codigo' }}</p>
                        </div>
                        <span class="text-xs font-semibold uppercase text-slate-600">Lote</span>
                    </div>
                    <p class="mt-4 text-base font-semibold">{{ $lot->product?->name ?: 'Producto no registrado' }}</p>
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-xs">
                        <div><dt class="font-semibold uppercase text-slate-500">Lote</dt><dd class="mt-1 font-medium">{{ $lot->lot ?: 'Sin lote' }}</dd></div>
                        <div><dt class="font-semibold uppercase text-slate-500">Serie</dt><dd class="mt-1 font-medium">{{ $lot->serial ?: 'No aplica' }}</dd></div>
                        <div><dt class="font-semibold uppercase text-slate-500">Vencimiento</dt><dd class="mt-1 font-medium">{{ $lot->expiry?->format('d/m/Y') ?: 'No registrado' }}</dd></div>
                        <div><dt class="font-semibold uppercase text-slate-500">Almacen</dt><dd class="mt-1 font-medium">{{ $lot->warehouse?->name ?: 'No registrado' }}</dd></div>
                        <div class="col-span-2"><dt class="font-semibold uppercase text-slate-500">Estado</dt><dd class="mt-1 font-medium">{{ \Illuminate\Support\Str::headline($lot->status?->value ?: 'sin estado') }}</dd></div>
                    </dl>
                    <div class="mt-5 border-2 border-slate-900 px-3 py-4 text-center">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Codigo escaneable</p>
                        <p class="mt-2 break-all font-mono text-base font-bold tracking-wide">{{ $lot->trace_code }}</p>
                    </div>
                </article>
            @empty
                <section class="ops-card p-6 text-sm text-slate-600 sm:col-span-2 xl:col-span-3">No hay lotes para imprimir.</section>
            @endforelse
        </div>

        <div class="trace-print-controls mt-6">
            {{ $lots->links() }}
        </div>
    </main>
</body>
</html>
