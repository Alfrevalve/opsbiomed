@extends('layouts.ops')

@section('title', $document->title.' | Documentos OPS BIOMED')

@section('content')
    @php
        $statusLabels = ['pendiente' => 'Pendiente', 'cargado' => 'Cargado', 'validado' => 'Validado', 'observado' => 'Observado', 'rechazado' => 'Rechazado'];
        $statusClasses = ['pendiente' => 'bg-slate-100 text-slate-700', 'cargado' => 'bg-sky-50 text-sky-700', 'validado' => 'bg-emerald-50 text-emerald-700', 'observado' => 'bg-amber-50 text-amber-700', 'rechazado' => 'bg-rose-50 text-rose-700'];
        $target = $document->documentable;
        $targetLabel = class_basename($document->documentable_type);
        $targetRoute = match ($document->documentable_type) {
            \App\Models\SurgeryCase::class => $target ? route('cases.show', $target) : null,
            \App\Models\InventoryLot::class => $target ? route('inventory.show', $target) : null,
            \App\Models\Failure::class => $target ? route('failures.show', $target) : null,
            \App\Models\CaseReturn::class => $target ? route('returns.show', $target) : null,
            \App\Models\BillingRecord::class => $target?->case ? route('billing.show', $target->case) : null,
            default => null,
        };
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('documents.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver a documentos</a>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950">{{ $document->title }}</h1>
                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$document->status] ?? 'bg-slate-100 text-slate-700' }}">{{ $statusLabels[$document->status] ?? ucfirst($document->status) }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-500">Trazabilidad documental #{{ $document->id }}.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @if ($targetRoute)
                    <a href="{{ $targetRoute }}" class="border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver entidad relacionada</a>
                @endif
                @can('delete', $document)
                    <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('Enviar este documento a papelera logica?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="border border-rose-300 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">Eliminar registro</button>
                    </form>
                @endcan
            </div>
        </div>

        @can('alerts.view')
            @include('alerts._related', ['slaAlerts' => $slaAlerts])
        @endcan

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Datos del documento</h2></div>
                <dl class="grid gap-x-6 gap-y-5 p-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</dt><dd class="mt-1 text-sm text-slate-900">{{ $documentTypes[$document->document_type] ?? str_replace('_', ' ', ucfirst($document->document_type)) }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Entidad</dt><dd class="mt-1 text-sm text-slate-900">{{ $targetLabel }} #{{ $document->documentable_id }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cargado por</dt><dd class="mt-1 text-sm text-slate-900">{{ $document->uploadedBy?->name ?: 'Sistema' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha documento</dt><dd class="mt-1 text-sm text-slate-900">{{ $document->document_date?->format('d/m/Y') ?: 'No registrada' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descripcion</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $document->description ?: 'Sin descripcion' }}</dd></div>
                </dl>
            </div>
            <div class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Archivo o referencia privada</h2></div>
                <dl class="space-y-5 p-5">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Archivo</dt><dd class="mt-1 break-words text-sm text-slate-900">@if ($document->file_path)<span>Archivo privado cargado.</span> <a href="{{ route('documents.download', $document) }}" class="font-semibold text-sky-700 hover:text-sky-900">Descargar archivo</a>@elseNo se cargo archivo.@endif</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Formato / tamano</dt><dd class="mt-1 text-sm text-slate-900">{{ $document->mime_type ?: 'Referencia textual' }}{{ $document->file_size ? ' · '.number_format($document->file_size / 1024, 1).' KB' : '' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Link o referencia</dt><dd class="mt-1 break-words text-sm text-slate-900">@if ($document->link_url && \Illuminate\Support\Str::startsWith($document->link_url, ['http://', 'https://']))<a href="{{ $document->link_url }}" target="_blank" rel="noreferrer" class="font-semibold text-sky-700 hover:text-sky-900">{{ $document->link_url }}</a>@else{{ $document->link_url ?: 'No registrada' }}@endif</dd></div>
                </dl>
            </div>
        </section>

        @can('validate', $document)
            <section class="border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Validar documento</h2>
                <p class="mt-1 text-sm text-slate-500">La validacion queda registrada con usuario y fecha. Las observaciones y rechazos requieren comentario.</p>
                <form method="POST" action="{{ route('documents.validate', $document) }}" class="mt-4 grid gap-4 sm:grid-cols-3">
                    @csrf
                    <label class="text-sm font-medium text-slate-700">Estado
                        <select name="status" required class="mt-1.5 block w-full border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            @foreach (['validado' => 'Validado', 'observado' => 'Observado', 'rechazado' => 'Rechazado'] as $status => $label)
                                <option value="{{ $status }}" @selected($document->status === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700 sm:col-span-2">Observaciones de validacion
                        <textarea name="validation_observations" rows="2" maxlength="5000" class="mt-1.5 block w-full border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">{{ old('validation_observations', $document->validation_observations) }}</textarea>
                    </label>
                    <div class="sm:col-span-3"><button type="submit" class="bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Guardar validacion</button></div>
                </form>
            </section>
        @endcan

        @if ($document->validated_at || $document->validation_observations)
            <section class="border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Resultado de validacion</h2>
                <p class="mt-2 text-sm text-slate-700">{{ $document->validated_at?->format('d/m/Y H:i') }} · {{ $document->validatedBy?->name ?: 'Usuario no disponible' }}</p>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $document->validation_observations ?: 'Sin observaciones.' }}</p>
            </section>
        @endif

        @if ($canAudit)
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Historial de auditoria</h2></div>
                @if ($auditLogs->isEmpty())
                    <p class="px-5 py-8 text-sm text-slate-500">No hay eventos de auditoria para este documento.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($auditLogs as $audit)
                            <div class="px-5 py-4">
                                <div class="flex flex-col justify-between gap-2 sm:flex-row">
                                    <p class="text-sm font-semibold text-slate-900">{{ $audit->action }}</p>
                                    <p class="text-xs text-slate-500">{{ $audit->created_at?->format('d/m/Y H:i:s') }}</p>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">Por {{ $audit->user?->name ?: 'Sistema' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
