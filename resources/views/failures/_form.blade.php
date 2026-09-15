@php
    $isEdit = isset($failure) && $failure;
    $formAction = $isEdit ? route('failures.update', $failure) : route('failures.store');
    $selectedProductId = old('product_id', $selectedProductId ?? $failure?->product_id);
    $selectedLotId = old('inventory_lot_id', $selectedLotId ?? $failure?->inventory_lot_id);
    $selectedCaseId = old('case_id', $selectedCaseId ?? $failure?->case_id);
@endphp

<form method="POST" action="{{ $formAction }}" class="space-y-8 border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
    @csrf
    @if ($isEdit)
        @method('PATCH')
    @endif

    @if ($errors->any())
        <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Revisa los campos marcados antes de guardar.
            @error('failure')<p class="mt-1">{{ $message }}</p>@enderror
        </div>
    @endif

    <section class="space-y-5">
        <div class="border-b border-slate-200 pb-3">
            <h2 class="font-semibold text-slate-950">Elemento afectado</h2>
            <p class="mt-1 text-sm text-slate-500">Relaciona la falla con un lote cuando la trazabilidad del inventario lo permita.</p>
        </div>

        @if ($isEdit)
            <div class="grid gap-5 md:grid-cols-3">
                <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Producto</p><p class="mt-1 text-sm text-slate-900">{{ $failure->product?->product_code ?? $failure->inventoryLot?->product?->product_code ?? 'No especificado' }} - {{ $failure->product?->name ?? $failure->inventoryLot?->product?->name }}</p></div>
                <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lote</p><p class="mt-1 text-sm text-slate-900">{{ $failure->inventoryLot?->lot ?: 'Sin lote' }}</p></div>
                <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Caso</p><p class="mt-1 text-sm text-slate-900">{{ $failure->case?->case_code ?: 'Registro manual' }}</p></div>
            </div>
        @else
            <div class="grid gap-5 md:grid-cols-3">
                <div>
                    <label for="product_id" class="block text-sm font-medium text-slate-700">Producto</label>
                    <select id="product_id" name="product_id" class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Selecciona un producto si no hay lote</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected($selectedProductId == $product->id)>{{ $product->product_code }} - {{ $product->name }}</option>
                        @endforeach
                    </select>
                    @error('product_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="inventory_lot_id" class="block text-sm font-medium text-slate-700">Lote / serie</label>
                    <select id="inventory_lot_id" name="inventory_lot_id" class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Registro sin lote</option>
                        @foreach ($lots as $lot)
                            <option value="{{ $lot->id }}" @selected($selectedLotId == $lot->id)>{{ $lot->product?->product_code }} / {{ $lot->lot ?: 'Sin lote' }}{{ $lot->serial ? ' / '.$lot->serial : '' }} - {{ $lot->warehouse?->name }}</option>
                        @endforeach
                    </select>
                    @error('inventory_lot_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="case_id" class="block text-sm font-medium text-slate-700">Caso relacionado</label>
                    <select id="case_id" name="case_id" class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Registro manual</option>
                        @foreach ($cases as $caseOption)
                            <option value="{{ $caseOption->id }}" @selected($selectedCaseId == $caseOption->id)>{{ $caseOption->case_code }} - {{ $caseOption->institution?->name }} / {{ $caseOption->doctor?->name }}</option>
                        @endforeach
                    </select>
                    @error('case_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>
        @endif
    </section>

    <section class="space-y-5">
        <div class="border-b border-slate-200 pb-3">
            <h2 class="font-semibold text-slate-950">Clasificacion de la falla</h2>
            <p class="mt-1 text-sm text-slate-500">Una falla alta, critica o ocurrida durante cirugia activa bloqueo preventivo automatico.</p>
        </div>
        <div class="grid gap-5 md:grid-cols-3">
            <div>
                <label for="failure_type" class="block text-sm font-medium text-slate-700">Tipo de falla <span class="text-rose-600">*</span></label>
                <select id="failure_type" name="failure_type" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    @foreach ($labels['types'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('failure_type', $failure?->failure_type ?? 'otro') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('failure_type')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="occurrence_moment" class="block text-sm font-medium text-slate-700">Momento <span class="text-rose-600">*</span></label>
                <select id="occurrence_moment" name="occurrence_moment" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    @foreach ($labels['moments'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('occurrence_moment', $failure?->occurrence_moment ?? 'inventario') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('occurrence_moment')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="severity" class="block text-sm font-medium text-slate-700">Severidad <span class="text-rose-600">*</span></label>
                <select id="severity" name="severity" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    @foreach ($labels['severities'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('severity', $failure?->severity ?? 'media') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('severity')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>
        @if ($isEdit)
            <div>
                <label for="status" class="block text-sm font-medium text-slate-700">Estado de seguimiento <span class="text-rose-600">*</span></label>
                <select id="status" name="status" required class="mt-1.5 block w-full max-w-md border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    @foreach (['reportada' => 'Reportada', 'bloqueada' => 'Bloqueada', 'en_revision' => 'En revision', 'pendiente_repuesto' => 'Pendiente de repuesto'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $failure->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
        @endif
    </section>

    <section class="grid gap-5 md:grid-cols-2">
        <div>
            <label for="description" class="block text-sm font-medium text-slate-700">Descripcion <span class="text-rose-600">*</span></label>
            <textarea id="description" name="description" rows="5" required maxlength="5000" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('description', $failure?->description) }}</textarea>
            @error('description')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="action_taken" class="block text-sm font-medium text-slate-700">Accion inmediata</label>
            <textarea id="action_taken" name="action_taken" rows="5" maxlength="2000" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('action_taken', $failure?->action_taken) }}</textarea>
            @error('action_taken')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="evidence_reference" class="block text-sm font-medium text-slate-700">Evidencia, enlace o referencia</label>
            <input id="evidence_reference" name="evidence_reference" type="text" value="{{ old('evidence_reference', $failure?->evidence_reference) }}" maxlength="500" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            @error('evidence_reference')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="responsible_technical_id" class="block text-sm font-medium text-slate-700">Responsable tecnico</label>
            <select id="responsible_technical_id" name="responsible_technical_id" class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">Sin asignar</option>
                @foreach ($technicalUsers as $technicalUser)
                    <option value="{{ $technicalUser->id }}" @selected(old('responsible_technical_id', $failure?->responsible_technical_id) == $technicalUser->id)>{{ $technicalUser->name }}</option>
                @endforeach
            </select>
            @error('responsible_technical_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>
    </section>

    @if ($isEdit)
        <section class="grid gap-5 md:grid-cols-2">
            <div><label for="diagnosis" class="block text-sm font-medium text-slate-700">Diagnostico tecnico</label><textarea id="diagnosis" name="diagnosis" rows="3" maxlength="5000" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('diagnosis', $failure->diagnosis) }}</textarea></div>
            <div><label for="probable_cause" class="block text-sm font-medium text-slate-700">Causa probable</label><textarea id="probable_cause" name="probable_cause" rows="3" maxlength="5000" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('probable_cause', $failure->probable_cause) }}</textarea></div>
            <div><label for="corrective_action" class="block text-sm font-medium text-slate-700">Accion correctiva</label><textarea id="corrective_action" name="corrective_action" rows="3" maxlength="5000" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('corrective_action', $failure->corrective_action) }}</textarea></div>
            <div class="flex flex-col justify-end gap-3 pb-1 sm:flex-row sm:items-center">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="requires_supplier" value="1" @checked(old('requires_supplier', $failure->requires_supplier)) class="border-slate-300 text-sky-700 focus:ring-sky-500"> Requiere proveedor</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="requires_replacement" value="1" @checked(old('requires_replacement', $failure->requires_replacement)) class="border-slate-300 text-sky-700 focus:ring-sky-500"> Requiere reposicion</label>
            </div>
        </section>
    @endif

    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
        <a href="{{ $isEdit ? route('failures.show', $failure) : route('failures.index') }}" class="inline-flex items-center justify-center border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
        <button type="submit" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-800">{{ $isEdit ? 'Guardar seguimiento' : 'Registrar falla' }}</button>
    </div>
</form>
