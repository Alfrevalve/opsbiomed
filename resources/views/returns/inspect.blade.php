@extends('layouts.ops')

@section('title', 'Inspeccionar devolucion | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <a href="{{ route('returns.show', $return) }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al detalle</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Inspeccionar devolucion #{{ $return->id }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $return->inventoryLot?->product?->product_code }} / lote {{ $return->inventoryLot?->lot ?: 'N/A' }} · {{ $return->returned_qty }} unidad(es) devuelta(s).</p>
        </div>

        @if ($errors->any())
            <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-semibold">No se pudo registrar la inspeccion.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Datos de retorno</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $return->case?->case_code }} · {{ $return->case?->institution?->name }} · {{ $return->case?->doctor?->name }}</p>
            </div>
            <dl class="grid gap-x-6 gap-y-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Producto</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->inventoryLot?->product?->name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Codigo</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->inventoryLot?->product?->product_code }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Serie</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->inventoryLot?->serial ?: 'N/A' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Almacen</dt><dd class="mt-1 text-sm text-slate-900">{{ $return->inventoryLot?->warehouse?->name }}</dd></div>
            </dl>
        </section>

        <form method="POST" action="{{ route('returns.inspect', $return) }}" class="space-y-6">
            @csrf
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-950">Resultado de inspeccion</h2>
                    <p class="mt-1 text-sm text-slate-500">La liberacion, el bloqueo, la desvalorizacion y la baja requieren decision tecnica autorizada.</p>
                </div>
                <div class="grid gap-5 p-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="inspection_result" class="block text-sm font-medium text-slate-700">Resultado <span class="text-rose-600">*</span></label>
                        <select id="inspection_result" name="inspection_result" required class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Seleccionar resultado</option>
                            @foreach ($inspectionResults as $value => $label)
                                <option value="{{ $value }}" @selected(old('inspection_result') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('inspection_result')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="inspection_observations" class="block text-sm font-medium text-slate-700">Observaciones <span class="text-rose-600">*</span></label>
                        <textarea id="inspection_observations" name="inspection_observations" rows="5" required maxlength="5000" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Describe el estado fisico, esterilidad, empaque y decision tomada.">{{ old('inspection_observations') }}</textarea>
                        @error('inspection_observations')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="inspection_responsible_id" class="block text-sm font-medium text-slate-700">Responsable <span class="text-rose-600">*</span></label>
                        <select id="inspection_responsible_id" name="inspection_responsible_id" required class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            @foreach ($responsibleUsers as $responsibleUser)
                                <option value="{{ $responsibleUser->id }}" @selected((int) old('inspection_responsible_id', auth()->id()) === $responsibleUser->id)>{{ $responsibleUser->name }}</option>
                            @endforeach
                        </select>
                        @error('inspection_responsible_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="inspection_date" class="block text-sm font-medium text-slate-700">Fecha de inspeccion <span class="text-rose-600">*</span></label>
                        <input id="inspection_date" name="inspection_date" type="datetime-local" required value="{{ old('inspection_date', now()->format('Y-m-d\TH:i')) }}" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        @error('inspection_date')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    @can('trace.scan')
                        <div class="sm:col-span-2">
                            <label for="inventory_lot_trace_code" class="block text-sm font-medium text-slate-700">Validar lote por escaneo (opcional)</label>
                            <input id="inventory_lot_trace_code" name="inventory_lot_trace_code" type="text" maxlength="160" value="{{ old('inventory_lot_trace_code') }}" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="{{ $return->inventoryLot?->trace_code ?: 'Escanea la etiqueta del lote devuelto' }}" class="mt-1.5 block w-full border-slate-300 font-mono text-sm uppercase shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            <p class="mt-1.5 text-xs text-slate-500">El codigo debe coincidir con el lote asociado a esta devolucion.</p>
                            @error('inventory_lot_trace_code')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    @endcan
                    <div class="sm:col-span-2">
                        <label for="inspection_evidence_reference" class="block text-sm font-medium text-slate-700">Evidencia o link (opcional)</label>
                        <input id="inspection_evidence_reference" name="inspection_evidence_reference" type="text" maxlength="500" value="{{ old('inspection_evidence_reference') }}" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Acta, fotografia, ubicacion o enlace de evidencia">
                        @error('inspection_evidence_reference')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3">
                <a href="{{ route('returns.show', $return) }}" class="border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button type="submit" class="bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Registrar inspeccion</button>
            </div>
        </form>
    </div>
@endsection
