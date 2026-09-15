@extends('layouts.ops')

@section('title', 'Forecast y reposicion | OPS BIOMED MR8')

@section('content')
    <div class="space-y-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-sky-700">Planificacion de inventario</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Forecast y reposicion MR8</h1>
                <p class="mt-2 text-sm text-slate-500">Recomendaciones basadas en stock inmediato neto, reservas activas y reglas criticas de kit.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @can('manual.view')
                    <a href="{{ route('manual.show', 'analizar-cobertura-y-forecast') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Guia de cobertura</a>
                @endcan
                <a href="{{ route('inventory.coverage') }}" class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Ver cobertura</a>
                <a href="{{ route('inventory.forecast.export', $filters) }}" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-800">Exportar CSV</a>
            </div>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6" aria-label="Resumen ejecutivo del forecast">
            @foreach ([
                ['label' => 'Items criticos', 'value' => $forecast['items_critical'], 'class' => 'border-rose-200 bg-rose-50 text-rose-900'],
                ['label' => 'Items alta prioridad', 'value' => $forecast['items_high'], 'class' => 'border-amber-200 bg-amber-50 text-amber-900'],
                ['label' => 'Items cubiertos', 'value' => $forecast['items_covered'], 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-900'],
                ['label' => 'Compra sugerida', 'value' => $forecast['suggested_purchase_total'], 'class' => 'border-slate-200 bg-white text-slate-950'],
                ['label' => 'Cirugias afectadas', 'value' => $forecast['affected_surgery_types'], 'class' => 'border-rose-200 bg-white text-rose-900'],
                ['label' => 'YSAN de respaldo', 'value' => $forecast['ysan_support_available'], 'class' => 'border-sky-200 bg-sky-50 text-sky-900'],
            ] as $metric)
                <article class="border p-4 shadow-sm {{ $metric['class'] }}">
                    <p class="text-sm font-medium">{{ $metric['label'] }}</p>
                    <p class="mt-3 text-2xl font-semibold">{{ $metric['value'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Filtros</h2>
            </div>
            <form method="GET" action="{{ route('inventory.forecast') }}" class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5">
                <label class="text-sm font-medium text-slate-700">
                    Tipo de cirugia
                    <select name="surgery_type" class="mt-2 block w-full border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($forecast['filter_options']['surgery_types'] as $type)
                            <option value="{{ $type['code'] }}" @selected(($filters['surgery_type'] ?? '') === $type['code'])>{{ $type['name'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Urgencia
                    <select name="urgency" class="mt-2 block w-full border-slate-300 text-sm">
                        <option value="">Todas</option>
                        @foreach ($forecast['filter_options']['urgencies'] as $urgency)
                            <option value="{{ $urgency['value'] }}" @selected(($filters['urgency'] ?? '') === $urgency['value'])>{{ $urgency['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Longitud
                    <select name="length" class="mt-2 block w-full border-slate-300 text-sm">
                        <option value="">Todas</option>
                        @foreach ($forecast['filter_options']['lengths'] as $length)
                            <option value="{{ $length }}" @selected((string) ($filters['length'] ?? '') === $length)>{{ $length }} cm</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Diametro
                    <select name="diameter" class="mt-2 block w-full border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($forecast['filter_options']['diameters'] as $diameter)
                            <option value="{{ $diameter }}" @selected((string) ($filters['diameter'] ?? '') === $diameter)>{{ $diameter }} mm</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Tipo de producto
                    <select name="product_type" class="mt-2 block w-full border-slate-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($forecast['filter_options']['product_types'] as $productType)
                            <option value="{{ $productType }}" @selected(($filters['product_type'] ?? '') === $productType)>{{ ucfirst($productType) }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-5">
                    <button type="submit" class="bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Aplicar filtros</button>
                    <a href="{{ route('inventory.forecast') }}" class="border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpiar</a>
                </div>
            </form>
        </section>

        @if ($forecast['affected_type_summary'] !== [])
            <section class="border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Tipos de cirugia mas afectados</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($forecast['affected_type_summary'] as $type)
                        <div class="flex items-center justify-between border border-rose-200 bg-rose-50 px-4 py-3">
                            <span class="text-sm font-semibold text-rose-900">{{ $type['name'] }}</span>
                            <span class="text-sm font-semibold text-rose-700">{{ $type['items'] }} items</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Plan de reposicion por combinacion critica</h2>
                <p class="mt-1 text-sm text-slate-500">Minimo: {{ $forecast['minimum'] }} cirugias. Objetivo: {{ $forecast['target'] }} cirugias. YSAN se muestra solo como respaldo.</p>
            </div>
            @if ($forecast['rows']->isEmpty())
                <p class="px-5 py-8 text-sm text-slate-500">No hay combinaciones que coincidan con los filtros seleccionados.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-[2200px] divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Tipo de cirugía</th>
                                <th class="px-4 py-3 font-semibold">Código de producto</th>
                                <th class="px-4 py-3 font-semibold">Producto</th>
                                <th class="px-4 py-3 font-semibold">Familia</th>
                                <th class="px-4 py-3 font-semibold">Subfamilia</th>
                                <th class="px-4 py-3 font-semibold">Longitud cm</th>
                                <th class="px-4 py-3 font-semibold">Diámetro mm</th>
                                <th class="px-4 py-3 font-semibold">Tipo</th>
                                <th class="px-4 py-3 font-semibold">Inmediato neto</th>
                                <th class="px-4 py-3 font-semibold">YSAN</th>
                                <th class="px-4 py-3 font-semibold">Reservado</th>
                                <th class="px-4 py-3 font-semibold">Mínimo</th>
                                <th class="px-4 py-3 font-semibold">Objetivo</th>
                                <th class="px-4 py-3 font-semibold">Déficit min.</th>
                                <th class="px-4 py-3 font-semibold">Déficit obj.</th>
                                <th class="px-4 py-3 font-semibold">Compra sugerida</th>
                                <th class="px-4 py-3 font-semibold">Urgencia</th>
                                <th class="px-4 py-3 font-semibold">Acción</th>
                                <th class="px-4 py-3 font-semibold">Observación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($forecast['rows'] as $row)
                                @php
                                    $urgencyClass = match ($row['urgency']) {
                                        'critical' => 'bg-rose-50 text-rose-700',
                                        'high' => 'bg-amber-50 text-amber-700',
                                        default => 'bg-emerald-50 text-emerald-700',
                                    };
                                @endphp
                                <tr class="align-top hover:bg-slate-50">
                                    <td class="px-4 py-4 font-semibold text-slate-900">{{ $row['surgery_type'] }}</td>
                                    <td class="px-4 py-4">
                                        @if ($row['has_product_match'])
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono text-xs font-semibold text-slate-800">{{ $row['product_code'] }}</span>
                                                <button type="button" data-copy-code="{{ $row['product_code'] }}" class="border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50" title="Copiar codigo" aria-label="Copiar codigo {{ $row['product_code'] }}">Copiar</button>
                                            </div>
                                        @else
                                            <span class="inline-flex border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800">{{ $row['product_code'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-slate-700">
                                        <span>{{ $row['product_name'] }}</span>
                                        @if ($row['has_inconsistency'])
                                            <span class="mt-2 inline-flex bg-orange-50 px-2 py-1 text-xs font-semibold text-orange-700">Revisar inconsistencia ({{ $row['inconsistency_count'] }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-slate-700">{{ $row['family'] }}</td>
                                    <td class="px-4 py-4 text-slate-700">{{ $row['subfamily'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-4 text-slate-700">{{ $row['length_cm'] ?? 'Cualquiera' }} cm</td>
                                    <td class="whitespace-nowrap px-4 py-4 text-slate-700">{{ $row['diameter_mm'] ?? 'Cualquiera' }} mm</td>
                                    <td class="whitespace-nowrap px-4 py-4 text-slate-700">{{ ucfirst($row['product_type']) }}</td>
                                    <td class="px-4 py-4 font-semibold text-slate-900">{{ $row['stock_immediate_net'] }}</td>
                                    <td class="px-4 py-4 text-sky-700">{{ $row['stock_ysan'] }}</td>
                                    <td class="px-4 py-4 text-slate-700">{{ $row['reserved_active'] }}</td>
                                    <td class="px-4 py-4 text-slate-700">{{ $row['minimum'] }}</td>
                                    <td class="px-4 py-4 text-slate-700">{{ $row['target'] }}</td>
                                    <td class="px-4 py-4 text-rose-700">{{ $row['deficit_minimum'] }}</td>
                                    <td class="px-4 py-4 text-amber-700">{{ $row['deficit_target'] }}</td>
                                    <td class="px-4 py-4 font-semibold text-slate-900">{{ $row['suggested_purchase'] }}</td>
                                    <td class="px-4 py-4"><span class="inline-flex px-2.5 py-1 text-xs font-semibold {{ $urgencyClass }}">{{ $row['urgency_label'] }}</span></td>
                                    <td class="min-w-[260px] px-4 py-4 text-slate-700">{{ $row['action'] }}</td>
                                    <td class="min-w-[250px] px-4 py-4 text-sm {{ $row['has_product_match'] ? 'text-slate-500' : 'font-semibold text-amber-800' }}">{{ $row['observation'] ?: 'Sin observaciones' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <script>
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-copy-code]');

            if (!button) {
                return;
            }

            const code = button.dataset.copyCode;

            try {
                await navigator.clipboard.writeText(code);
                const originalLabel = button.textContent;
                button.textContent = 'Copiado';
                window.setTimeout(() => {
                    button.textContent = originalLabel;
                }, 1400);
            } catch (error) {
                button.textContent = 'No disponible';
            }
        });
    </script>
@endsection
