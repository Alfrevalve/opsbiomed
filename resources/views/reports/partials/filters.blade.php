<section class="border border-slate-200 bg-white p-5 shadow-sm no-print">
    <form method="GET" action="{{ $action }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @if ($section !== 'inventory')
        <label class="text-sm font-medium text-slate-700">Desde
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        </label>
        <label class="text-sm font-medium text-slate-700">Hasta
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        </label>
        @endif
        @if ($section !== 'inventory')
        <label class="text-sm font-medium text-slate-700">Institucion
            <select name="institution_id" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">Todas</option>
                @foreach ($options['institutions'] as $institution)
                    <option value="{{ $institution->id }}" @selected(($filters['institution_id'] ?? null) === $institution->id)>{{ $institution->name }}</option>
                @endforeach
            </select>
        </label>
        @endif
        @if ($section !== 'inventory')
        <label class="text-sm font-medium text-slate-700">Medico
            <select name="doctor_id" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">Todos</option>
                @foreach ($options['doctors'] as $doctor)
                    <option value="{{ $doctor->id }}" @selected(($filters['doctor_id'] ?? null) === $doctor->id)>{{ $doctor->name }}</option>
                @endforeach
            </select>
        </label>
        @endif
        <label class="text-sm font-medium text-slate-700">Tipo de cirugia
            <select name="surgery_type_id" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">Todos</option>
                @foreach ($options['surgery_types'] as $surgeryType)
                    <option value="{{ $surgeryType->id }}" @selected(($filters['surgery_type_id'] ?? null) === $surgeryType->id)>{{ $surgeryType->name }}</option>
                @endforeach
            </select>
        </label>
        @if ($section !== 'inventory')
        <label class="text-sm font-medium text-slate-700">Estado del caso
            <select name="case_status" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">Todos</option>
                @foreach ($options['case_statuses'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['case_status'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @endif
        @if ($section === 'billing')
            <label class="text-sm font-medium text-slate-700">Estado facturacion
                <select name="invoice_status" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Todos</option>
                    @foreach ($options['invoice_statuses'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['invoice_status'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @if ($section === 'inventory')
            <label class="text-sm font-medium text-slate-700">Producto
                <select name="product_id" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Todos</option>
                    @foreach ($options['products'] as $product)
                        <option value="{{ $product->id }}" @selected(($filters['product_id'] ?? null) === $product->id)>{{ $product->product_code }} · {{ $product->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Urgencia de stock
                <select name="urgency" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Todas</option>
                    @foreach ($options['urgencies'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['urgency'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Longitud (cm)
                <input type="number" step="0.01" min="0" name="length" value="{{ $filters['length'] ?? '' }}" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            </label>
            <label class="text-sm font-medium text-slate-700">Diametro (mm)
                <input type="number" step="0.01" min="0" name="diameter" value="{{ $filters['diameter'] ?? '' }}" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            </label>
        @endif
        <div class="flex flex-wrap items-end gap-3 sm:col-span-2 lg:col-span-4">
            <button type="submit" class="bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Aplicar filtros</button>
            <a href="{{ $action }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Limpiar</a>
        </div>
    </form>
</section>
