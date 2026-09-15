@extends('layouts.ops')

@section('title', 'Documentos y evidencias | OPS BIOMED MR8')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-sky-700">Trazabilidad transversal</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Documentos y evidencias</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">Centraliza archivos y referencias de solicitudes, inventario, fallas, devoluciones, facturacion y aprobaciones.</p>
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

        @can('documents.upload')
            <section class="border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-slate-200 pb-4">
                    <h2 class="font-semibold text-slate-950">Cargar evidencia</h2>
                    <p class="mt-1 text-sm text-slate-500">El archivo se conserva en almacenamiento privado y cada carga queda auditada.</p>
                </div>
                @include('documents._upload-form')
            </section>
        @endcan

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="font-semibold text-slate-950">Repositorio documental</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $documents->total() }} documentos visibles.</p>
                    </div>
                    <form method="GET" class="grid gap-2 sm:grid-cols-3">
                        <select name="status" class="border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Todos los estados</option>
                            @foreach (['pendiente' => 'Pendiente', 'cargado' => 'Cargado', 'validado' => 'Validado', 'observado' => 'Observado', 'rechazado' => 'Rechazado'] as $status => $label)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="document_type" class="border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Todos los tipos</option>
                            @foreach ($documentTypes as $type => $label)
                                <option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700">Filtrar</button>
                    </form>
                </div>
            </div>
            @include('documents._list', ['documents' => $documents, 'documentTypes' => $documentTypes])
            @if ($documents->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $documents->links() }}</div>
            @endif
        </section>
    </div>
@endsection
