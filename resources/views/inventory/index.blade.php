@extends('layouts.ops')

@section('title', 'Inventario | OPS BIOMED MR8')

@section('content')
    @php
        $statusLabels = [
            'apto' => 'Disponible',
            'reservado' => 'Reservado',
            'bloqueado' => 'Bloqueado',
            'cuarentena' => 'Cuarentena',
            'falla_preventiva' => 'Falla preventiva',
            'vencido' => 'Vencido',
            'desvalorizado' => 'Desvalorizado',
            'observado' => 'Observado',
        ];
        $statusClasses = [
            'apto' => 'bg-emerald-50 text-emerald-700',
            'reservado' => 'bg-sky-50 text-sky-700',
            'bloqueado' => 'bg-rose-50 text-rose-700',
            'cuarentena' => 'bg-amber-50 text-amber-700',
            'falla_preventiva' => 'bg-rose-50 text-rose-700',
            'vencido' => 'bg-rose-50 text-rose-700',
            'desvalorizado' => 'bg-slate-100 text-slate-600',
            'observado' => 'bg-orange-50 text-orange-700',
        ];
    @endphp

    <div class="space-y-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-sky-700">Control de existencias</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Inventario MR8</h1>
                <p class="mt-2 text-sm text-slate-500">Consulta de lotes, reservas activas y disponibilidad neta.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('manual.view')
                    <a href="{{ route('manual.show', 'buscar-inventario') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Guia de inventario</a>
                @endcan
                @can('catalog.import')
                    <a href="{{ route('catalog.imports.index') }}" class="ops-button-primary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Importar catalogo</a>
                @endcan
            </div>
        </div>

        <section class="border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('inventory.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="w-full sm:max-w-xs">
                    <label for="inventory-status" class="text-sm font-semibold text-slate-700">Filtrar por estado</label>
                    <select id="inventory-status" name="status" class="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                        <option value="">Todos los estados</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Aplicar filtro</button>
                @if ($selectedStatus !== '')
                    <a href="{{ route('inventory.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Limpiar</a>
                @endif
            </form>
        </section>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Lotes registrados</h2>
                <p class="mt-1 text-sm text-slate-500">La cantidad solo se modifica desde Ajuste de inventario.</p>
            </div>

            @if ($lots->isEmpty())
                <p class="px-5 py-8 text-sm text-slate-500">No hay inventario registrado.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Producto</th>
                                <th class="px-5 py-3 font-semibold">Lote / serie</th>
                                <th class="px-5 py-3 font-semibold">Vencimiento</th>
                                <th class="px-5 py-3 font-semibold">Almacen / ubicacion</th>
                                <th class="px-5 py-3 font-semibold">Stock</th>
                                <th class="px-5 py-3 font-semibold">Estado</th>
                                <th class="px-5 py-3 font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($lots as $lot)
                                @php
                                    $statusValue = $lot->status?->value ?? (string) $lot->status;
                                    $statusLabel = $statusLabels[$statusValue] ?? ucfirst($statusValue);
                                @endphp
                                <tr class="align-top hover:bg-slate-50">
                                    <td class="px-5 py-4">
                                        <a href="{{ route('inventory.show', $lot) }}" class="font-semibold text-sky-700 hover:text-sky-900">{{ $lot->product?->product_code }}</a>
                                        <p class="mt-1 max-w-xs text-slate-700">{{ $lot->product?->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $lot->product?->classification ?? 'Sin clasificar' }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">
                                        {{ $lot->lot ?: 'Sin lote' }}
                                        @if ($lot->serial)
                                            <p class="mt-1 text-xs text-slate-500">Serie: {{ $lot->serial }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $lot->expiry?->format('d/m/Y') ?? 'N/A' }}</td>
                                    <td class="px-5 py-4 text-slate-700">
                                        {{ $lot->warehouse?->name }}
                                        <p class="mt-1 text-xs text-slate-500">{{ $lot->location ?: 'Sin ubicacion' }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">
                                        <p>Total: {{ $lot->quantity }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Reservado: {{ $lot->reserved_active ?? 0 }}</p>
                                        <p class="mt-1 font-semibold text-slate-900">Neto: {{ $lot->available_net }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$statusValue] ?? 'bg-slate-100 text-slate-600' }}">{{ $statusLabel }}</span>
                                        @if ($lot->eligible_flag)
                                            <p class="mt-2 text-xs font-semibold text-emerald-700">Elegible inmediato</p>
                                        @else
                                            <p class="mt-2 text-xs font-semibold text-slate-500">No elegible</p>
                                        @endif
                                        @if ($lot->block_reason)
                                            <p class="mt-1 max-w-xs text-xs text-rose-700">{{ $lot->block_reason }}</p>
                                        @endif
                                        @if (($lot->negative_issues ?? collect())->isNotEmpty())
                                            <p class="mt-1 max-w-xs text-xs font-semibold text-orange-700">Tiene inconsistencias de cantidad negativa</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <div class="flex flex-col items-start gap-2">
                                            <a href="{{ route('inventory.show', $lot) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ver detalle</a>
                                            @can('update', $lot)
                                                <a href="{{ route('inventory.edit', $lot) }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950">Editar datos</a>
                                            @endcan
                                            @can('adjust', $lot)
                                                <a href="{{ route('inventory.adjust.create', $lot) }}" class="text-sm font-semibold text-amber-700 hover:text-amber-900">Ajustar stock</a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-4">{{ $lots->links() }}</div>
            @endif
        </section>
    </div>
@endsection
