@extends('layouts.ops')

@section('title', 'Cerrar cirugia | '.$case->case_code)

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('cases.show', $case) }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al detalle</a>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Cerrar cirugia</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $case->case_code }} - {{ $case->surgeryType?->name }} - {{ $case->patient?->full_name }}</p>
            </div>
            <div class="border border-slate-200 bg-white px-4 py-3 text-sm">
                <p>
                    <span class="text-slate-500">Estado actual:</span>
                    <strong class="ml-1 text-slate-950">{{ $case->status?->label() ?? 'No definido' }}</strong>
                </p>
                <p class="mt-1 text-xs text-slate-500">Al guardar correctamente pasara a: Cerrado</p>
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

        <form method="POST" action="{{ route('cases.close', $case) }}" class="space-y-6">
            @csrf
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-950">Material reservado</h2>
                    <p class="mt-1 text-sm text-slate-500">Cada reserva debe quedar conciliada antes de cerrar.</p>
                    <p class="mt-2 text-sm text-slate-600">La suma de usada + abierta no usada + devuelta + falla debe igualar lo reservado.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3" aria-live="polite">
                        <div class="border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reservado total</p>
                            <p class="mt-1 text-xl font-semibold text-slate-950" data-total-reserved>0</p>
                        </div>
                        <div class="border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Conciliado total</p>
                            <p class="mt-1 text-xl font-semibold text-slate-950" data-total-reconciled>0</p>
                        </div>
                        <div class="border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Diferencia total</p>
                            <p class="mt-1 text-xl font-semibold text-slate-950" data-total-difference>0</p>
                        </div>
                    </div>
                </div>
                <div class="divide-y divide-slate-200">
                    @foreach ($case->reservations as $index => $reservation)
                        @php
                            $prefix = 'materials.'.$index;
                            $lot = $reservation->inventoryLot;
                            $priceSuggestion = $priceSuggestions[$reservation->id] ?? null;
                        @endphp
                        <div class="space-y-4 p-5" data-material-row data-reserved="{{ $reservation->quantity }}">
                            <input type="hidden" name="materials[{{ $index }}][reservation_id]" value="{{ $reservation->id }}">
                            <div class="flex flex-col justify-between gap-2 sm:flex-row">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $lot?->product?->name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $lot?->product?->product_code }} - Lote {{ $lot?->lot ?? 'Sin lote' }} - {{ $lot?->warehouse?->name }}</p>
                                </div>
                                <div class="border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900">
                                    Cantidad reservada: <span data-reserved-value>{{ $reservation->quantity }}</span>
                                </div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cantidad conciliada</p>
                                    <p class="mt-1 text-xl font-semibold text-slate-950" data-reconciled-value>0</p>
                                </div>
                                <div class="border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Diferencia</p>
                                    <p class="mt-1 text-xl font-semibold text-slate-950" data-difference-value>0</p>
                                </div>
                                <div class="border border-slate-200 bg-emerald-50 px-4 py-3 text-emerald-800" data-reconciliation-status>
                                    <p class="text-xs font-semibold uppercase tracking-wide">Estado</p>
                                    <p class="mt-1 font-semibold" data-status-label>Conciliado</p>
                                    <p class="mt-1 text-sm" data-status-message>Las cantidades coinciden.</p>
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                @foreach ([
                                    ['key' => 'used_qty', 'label' => 'Cantidad usada'],
                                    ['key' => 'unused_opened_qty', 'label' => 'Abierta no usada'],
                                    ['key' => 'returned_qty', 'label' => 'Cantidad devuelta'],
                                    ['key' => 'failure_qty', 'label' => 'Cantidad con falla'],
                                ] as $field)
                                    @php
                                        $oldQuantity = old($prefix.'.'.$field['key']);
                                        $quantityValue = filled($oldQuantity) ? $oldQuantity : 0;
                                    @endphp
                                    <label class="text-sm font-medium text-slate-700">
                                        {{ $field['label'] }}
                                        <input name="materials[{{ $index }}][{{ $field['key'] }}]" type="number" min="0" step="1" value="{{ $quantityValue }}" data-reconciliation-field="{{ $field['key'] }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                    </label>
                                @endforeach
                                <label class="text-sm font-medium text-slate-700">
                                    Precio unitario
                                    <input name="materials[{{ $index }}][unit_price]" type="number" min="0" step="0.01" value="{{ old($prefix.'.unit_price', $priceSuggestion?->unit_price) }}" placeholder="Precio o costo cero" @readonly(! $canOverridePrice) class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 read-only:bg-slate-100 read-only:text-slate-500">
                                    @if ($priceSuggestion)
                                        <span class="mt-1 block text-xs text-slate-500">{{ $canOverridePrice ? 'Sugerido por maestro; puedes ajustarlo.' : 'Sugerido por maestro.' }} Monto minimo autorizado: {{ $priceSuggestion->minimum_price !== null ? number_format((float) $priceSuggestion->minimum_price, 2).' '.$priceSuggestion->currency : 'No definido' }}.</span>
                                    @else
                                        <span class="mt-1 block text-xs text-amber-700">Sin precio vigente en el maestro. Registra uno manualmente o usa costo cero con motivo.</span>
                                    @endif
                                </label>
                            </div>
                            @can('trace.scan')
                                <label class="block text-sm font-medium text-slate-700">
                                    Validar lote por escaneo (opcional)
                                    <input name="materials[{{ $index }}][inventory_lot_trace_code]" type="text" maxlength="160" value="{{ old($prefix.'.inventory_lot_trace_code') }}" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="{{ $lot?->trace_code ?: 'Escanea la etiqueta del lote reservado' }}" class="mt-2 block w-full border-slate-300 font-mono text-sm uppercase shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                    <span class="mt-1 block text-xs text-slate-500">El codigo debe corresponder al lote reservado para esta fila.</span>
                                    @error($prefix.'.inventory_lot_trace_code')<span class="mt-1 block text-xs font-medium text-rose-700">{{ $message }}</span>@enderror
                                </label>
                            @endcan
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="border border-transparent p-3 text-sm font-medium text-slate-700 transition" data-difference-reason>
                                    Motivo de diferencia
                                    <textarea name="materials[{{ $index }}][difference_reason]" rows="2" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old($prefix.'.difference_reason') }}</textarea>
                                    <span class="mt-2 hidden text-xs font-semibold text-amber-800" data-difference-help>Indica por que falta justificar esta cantidad.</span>
                                </label>
                                <label class="border border-transparent p-3 text-sm font-medium text-slate-700 transition" data-failure-description>
                                    Descripcion de falla
                                    <textarea name="materials[{{ $index }}][failure_description]" rows="2" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old($prefix.'.failure_description') }}</textarea>
                                    <span class="mt-2 hidden text-xs font-semibold text-rose-800" data-failure-help>Describe la falla para activar la revision tecnica.</span>
                                </label>
                            </div>
                            <div class="flex flex-wrap items-center gap-4">
                                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                    <input type="hidden" name="materials[{{ $index }}][cost_zero]" value="0">
                                    <input type="checkbox" name="materials[{{ $index }}][cost_zero]" value="1" @checked(old($prefix.'.cost_zero') === '1') class="border-slate-300 text-sky-700 focus:ring-sky-500">
                                    Costo cero
                                </label>
                                <label class="min-w-64 flex-1 text-sm font-medium text-slate-700">
                                    Motivo costo cero
                                    <input name="materials[{{ $index }}][cost_zero_reason]" type="text" value="{{ old($prefix.'.cost_zero_reason') }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Evidencia y cierre</h2></div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <label class="text-sm font-medium text-slate-700 sm:col-span-2">
                        Evidencia de consumo
                        <textarea name="evidence_description" rows="3" required class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('evidence_description') }}</textarea>
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        Link o referencia de evidencia
                        <input name="evidence_reference" type="text" value="{{ old('evidence_reference') }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        Observaciones
                        <input name="observations" type="text" value="{{ old('observations') }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    </label>
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3">
                <a href="{{ route('cases.show', $case) }}" class="border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button type="submit" class="bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Registrar consumo y cerrar</button>
            </div>
        </form>
        @can('documents.view')
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div><h2 class="font-semibold text-slate-950">Evidencias vinculadas</h2><p class="mt-1 text-sm text-slate-500">Puedes agregar la hoja de consumo o evidencia firmada desde el expediente documental del caso.</p></div>
                    <a href="{{ route('cases.documents.index', $case) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Abrir documentos del caso</a>
                </div>
                @include('documents._list', ['documents' => $case->documents])
            </section>
        @endcan
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const rows = [...document.querySelectorAll('[data-material-row]')];
                const totalReserved = document.querySelector('[data-total-reserved]');
                const totalReconciled = document.querySelector('[data-total-reconciled]');
                const totalDifference = document.querySelector('[data-total-difference]');
                const tones = {
                    green: ['border-emerald-200', 'bg-emerald-50', 'text-emerald-800'],
                    yellow: ['border-amber-200', 'bg-amber-50', 'text-amber-800'],
                    red: ['border-rose-200', 'bg-rose-50', 'text-rose-800'],
                };

                const quantity = (row, field) => {
                    const input = row.querySelector(`[data-reconciliation-field="${field}"]`);
                    const value = Number.parseInt(input?.value ?? '0', 10);

                    return Number.isNaN(value) ? 0 : value;
                };

                const applyTone = (element, tone) => {
                    Object.values(tones).flat().forEach((className) => element.classList.remove(className));
                    tones[tone].forEach((className) => element.classList.add(className));
                };

                const updateRow = (row) => {
                    const reserved = Number.parseInt(row.dataset.reserved ?? '0', 10) || 0;
                    const reconciled = ['used_qty', 'unused_opened_qty', 'returned_qty', 'failure_qty']
                        .reduce((sum, field) => sum + quantity(row, field), 0);
                    const difference = reserved - reconciled;
                    const failureQuantity = quantity(row, 'failure_qty');
                    const status = row.querySelector('[data-reconciliation-status]');
                    const differenceReason = row.querySelector('[data-difference-reason]');
                    const differenceInput = differenceReason?.querySelector('textarea');
                    const differenceHelp = row.querySelector('[data-difference-help]');
                    const failureDescription = row.querySelector('[data-failure-description]');
                    const failureInput = failureDescription?.querySelector('textarea');
                    const failureHelp = row.querySelector('[data-failure-help]');

                    row.querySelector('[data-reconciled-value]').textContent = reconciled;
                    row.querySelector('[data-difference-value]').textContent = difference;

                    if (difference === 0) {
                        applyTone(status, 'green');
                        status.querySelector('[data-status-label]').textContent = 'Conciliado';
                        status.querySelector('[data-status-message]').textContent = 'Las cantidades coinciden.';
                    } else if (difference > 0) {
                        applyTone(status, 'yellow');
                        status.querySelector('[data-status-label]').textContent = 'Falta justificar';
                        status.querySelector('[data-status-message]').textContent = `Falta justificar ${difference} ${difference === 1 ? 'unidad' : 'unidades'}.`;
                    } else {
                        applyTone(status, 'red');
                        status.querySelector('[data-status-label]').textContent = 'Exceso ingresado';
                        status.querySelector('[data-status-message]').textContent = `Exceso ingresado: ${Math.abs(difference)} ${Math.abs(difference) === 1 ? 'unidad' : 'unidades'}.`;
                    }

                    differenceReason.classList.toggle('border-amber-300', difference > 0);
                    differenceReason.classList.toggle('bg-amber-50', difference > 0);
                    differenceReason.classList.toggle('text-amber-900', difference > 0);
                    differenceInput.required = difference > 0;
                    differenceHelp.classList.toggle('hidden', difference <= 0);

                    failureDescription.classList.toggle('border-rose-300', failureQuantity > 0);
                    failureDescription.classList.toggle('bg-rose-50', failureQuantity > 0);
                    failureDescription.classList.toggle('text-rose-900', failureQuantity > 0);
                    failureInput.required = failureQuantity > 0;
                    failureHelp.classList.toggle('hidden', failureQuantity <= 0);

                    return { reserved, reconciled, difference };
                };

                const updateTotals = () => {
                    const totals = rows.map(updateRow).reduce((sum, row) => ({
                        reserved: sum.reserved + row.reserved,
                        reconciled: sum.reconciled + row.reconciled,
                        difference: sum.difference + row.difference,
                    }), { reserved: 0, reconciled: 0, difference: 0 });

                    totalReserved.textContent = totals.reserved;
                    totalReconciled.textContent = totals.reconciled;
                    totalDifference.textContent = totals.difference;
                };

                rows.forEach((row) => {
                    row.querySelectorAll('[data-reconciliation-field]').forEach((input) => {
                        input.addEventListener('input', updateTotals);
                    });
                });

                updateTotals();
            });
        </script>
    </div>
@endsection
