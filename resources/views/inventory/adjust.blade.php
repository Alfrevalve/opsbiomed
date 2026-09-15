@extends('layouts.ops')

@section('title', 'Ajustar inventario | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-3xl space-y-8">
        <div>
            <a href="{{ route('inventory.show', $lot) }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al detalle</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Ajuste de inventario</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $lot->product?->product_code }} / {{ $lot->lot ?: 'Sin lote' }}. El ajuste queda vinculado al usuario, fecha, motivo y auditoria.</p>
        </div>

        <section class="grid gap-4 sm:grid-cols-3">
            <article class="border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Stock actual</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ $lot->quantity }}</p></article>
            <article class="border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Estado</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ $lot->status?->value }}</p></article>
            <article class="border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Almacen</p><p class="mt-2 text-sm font-semibold text-slate-950">{{ $lot->warehouse?->name }}</p></article>
        </section>

        @if ($errors->any())
            <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('inventory.adjust', $lot) }}" enctype="multipart/form-data" class="border border-slate-200 bg-white p-5 shadow-sm">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="adjustment_type" class="text-sm font-semibold text-slate-700">Tipo de ajuste</label>
                    <select id="adjustment_type" name="adjustment_type" required class="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900">
                        @foreach (['conteo_fisico' => 'Conteo fisico', 'correccion_importacion' => 'Correccion de importacion', 'devolucion' => 'Devolucion', 'perdida' => 'Perdida', 'baja' => 'Baja', 'traslado' => 'Traslado', 'canje' => 'Canje'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('adjustment_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="quantity_adjustment" class="text-sm font-semibold text-slate-700">Cantidad firmada</label>
                    <input id="quantity_adjustment" type="number" name="quantity_adjustment" value="{{ old('quantity_adjustment') }}" required step="1" min="-2147483648" max="2147483647" class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900">
                    <p class="mt-1 text-xs text-slate-500">Usa positivo para sumar y negativo para restar.</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="reason" class="text-sm font-semibold text-slate-700">Motivo obligatorio</label>
                    <textarea id="reason" name="reason" rows="4" required class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900">{{ old('reason') }}</textarea>
                </div>
                <div>
                    <label for="adjusted_at" class="text-sm font-semibold text-slate-700">Fecha y hora</label>
                    <input id="adjusted_at" type="datetime-local" name="adjusted_at" value="{{ old('adjusted_at', now()->format('Y-m-d\TH:i')) }}" required class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900">
                </div>
                <div>
                    <label for="evidence" class="text-sm font-semibold text-slate-700">Evidencia opcional</label>
                    <input id="evidence" type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                </div>
                @can('inventory.audit')
                    <div class="flex items-start gap-3 sm:col-span-2">
                        <input id="allow_inconsistency" type="checkbox" name="allow_inconsistency" value="1" @checked(old('allow_inconsistency')) class="mt-1 h-4 w-4 border-slate-300 text-sky-700">
                        <label for="allow_inconsistency" class="text-sm text-slate-700">Registrar explicitamente una inconsistencia de stock negativo</label>
                    </div>
                @endcan
            </div>
            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <a href="{{ route('inventory.show', $lot) }}" class="border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button type="submit" class="bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-800">Registrar ajuste</button>
            </div>
        </form>
    </div>
@endsection
