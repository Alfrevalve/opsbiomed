@php
    $editing = isset($institution);
    $institutionTypesLabels = [
        'hospital_publico' => 'Hospital publico',
        'clinica_privada' => 'Clinica privada',
        'instituto' => 'Instituto',
        'centro_medico' => 'Centro medico',
        'otro' => 'Otro',
    ];
    $commercialConditionLabels = [
        'regular' => 'Regular',
        'convenio' => 'Convenio',
        'cesion_uso' => 'Cesion de uso',
        'licitacion' => 'Licitacion',
        'costo_cero_autorizado' => 'Costo cero autorizado',
        'suspendida' => 'Suspendida',
    ];
    $debtStatusLabels = [
        'al_dia' => 'Al dia',
        'observada' => 'Observada',
        'vencida' => 'Vencida',
        'bloqueada' => 'Bloqueada',
    ];
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="name" class="block text-sm font-medium text-slate-700">Nombre <span class="text-rose-600">*</span></label>
        <input id="name" name="name" type="text" value="{{ old('name', $institution->name ?? '') }}" required maxlength="180" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        @error('name')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="ruc" class="block text-sm font-medium text-slate-700">RUC</label>
        <input id="ruc" name="ruc" type="text" value="{{ old('ruc', $institution->ruc ?? '') }}" maxlength="20" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        @error('ruc')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="institution_type" class="block text-sm font-medium text-slate-700">Tipo <span class="text-rose-600">*</span></label>
        <select id="institution_type" name="institution_type" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            @foreach ($institutionTypes as $value)
                <option value="{{ $value }}" @selected(old('institution_type', $institution->institution_type ?? 'otro') === $value)>{{ $institutionTypesLabels[$value] }}</option>
            @endforeach
        </select>
        @error('institution_type')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-2">
        <label for="address" class="block text-sm font-medium text-slate-700">Direccion</label>
        <input id="address" name="address" type="text" value="{{ old('address', $institution->address ?? '') }}" maxlength="255" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        @error('address')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    @foreach (['sop_contact' => 'Contacto SOP', 'pharmacy_contact' => 'Contacto farmacia', 'billing_contact' => 'Contacto facturacion', 'phone' => 'Telefono', 'email' => 'Email'] as $field => $label)
        <div>
            <label for="{{ $field }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ old($field, $institution->{$field} ?? '') }}" maxlength="{{ $field === 'phone' ? 50 : 180 }}" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            @error($field)<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>
    @endforeach
    <div>
        <label for="billing_policy" class="block text-sm font-medium text-slate-700">Condicion comercial <span class="text-rose-600">*</span></label>
        <select id="billing_policy" name="billing_policy" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            @foreach ($commercialConditions as $value)
                <option value="{{ $value }}" @selected(old('billing_policy', $institution->billing_policy ?? 'regular') === $value)>{{ $commercialConditionLabels[$value] }}</option>
            @endforeach
        </select>
        @error('billing_policy')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="debt_status" class="block text-sm font-medium text-slate-700">Estado deuda <span class="text-rose-600">*</span></label>
        <select id="debt_status" name="debt_status" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            @foreach ($debtStatuses as $value)
                <option value="{{ $value }}" @selected(old('debt_status', $institution->debt_status ?? 'al_dia') === $value)>{{ $debtStatusLabels[$value] }}</option>
            @endforeach
        </select>
        @error('debt_status')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-2">
        <label for="observations" class="block text-sm font-medium text-slate-700">Observaciones</label>
        <textarea id="observations" name="observations" rows="4" maxlength="2000" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('observations', $institution->observations ?? '') }}</textarea>
        @error('observations')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-2">
        <label class="inline-flex items-center gap-3 text-sm font-medium text-slate-700">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" @checked(old('active', $institution->active ?? true)) class="border-slate-300 text-sky-700 focus:ring-sky-500">
            Institucion activa
        </label>
        @error('active')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
    <a href="{{ route('masters.institutions.index') }}" class="inline-flex items-center justify-center border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
    <button type="submit" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-800">{{ $editing ? 'Guardar cambios' : 'Crear institucion' }}</button>
</div>
