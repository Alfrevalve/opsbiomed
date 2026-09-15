@extends('layouts.ops')

@section('title', 'Reservar material | '.$case->case_code)

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('cases.show', $case) }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al detalle</a>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Reservar material</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $case->case_code }} · {{ $case->surgeryType?->name }} · {{ $case->patient?->full_name }}</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Riesgo de ruptura</h2>
                <p class="mt-1 text-sm text-slate-500">El semaforo usa cobertura en cirugias por combinacion exacta.</p>
            </div>
            <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($reservationOverview['risks'] as $risk)
                    @php
                        $riskClass = match ($risk['risk']) {
                            'green' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                            'yellow' => 'border-amber-200 bg-amber-50 text-amber-800',
                            default => 'border-rose-200 bg-rose-50 text-rose-800',
                        };
                    @endphp
                    <div class="border p-4 {{ $riskClass }}">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold">{{ $risk['risk_label'] }}</p>
                            <span class="text-xs font-semibold">{{ $risk['coverage'] }} cirugias</span>
                        </div>
                        <p class="mt-2 text-xs">{{ $risk['label'] }}</p>
                        <p class="mt-2 text-xs">Neto: {{ $risk['available_net'] }} · Para este caso: {{ $risk['reserved_for_case'] }}/{{ $risk['units_per_surgery'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No hay reglas de material configuradas para este tipo de cirugia.</p>
                @endforelse
            </div>
        </section>

        @can('trace.scan')
        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Reserva por escaneo</h2>
                <p class="mt-1 text-sm text-slate-500">Escanea la etiqueta del lote para seleccionarlo. La disponibilidad se valida al reservar.</p>
            </div>
            <form method="POST" action="{{ route('cases.reserve', $case) }}" class="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end">
                @csrf
                <label class="text-sm font-medium text-slate-700" for="inventory_lot_trace_code">
                    Codigo trazable del lote
                    <input id="inventory_lot_trace_code" name="inventory_lot_trace_code" type="text" maxlength="160" value="{{ old('inventory_lot_trace_code') }}" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="LOT-123-MR8-9BA30" class="mt-2 block w-full border-slate-300 font-mono text-sm uppercase shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    @error('inventory_lot_trace_code')<span class="mt-1 block text-xs font-medium text-rose-700">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm font-medium text-slate-700" for="scanned-quantity">
                    Cantidad
                    <input id="scanned-quantity" name="quantity" type="number" min="1" step="1" required value="{{ old('inventory_lot_trace_code') ? old('quantity', 1) : 1 }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </label>
                <button type="submit" class="inline-flex items-center justify-center bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Reservar lote</button>
            </form>
        </section>
        @endcan

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Lotes elegibles</h2>
                <p class="mt-1 text-sm text-slate-500">Solo se muestran lotes aptos, vigentes, no bloqueados y de almacenes de disponibilidad inmediata.</p>
            </div>
            @if ($reservationOverview['lots']->isEmpty())
                <p class="px-5 py-8 text-sm text-slate-500">No hay lotes elegibles con disponible neto para este caso.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Producto</th>
                                <th class="px-5 py-3 font-semibold">Codigo</th>
                                <th class="px-5 py-3 font-semibold">Codigo trazable</th>
                                <th class="px-5 py-3 font-semibold">Lote</th>
                                <th class="px-5 py-3 font-semibold">Serie</th>
                                <th class="px-5 py-3 font-semibold">Vencimiento</th>
                                <th class="px-5 py-3 font-semibold">Almacen</th>
                                <th class="px-5 py-3 font-semibold">Stock total</th>
                                <th class="px-5 py-3 font-semibold">Reservado activo</th>
                                <th class="px-5 py-3 font-semibold">Disponible neto</th>
                                <th class="px-5 py-3 font-semibold">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($reservationOverview['lots'] as $lot)
                                <tr class="align-middle hover:bg-slate-50">
                                    <td class="px-5 py-4 text-slate-700">{{ $lot->product?->name }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 font-medium text-slate-900">{{ $lot->product?->product_code }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-700">{{ $lot->trace_code ?: 'Pendiente' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $lot->lot ?? 'Sin lote' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $lot->serial ?? 'No aplica' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $lot->expiry?->format('d/m/Y') ?? 'Sin vencimiento' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $lot->warehouse?->name }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $lot->stock_total }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $lot->reserved_active }}</td>
                                    <td class="px-5 py-4 font-semibold text-slate-900">{{ $lot->available_net }}</td>
                                    <td class="px-5 py-4">
                                        <form method="POST" action="{{ route('cases.reserve', $case) }}" class="flex min-w-44 items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="inventory_lot_id" value="{{ $lot->id }}">
                                            <label class="sr-only" for="quantity-{{ $lot->id }}">Cantidad para {{ $lot->product?->product_code }}</label>
                                            <input id="quantity-{{ $lot->id }}" name="quantity" type="number" min="1" max="{{ $lot->available_net }}" value="{{ old('inventory_lot_id') == $lot->id ? old('quantity') : 1 }}" required class="w-20 border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                            <button type="submit" class="inline-flex items-center justify-center bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">Reservar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
