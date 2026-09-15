@extends('layouts.ops')

@section('title', 'Editar inventario | OPS BIOMED MR8')

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
    @endphp

    <div class="mx-auto max-w-4xl space-y-8">
        <div>
            <a href="{{ route('inventory.show', $lot) }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al detalle</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Editar datos de inventario</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $lot->product?->product_code }} / {{ $lot->lot ?: 'Sin lote' }}. El codigo, lote, serie y cantidades son inmutables en este formulario.</p>
        </div>

        @if ($hasActiveReservations)
            <div class="border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Este lote tiene reservas activas. Estado, vencimientos y clasificacion quedan bloqueados hasta liberar o cerrar las reservas.</div>
        @endif

        @if ($errors->any())
            <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-semibold">No se pudo guardar el cambio.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('inventory.update', $lot) }}" class="space-y-6">
            @csrf
            @method('PATCH')

            <section class="border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Datos no editables</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Codigo de producto</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->product?->product_code }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Producto</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->product?->name }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lote</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->lot ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Serie</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->serial ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Stock total</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->quantity }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Almacen</dt><dd class="mt-1 text-sm text-slate-900">{{ $lot->warehouse?->name }}</dd></div>
                </dl>
            </section>

            <section class="border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Producto</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="name" class="text-sm font-semibold text-slate-700">Nombre</label>
                        <input id="name" name="name" value="{{ old('name', $lot->product?->name) }}" required class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900">
                    </div>
                    <div>
                        <label for="subfamily" class="text-sm font-semibold text-slate-700">Subfamilia</label>
                        <input id="subfamily" name="subfamily" value="{{ old('subfamily', $lot->product?->subfamily) }}" class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900">
                    </div>
                    <div>
                        <label for="regulatory_record" class="text-sm font-semibold text-slate-700">Registro sanitario</label>
                        <input id="regulatory_record" name="regulatory_record" value="{{ old('regulatory_record', $lot->product?->regulatory_record) }}" class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900">
                    </div>
                    <div>
                        <label for="regulatory_expiry" class="text-sm font-semibold text-slate-700">Vencimiento regsan</label>
                        <input id="regulatory_expiry" type="date" name="regulatory_expiry" value="{{ old('regulatory_expiry', $lot->product?->regulatory_expiry?->format('Y-m-d')) }}" @disabled($hasActiveReservations) class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 disabled:bg-slate-100">
                    </div>
                    <div>
                        <label for="classification" class="text-sm font-semibold text-slate-700">Clasificacion</label>
                        <select id="classification" name="classification" @disabled($hasActiveReservations) class="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 disabled:bg-slate-100">
                            @foreach (['consumible' => 'Consumible', 'reusable' => 'Reusable', 'equipo' => 'Equipo', 'accesorio' => 'Accesorio', 'instrumental' => 'Instrumental'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('classification', $lot->product?->classification) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-3 sm:col-span-2">
                        @if (! $hasActiveReservations)
                            <input type="hidden" name="expiry_required" value="0">
                            <input id="expiry_required" type="checkbox" name="expiry_required" value="1" @checked(old('expiry_required', $lot->product?->expiry_required)) class="h-4 w-4 border-slate-300 text-sky-700">
                            <label for="expiry_required" class="text-sm font-semibold text-slate-700">El lote requiere vencimiento</label>
                        @else
                            <span class="text-sm text-slate-600">Vencimiento requerido: <strong>{{ $lot->product?->expiry_required ? 'Si' : 'No' }}</strong></span>
                        @endif
                    </div>
                </div>
            </section>

            <section class="border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Lote y estado</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="detail_expiry" class="text-sm font-semibold text-slate-700">Vencimiento del lote</label>
                        <input id="detail_expiry" type="date" name="detail_expiry" value="{{ old('detail_expiry', $lot->expiry?->format('Y-m-d')) }}" @disabled($hasActiveReservations) class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 disabled:bg-slate-100">
                    </div>
                    <div>
                        <label for="location" class="text-sm font-semibold text-slate-700">Ubicacion</label>
                        <input id="location" name="location" value="{{ old('location', $lot->location) }}" class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900">
                    </div>
                    <div>
                        <label for="status" class="text-sm font-semibold text-slate-700">Estado operativo</label>
                        <select id="status" name="status" @disabled($hasActiveReservations) class="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 disabled:bg-slate-100">
                            @foreach ($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $lot->status?->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="block_reason" class="text-sm font-semibold text-slate-700">Motivo de bloqueo</label>
                        <input id="block_reason" name="block_reason" value="{{ old('block_reason', $lot->block_reason) }}" class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="observations" class="text-sm font-semibold text-slate-700">Observaciones tecnicas</label>
                        <textarea id="observations" name="observations" rows="4" class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900">{{ old('observations', $lot->observations) }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="change_reason" class="text-sm font-semibold text-slate-700">Justificacion del cambio de estado o vencimiento</label>
                        <textarea id="change_reason" name="change_reason" rows="3" class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900">{{ old('change_reason') }}</textarea>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('inventory.show', $lot) }}" class="border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button type="submit" class="bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
