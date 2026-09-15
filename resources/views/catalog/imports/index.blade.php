@extends('layouts.ops')

@section('title', 'Importador de catalogo | OPS BIOMED MR8')

@section('content')
    <div class='space-y-8'>
        <div class='flex flex-col justify-between gap-4 sm:flex-row sm:items-end'>
            <div>
                <p class='text-sm font-medium text-sky-700'>Catalogo e inventario</p>
                <h1 class='mt-1 text-2xl font-semibold tracking-tight text-slate-950'>Importador Excel MR8</h1>
                <p class='mt-2 text-sm text-slate-500'>Carga el archivo a staging, revisa los errores y confirma solo cuando la validacion este limpia.</p>
            </div>
        </div>

        <section class='border border-slate-200 bg-white p-5 shadow-sm'>
            <form method='POST' enctype='multipart/form-data' action='{{ route('catalog.imports.store') }}' class='flex flex-col gap-4 sm:flex-row sm:items-end'>
                @csrf
                <div class='flex-1'>
                    <label for='catalog' class='block text-sm font-semibold text-slate-900'>Archivo Excel o CSV</label>
                    <input id='catalog' name='catalog' type='file' accept='.xlsx,.xls,.csv' required class='mt-2 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-700 file:mr-4 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700'>
                    @error('catalog')
                        <p class='mt-2 text-sm text-rose-700'>{{ $message }}</p>
                    @enderror
                </div>
                <button type='submit' class='inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-800'>Subir a staging</button>
            </form>
        </section>

        <section class='border border-slate-200 bg-white shadow-sm'>
            <div class='border-b border-slate-200 px-5 py-4'>
                <h2 class='font-semibold text-slate-950'>Historial de importaciones</h2>
                <p class='mt-1 text-sm text-slate-500'>Las importaciones confirmadas conservan su archivo, filas y auditoria.</p>
            </div>
            @if ($imports->isEmpty())
                <p class='px-5 py-8 text-sm text-slate-500'>Todavia no hay importaciones registradas.</p>
            @else
                <div class='overflow-x-auto'>
                    <table class='min-w-full divide-y divide-slate-200 text-left text-sm'>
                        <thead class='bg-slate-50 text-xs uppercase tracking-wide text-slate-500'>
                            <tr>
                                <th class='px-5 py-3 font-semibold'>Archivo</th>
                                <th class='px-5 py-3 font-semibold'>Estado</th>
                                <th class='px-5 py-3 font-semibold'>Filas</th>
                                <th class='px-5 py-3 font-semibold'>Errores</th>
                                <th class='px-5 py-3 font-semibold'>Inconsistencias</th>
                                <th class='px-5 py-3 font-semibold'>Advertencias</th>
                                <th class='px-5 py-3 font-semibold'>Fecha</th>
                            </tr>
                        </thead>
                        <tbody class='divide-y divide-slate-100'>
                            @foreach ($imports as $import)
                                @php
                                    $statusClass = match ($import->status) {
                                        'committed' => 'bg-emerald-50 text-emerald-700',
                                        'replaced' => 'bg-slate-100 text-slate-600',
                                        'failed' => 'bg-rose-50 text-rose-700',
                                        default => 'bg-amber-50 text-amber-700',
                                    };
                                @endphp
                                <tr class='hover:bg-slate-50'>
                                    <td class='px-5 py-4'>
                                        <a href='{{ route('catalog.imports.show', $import) }}' class='font-semibold text-sky-700 hover:text-sky-900'>{{ $import->original_filename }}</a>
                                        <p class='mt-1 text-xs text-slate-500'>{{ $import->uploadedBy?->name ?? 'Usuario' }}</p>
                                    </td>
                                    <td class='px-5 py-4'><span class='inline-flex px-2.5 py-1 text-xs font-semibold {{ $statusClass }}'>{{ ucfirst($import->status) }}</span></td>
                                    <td class='px-5 py-4 text-slate-700'>{{ $import->total_rows }}</td>
                                    <td class='px-5 py-4 font-semibold {{ $import->critical_errors > 0 ? 'text-rose-700' : 'text-slate-700' }}'>{{ $import->critical_errors }}</td>
                                    <td class='px-5 py-4 font-semibold {{ $import->inconsistencies > 0 ? 'text-orange-700' : 'text-slate-700' }}'>{{ $import->inconsistencies }}</td>
                                    <td class='px-5 py-4 text-slate-700'>{{ $import->warnings }}</td>
                                    <td class='whitespace-nowrap px-5 py-4 text-slate-700'>{{ $import->created_at?->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class='border-t border-slate-200 px-5 py-4'>{{ $imports->links() }}</div>
            @endif
        </section>
    </div>
@endsection
