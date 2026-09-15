@php
    $documentTypes = $documentTypes ?? [
        'solicitud' => 'Solicitud',
        'guia_internamiento' => 'Guia de internamiento',
        'cargo_recepcion' => 'Cargo de recepcion',
        'evidencia_consumo' => 'Evidencia de consumo',
        'hoja_consumo' => 'Hoja de consumo',
        'reporte_falla' => 'Reporte de falla',
        'evidencia_falla' => 'Evidencia de falla',
        'evidencia_devolucion' => 'Evidencia de devolucion',
        'inspeccion' => 'Inspeccion',
        'orden_compra' => 'Orden de compra',
        'factura' => 'Factura',
        'boleta' => 'Boleta',
        'aprobacion_costo_cero' => 'Aprobacion de costo cero',
        'solicitud_pago' => 'Solicitud de pago',
        'comprobante_pago' => 'Comprobante de pago',
        'otro' => 'Otro',
    ];
    $statusLabels = [
        'pendiente' => 'Pendiente',
        'cargado' => 'Cargado',
        'validado' => 'Validado',
        'observado' => 'Observado',
        'rechazado' => 'Rechazado',
    ];
    $statusClasses = [
        'pendiente' => 'bg-slate-100 text-slate-700',
        'cargado' => 'bg-sky-50 text-sky-700',
        'validado' => 'bg-emerald-50 text-emerald-700',
        'observado' => 'bg-amber-50 text-amber-700',
        'rechazado' => 'bg-rose-50 text-rose-700',
    ];
@endphp

@if ($documents->isEmpty())
    <p class="px-5 py-8 text-sm text-slate-500">No hay documentos cargados.</p>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3 font-semibold">Documento</th>
                    <th class="px-5 py-3 font-semibold">Tipo</th>
                    <th class="px-5 py-3 font-semibold">Estado</th>
                    <th class="px-5 py-3 font-semibold">Carga</th>
                    <th class="px-5 py-3 font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @foreach ($documents as $document)
                    <tr>
                        <td class="px-5 py-4">
                            <p class="font-semibold text-slate-900">{{ $document->title }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $document->file_path ? 'Archivo privado' : 'Referencia registrada' }}{{ $document->is_required ? ' · Obligatorio' : '' }}</p>
                        </td>
                        <td class="px-5 py-4 text-slate-600">{{ $documentTypes[$document->document_type] ?? str_replace('_', ' ', ucfirst($document->document_type)) }}</td>
                        <td class="px-5 py-4"><span class="inline-flex px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$document->status] ?? 'bg-slate-100 text-slate-700' }}">{{ $statusLabels[$document->status] ?? ucfirst($document->status) }}</span></td>
                        <td class="px-5 py-4 text-slate-600">{{ $document->uploadedBy?->name ?: 'Sistema' }}<br><span class="text-xs text-slate-400">{{ $document->created_at?->format('d/m/Y H:i') }}</span></td>
                        <td class="px-5 py-4 text-right"><a href="{{ route('documents.show', $document) }}" class="font-semibold text-sky-700 hover:text-sky-900">Ver detalle</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
