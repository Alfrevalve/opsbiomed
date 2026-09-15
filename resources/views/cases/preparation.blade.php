@extends('layouts.ops')

@section('title', 'Preparacion y despacho | OPS BIOMED MR8')

@section('content')
    @php
        $preparation = $preparationSummary['preparation'];
        $checklistItems = [
            'institution_confirmed' => ['label' => 'Institucion confirmada', 'description' => 'Sede, ingreso y contacto operativo verificados.'],
            'doctor_confirmed' => ['label' => 'Medico confirmado', 'description' => 'Medico y equipo quirurgico confirmados.'],
            'schedule_confirmed' => ['label' => 'Fecha y hora confirmadas', 'description' => 'Agenda y ventana quirurgica verificadas.'],
            'material_confirmed' => ['label' => 'Material fisico verificado', 'description' => 'Producto, lote, serie y cantidad contrastados con la reserva.'],
            'documents_confirmed' => ['label' => 'Documentos preoperatorios confirmados', 'description' => 'Guia, cargo o respaldo disponible para el despacho.'],
        ];
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">
        <header class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-6 lg:flex-row lg:items-end">
            <div>
                <a href="{{ route('cases.control', $case) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Volver al control operativo</a>
                <p class="ops-eyebrow mt-4">Preoperatorio</p>
                <h1 class="ops-section-title mt-1 text-2xl font-semibold">Preparacion y despacho a sala</h1>
                <p class="ops-muted mt-2 text-sm">{{ $case->case_code }} / {{ $case->institution?->name ?: 'Institucion no registrada' }} / {{ $case->scheduled_at?->format('d/m/Y H:i') ?: 'Fecha por confirmar' }}</p>
            </div>
            <span class="{{ $preparationSummary['ready_for_room'] ? 'ops-badge-success' : 'ops-badge-warning' }}">
                {{ $preparationSummary['ready_for_room'] ? 'Listo para avanzar' : 'Preparacion pendiente' }}
            </span>
        </header>

        @if ($errors->has('preparation'))
            <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800" role="alert">
                {{ $errors->first('preparation') }}
            </div>
        @endif

        <section class="ops-card" aria-labelledby="preparation-case-heading">
            <div class="border-b border-slate-200 px-5 py-4">
                <p class="ops-eyebrow">Caso quirurgico</p>
                <h2 id="preparation-case-heading" class="ops-section-title mt-1 text-lg font-semibold">Verificacion previa a la salida</h2>
            </div>
            <dl class="grid gap-x-6 gap-y-4 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Institucion</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $case->institution?->name ?: 'No registrada' }}</dd>
                </div>
                <div>
                    <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Medico</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $case->doctor?->name ?: 'No registrado' }}</dd>
                </div>
                <div>
                    <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Paciente</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $case->patient?->full_name ?: 'No registrado' }}</dd>
                </div>
                <div>
                    <dt class="ops-muted text-xs font-semibold uppercase tracking-wide">Tipo de cirugia</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $case->surgeryType?->name ?: 'No registrado' }}</dd>
                </div>
            </dl>
        </section>

        <form method="POST" action="{{ route('cases.preparation.store', $case) }}" class="space-y-6">
            @csrf

            <section class="ops-card" aria-labelledby="checklist-heading">
                <div class="border-b border-slate-200 px-5 py-4">
                    <p class="ops-eyebrow">Checklist obligatorio</p>
                    <h2 id="checklist-heading" class="ops-section-title mt-1 text-lg font-semibold">Confirmaciones preoperatorias</h2>
                    <p class="ops-muted mt-1 text-sm">Todas las verificaciones deben completarse antes de confirmar el despacho.</p>
                </div>
                <div class="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($checklistItems as $field => $item)
                        <label class="flex cursor-pointer gap-3 border p-4 {{ $errors->has($field) ? 'border-rose-300 bg-rose-50' : 'border-slate-200 bg-white' }}">
                            <input type="hidden" name="{{ $field }}" value="0">
                            <input
                                type="checkbox"
                                name="{{ $field }}"
                                value="1"
                                @checked(old($field, $preparation?->{$field} ?? false))
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-700"
                            >
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">{{ $item['label'] }}</span>
                                <span class="ops-muted mt-1 block text-xs leading-5">{{ $item['description'] }}</span>
                                @error($field)
                                    <span class="mt-2 block text-xs font-medium text-rose-700">{{ $message }}</span>
                                @enderror
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="ops-card" aria-labelledby="dispatch-heading">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="ops-eyebrow">Despacho fisico</p>
                        <h2 id="dispatch-heading" class="ops-section-title mt-1 text-lg font-semibold">Material reservado</h2>
                        <p class="ops-muted mt-1 text-sm">La cantidad despachada debe coincidir exactamente con cada reserva activa.</p>
                    </div>
                    <span class="ops-badge-neutral">{{ $preparationSummary['rows']->count() }} reservas activas</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Producto</th>
                                <th class="px-5 py-3 font-semibold">Codigo</th>
                                <th class="px-5 py-3 font-semibold">Lote / serie</th>
                                <th class="px-5 py-3 font-semibold">Almacen</th>
                                <th class="px-5 py-3 text-right font-semibold">Reservado</th>
                                <th class="px-5 py-3 text-right font-semibold">Despachado</th>
                                <th class="px-5 py-3 font-semibold">Verificacion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($preparationSummary['rows'] as $index => $row)
                                @php
                                    $reservation = $row['reservation'];
                                    $lot = $reservation->inventoryLot;
                                    $sent = $row['material_sent'];
                                    $quantity = old("materials.$index.quantity", $sent?->quantity ?? $reservation->quantity);
                                    $quantity = filled($quantity) ? $quantity : $reservation->quantity;
                                @endphp
                                <tr class="align-top">
                                    <td class="px-5 py-4 font-medium text-slate-900">{{ $lot?->product?->name ?: 'Producto no registrado' }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $lot?->product?->product_code ?: '-' }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $lot?->lot ?: '-' }}{{ $lot?->serial ? ' / '.$lot->serial : '' }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $lot?->warehouse?->name ?: '-' }}</td>
                                    <td class="px-5 py-4 text-right font-semibold text-slate-900">{{ number_format((int) $reservation->quantity) }}</td>
                                    <td class="px-5 py-4">
                                        <input type="hidden" name="materials[{{ $index }}][reservation_id]" value="{{ $reservation->id }}">
                                        <input
                                            id="material-quantity-{{ $reservation->id }}"
                                            type="number"
                                            name="materials[{{ $index }}][quantity]"
                                            min="1"
                                            step="1"
                                            value="{{ $quantity }}"
                                            class="block w-24 border-slate-300 text-right text-sm text-slate-900 focus:border-cyan-700 focus:ring-cyan-700 {{ $errors->has("materials.$index.quantity") ? 'border-rose-400' : '' }}"
                                            aria-label="Cantidad despachada para {{ $lot?->product?->name ?: 'material reservado' }}"
                                        >
                                        @error("materials.$index.quantity")
                                            <p class="mt-1 max-w-44 text-xs text-rose-700">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="px-5 py-4">
                                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-800">
                                            <input type="hidden" name="materials[{{ $index }}][verified]" value="0">
                                            <input
                                                type="checkbox"
                                                name="materials[{{ $index }}][verified]"
                                                value="1"
                                                @checked(old("materials.$index.verified", $row['dispatched']))
                                                class="h-4 w-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-700"
                                            >
                                            Verificado
                                        </label>
                                        @error("materials.$index.verified")
                                            <p class="mt-1 max-w-44 text-xs text-rose-700">{{ $message }}</p>
                                        @enderror
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-8 text-sm text-slate-500">No hay reservas activas disponibles para preparar este despacho.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ops-card" aria-labelledby="evidence-heading">
                <div class="border-b border-slate-200 px-5 py-4">
                    <p class="ops-eyebrow">Trazabilidad</p>
                    <h2 id="evidence-heading" class="ops-section-title mt-1 text-lg font-semibold">Guia y evidencia de despacho</h2>
                    <p class="ops-muted mt-1 text-sm">Registre al menos un numero de guia o una referencia de evidencia.</p>
                </div>
                <div class="grid gap-5 p-5 lg:grid-cols-2">
                    <div>
                        <label for="guide_number" class="text-sm font-semibold text-slate-800">Numero de guia</label>
                        <input id="guide_number" type="text" name="guide_number" maxlength="100" value="{{ old('guide_number', $preparation?->guide_number) }}" class="mt-2 block w-full border-slate-300 text-sm text-slate-900 focus:border-cyan-700 focus:ring-cyan-700">
                        @error('guide_number')
                            <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="delivery_evidence_reference" class="text-sm font-semibold text-slate-800">Link o referencia de evidencia</label>
                        <input id="delivery_evidence_reference" type="text" name="delivery_evidence_reference" maxlength="500" value="{{ old('delivery_evidence_reference', $preparation?->delivery_evidence_reference) }}" class="mt-2 block w-full border-slate-300 text-sm text-slate-900 focus:border-cyan-700 focus:ring-cyan-700">
                        @error('delivery_evidence_reference')
                            <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="lg:col-span-2">
                        <label for="notes" class="text-sm font-semibold text-slate-800">Observaciones operativas</label>
                        <textarea id="notes" name="notes" rows="4" maxlength="2000" class="mt-2 block w-full border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-700 focus:ring-cyan-700" placeholder="Indique cualquier detalle relevante de la entrega o coordinacion.">{{ old('notes', $preparation?->notes) }}</textarea>
                        @error('notes')
                            <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                <a href="{{ route('cases.control', $case) }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Cancelar</a>
                <button type="submit" class="ops-button-primary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Confirmar preparacion y despacho</button>
            </div>
        </form>
    </div>
@endsection
