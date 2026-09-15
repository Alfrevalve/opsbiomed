@php
    $editing = isset($price);
    $priceTypeLabels = ['lista' => 'Lista', 'convenio' => 'Convenio', 'licitacion' => 'Licitacion', 'especial' => 'Especial', 'cesion_uso' => 'Cesion de uso', 'costo_cero' => 'Costo cero'];
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="product_id" class="block text-sm font-medium text-slate-700">Producto <span class="text-rose-600">*</span></label>
        <select id="product_id" name="product_id" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            <option value="">Selecciona un producto</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected(old('product_id', $price->product_id ?? '') == $product->id)>{{ $product->product_code }} - {{ $product->name }}</option>
            @endforeach
        </select>
        @error('product_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="unit_price" class="block text-sm font-medium text-slate-700">Precio lista <span class="text-rose-600">*</span></label>
        <input id="unit_price" name="unit_price" type="number" min="0" step="0.01" value="{{ old('unit_price', $price->unit_price ?? '') }}" required class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        @error('unit_price')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="minimum_price" class="block text-sm font-medium text-slate-700">Precio minimo autorizado</label>
        <input id="minimum_price" name="minimum_price" type="number" min="0" step="0.01" value="{{ old('minimum_price', $price->minimum_price ?? '') }}" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        @error('minimum_price')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="currency" class="block text-sm font-medium text-slate-700">Moneda <span class="text-rose-600">*</span></label>
        <select id="currency" name="currency" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"><option value="PEN" @selected(old('currency', $price->currency ?? 'PEN') === 'PEN')>PEN</option><option value="USD" @selected(old('currency', $price->currency ?? 'PEN') === 'USD')>USD</option></select>
        @error('currency')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="price_type" class="block text-sm font-medium text-slate-700">Tipo de precio <span class="text-rose-600">*</span></label>
        <select id="price_type" name="price_type" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            @foreach ($priceTypes as $value)
                <option value="{{ $value }}" @selected(old('price_type', $price->price_type ?? 'lista') === $value)>{{ $priceTypeLabels[$value] }}</option>
            @endforeach
        </select>
        @error('price_type')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="institution_id" class="block text-sm font-medium text-slate-700">Institucion</label>
        <select id="institution_id" name="institution_id" class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"><option value="">Precio general</option>@foreach ($institutions as $institution)<option value="{{ $institution->id }}" @selected(old('institution_id', $price->institution_id ?? '') == $institution->id)>{{ $institution->name }}{{ $institution->active ? '' : ' (inactiva)' }}</option>@endforeach</select>
        @error('institution_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="doctor_id" class="block text-sm font-medium text-slate-700">Medico</label>
        <select id="doctor_id" name="doctor_id" class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"><option value="">Precio general</option>@foreach ($doctors as $doctor)<option value="{{ $doctor->id }}" @selected(old('doctor_id', $price->doctor_id ?? '') == $doctor->id)>{{ $doctor->name }}{{ $doctor->active ? '' : ' (inactivo)' }}</option>@endforeach</select>
        @error('doctor_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="valid_from" class="block text-sm font-medium text-slate-700">Vigencia desde <span class="text-rose-600">*</span></label>
        <input id="valid_from" name="valid_from" type="date" value="{{ old('valid_from', isset($price) && $price->valid_from ? $price->valid_from->format('Y-m-d') : now()->format('Y-m-d')) }}" required class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        @error('valid_from')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="valid_until" class="block text-sm font-medium text-slate-700">Vigencia hasta</label>
        <input id="valid_until" name="valid_until" type="date" value="{{ old('valid_until', isset($price) && $price->valid_until ? $price->valid_until->format('Y-m-d') : '') }}" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        @error('valid_until')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-2">
        <label for="observations" class="block text-sm font-medium text-slate-700">Observaciones <span id="cost-zero-hint" class="hidden text-amber-700">(obligatorias para costo cero)</span></label>
        <textarea id="observations" name="observations" rows="4" maxlength="2000" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('observations', $price->observations ?? '') }}</textarea>
        @error('observations')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-2"><label class="inline-flex items-center gap-3 text-sm font-medium text-slate-700"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $price->active ?? true)) class="border-slate-300 text-sky-700 focus:ring-sky-500">Precio activo</label>@error('active')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
</div>

<div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end"><a href="{{ route('masters.prices.index') }}" class="inline-flex items-center justify-center border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a><button type="submit" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-800">{{ $editing ? 'Guardar cambios' : 'Crear precio' }}</button></div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const type = document.getElementById('price_type');
        const hint = document.getElementById('cost-zero-hint');
        const updateHint = () => hint.classList.toggle('hidden', type.value !== 'costo_cero');
        type.addEventListener('change', updateHint);
        updateHint();
    });
</script>
