@php
    $isCaseRoute = isset($case);
    $formAction = $isCaseRoute ? route('cases.documents.store', $case) : route('documents.store');
@endphp

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    @unless ($isCaseRoute)
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium text-slate-700">
                Entidad relacionada
                <select name="documentable_type" required class="mt-1.5 block w-full border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Seleccionar entidad</option>
                    @foreach ($targetTypes as $targetType => $targetClass)
                        <option value="{{ $targetType }}" @selected(old('documentable_type') === $targetType)>{{ str_replace('_', ' ', ucfirst($targetType)) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">
                Registro relacionado
                <select name="documentable_id" required class="mt-1.5 block w-full border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Seleccionar registro</option>
                    @foreach ($targetOptions as $targetType => $options)
                        <optgroup label="{{ str_replace('_', ' ', ucfirst($targetType)) }}">
                            @foreach ($options as $option)
                                @php
                                    $optionLabel = match ($targetType) {
                                        'case' => $option->case_code.' - '.($option->institution?->name ?: 'Sin institucion'),
                                        'inventory_lot' => ($option->product?->product_code ?: 'Sin codigo').' - Lote '.($option->lot ?: 'Sin lote'),
                                        'failure' => 'Falla #'.$option->id.' - '.($option->product?->product_code ?: $option->inventoryLot?->product?->product_code ?: 'Sin producto'),
                                        'return' => 'Devolucion #'.$option->id.' - '.($option->case?->case_code ?: 'Sin caso'),
                                        'billing' => 'Facturacion #'.$option->id.' - '.($option->case?->case_code ?: 'Sin caso'),
                                        'approval' => 'Aprobacion #'.$option->id.' - '.($option->case?->case_code ?: 'Sin caso'),
                                        default => 'Registro #'.$option->id,
                                    };
                                @endphp
                                <option value="{{ $option->id }}" @selected((string) old('documentable_id') === (string) $option->id)>{{ $optionLabel }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs font-normal text-slate-500">Selecciona el tipo y el registro del mismo grupo.</span>
            </label>
        </div>
    @else
        <div class="border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-800">
            Documento asociado al caso <strong>{{ $case->case_code }}</strong>.
        </div>
    @endunless

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-sm font-medium text-slate-700">
            Tipo de documento
            <select name="document_type" required class="mt-1.5 block w-full border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">Seleccionar tipo</option>
                @foreach ($documentTypes as $type => $label)
                    <option value="{{ $type }}" @selected(old('document_type') === $type)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm font-medium text-slate-700">
            Titulo
            <input name="title" type="text" required maxlength="180" value="{{ old('title') }}" class="mt-1.5 block w-full border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
        </label>
    </div>

    <label class="block text-sm font-medium text-slate-700">
        Descripcion
        <textarea name="description" rows="3" maxlength="5000" class="mt-1.5 block w-full border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">{{ old('description') }}</textarea>
    </label>

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-sm font-medium text-slate-700">
            Archivo privado
            <input name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.docx" class="mt-1.5 block w-full border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm">
            <span class="mt-1 block text-xs font-normal text-slate-500">PDF, JPG, PNG, XLSX o DOCX; maximo 10 MB.</span>
        </label>
        <label class="text-sm font-medium text-slate-700">
            Link o referencia
            <input name="link_url" type="text" maxlength="2048" value="{{ old('link_url') }}" placeholder="URL o referencia textual de la evidencia" class="mt-1.5 block w-full border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
            <span class="mt-1 block text-xs font-normal text-slate-500">Puedes registrar una referencia textual para evidencia de consumo.</span>
        </label>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="text-sm font-medium text-slate-700">
            Fecha del documento
            <input name="document_date" type="date" value="{{ old('document_date') }}" class="mt-1.5 block w-full border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
        </label>
        <label class="flex items-center gap-3 self-end pb-2 text-sm font-medium text-slate-700">
            <input name="is_required" type="checkbox" value="1" @checked(old('is_required')) class="border-slate-300 text-sky-700 focus:ring-sky-500">
            Marcar como evidencia obligatoria
        </label>
    </div>

    <button type="submit" class="inline-flex items-center justify-center bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Cargar documento</button>
</form>
