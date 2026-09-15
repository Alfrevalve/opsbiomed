<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiquetas de caso | {{ $case->case_code }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .trace-print-controls { display: none !important; }
            body { background: #ffffff !important; }
        }
    </style>
</head>
<body class="ops-page min-h-screen">
    <main class="mx-auto max-w-6xl p-5 sm:p-8">
        <div class="trace-print-controls mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="ops-eyebrow">Trazabilidad operativa</p>
                <h1 class="ops-section-title mt-1 text-2xl font-semibold">Etiquetas del caso {{ $case->case_code }}</h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('cases.control', $case) }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2 text-sm font-semibold">Volver al control</a>
                <button type="button" onclick="window.print()" class="ops-button-primary inline-flex items-center justify-center px-4 py-2 text-sm font-semibold">Imprimir etiquetas</button>
            </div>
        </div>

        <section class="border-2 border-slate-700 bg-white p-6 text-slate-900" style="break-inside: avoid;">
            <div class="flex flex-col justify-between gap-4 border-b border-slate-300 pb-4 sm:flex-row">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-600">OPS BIOMED MR8 / Caso quirurgico</p>
                    <h2 class="mt-1 text-2xl font-bold">{{ $case->case_code }}</h2>
                    <p class="mt-1 text-sm text-slate-700">{{ $case->institution?->name ?: 'Institucion no registrada' }} / {{ $case->surgeryType?->name ?: 'Cirugia no registrada' }}</p>
                </div>
                <p class="text-sm font-semibold">{{ $case->scheduled_at?->format('d/m/Y H:i') ?: 'Fecha por confirmar' }}</p>
            </div>
            <div class="mt-6 border-2 border-slate-900 px-4 py-5 text-center">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Codigo escaneable del caso</p>
                <p class="mt-2 break-all font-mono text-xl font-bold tracking-wide">{{ $case->trace_code }}</p>
            </div>
        </section>

        <h2 class="mt-8 text-lg font-semibold text-slate-900">Lotes reservados</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($case->reservations as $reservation)
                @php($lot = $reservation->inventoryLot)
                <article class="border-2 border-slate-700 bg-white p-5 text-slate-900" style="break-inside: avoid;">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-600">Material reservado / {{ $reservation->quantity }} unidad(es)</p>
                    <p class="mt-2 text-sm font-semibold">{{ $lot?->product?->product_code ?: 'Sin codigo' }}</p>
                    <p class="mt-1 text-sm">{{ $lot?->product?->name ?: 'Producto no registrado' }}</p>
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-xs">
                        <div><dt class="font-semibold uppercase text-slate-500">Lote</dt><dd class="mt-1 font-medium">{{ $lot?->lot ?: 'Sin lote' }}</dd></div>
                        <div><dt class="font-semibold uppercase text-slate-500">Serie</dt><dd class="mt-1 font-medium">{{ $lot?->serial ?: 'No aplica' }}</dd></div>
                        <div><dt class="font-semibold uppercase text-slate-500">Almacen</dt><dd class="mt-1 font-medium">{{ $lot?->warehouse?->name ?: 'No registrado' }}</dd></div>
                        <div><dt class="font-semibold uppercase text-slate-500">Estado</dt><dd class="mt-1 font-medium">{{ \Illuminate\Support\Str::headline($lot?->status?->value ?: 'sin estado') }}</dd></div>
                    </dl>
                    <div class="mt-5 border-2 border-slate-900 px-3 py-4 text-center">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Codigo escaneable del lote</p>
                        <p class="mt-2 break-all font-mono text-sm font-bold tracking-wide">{{ $lot?->trace_code ?: 'Sin codigo generado' }}</p>
                    </div>
                </article>
            @empty
                <p class="text-sm text-slate-600">No hay lotes reservados para este caso.</p>
            @endforelse
        </div>
    </main>
</body>
</html>
