@extends('layouts.ops')

@section('title', 'Diagnostico de importacion | OPS BIOMED MR8')

@section('content')
    @php
        $statusClass = match ($import->status) {
            'committed' => 'bg-emerald-50 text-emerald-700',
            'replaced' => 'bg-slate-100 text-slate-600',
            'failed' => 'bg-rose-50 text-rose-700',
            default => 'bg-amber-50 text-amber-700',
        };
        $warehouseLabels = [
            'principal' => 'ALMACEN PRINCIPAL',
            'consignacion' => 'ALMACEN CONSIGNACION',
            'desvalorizado' => 'ALMACEN DESVALORIZADO',
            'ysan' => 'ALMACEN YSAN',
        ];
        $statusCounts = [
            'error' => $import->rows->where('status', 'error')->count(),
            'warning' => $import->rows->where('status', 'warning')->count(),
            'ok' => $import->rows->where('status', 'valid')->count(),
        ];
    @endphp

    @if ($errors->any())
        <div class='border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800'>
            <p class='font-semibold'>No se pudo confirmar la importacion.</p>
            <ul class='mt-2 list-disc space-y-1 pl-5'>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class='space-y-8'>
        <div class='flex flex-col justify-between gap-4 sm:flex-row sm:items-end'>
            <div>
                <a href='{{ route('catalog.imports.index') }}' class='text-sm font-medium text-sky-700 hover:text-sky-900'>Volver al importador</a>
                <div class='mt-3 flex flex-wrap items-center gap-3'>
                    <h1 class='text-2xl font-semibold tracking-tight text-slate-950'>{{ $import->original_filename }}</h1>
                    <span class='inline-flex px-2.5 py-1 text-xs font-semibold {{ $statusClass }}'>{{ ucfirst($import->status) }}</span>
                </div>
                <p class='mt-2 text-sm text-slate-500'>Cargado el {{ $import->created_at?->format('d/m/Y H:i') }} por {{ $import->uploadedBy?->name ?? 'Usuario' }}.</p>
            </div>

            <div class='flex flex-col items-start gap-3 sm:items-end'>
                @if ($import->critical_errors > 0)
                    <p class='border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-800'>
                        Confirmacion bloqueada: {{ $import->critical_errors }} errores criticos.
                    </p>
                @elseif ($import->status === 'validated')
                    @can('catalog.commit')
                        @if ($import->inconsistencies > 0)
                            <div class='max-w-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900'>
                                <p class='font-semibold'>Este archivo contiene cantidades negativas. Ser&aacute;n importadas como inconsistencias y no como stock disponible.</p>
                                <form method='POST' action='{{ route('catalog.imports.commit', $import) }}' class='mt-3 space-y-3'>
                                    @csrf
                                    <label class='flex items-start gap-2'>
                                        <input type='checkbox' name='confirm_negative_inconsistencies' value='1' required class='mt-0.5 h-4 w-4 border-amber-400 text-amber-700'>
                                        <span>Confirmo importar ignorando cantidades negativas como stock disponible y registrarlas como inconsistencias.</span>
                                    </label>
                                    <button type='submit' class='inline-flex items-center justify-center bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-800'>Confirmar con inconsistencias</button>
                                </form>
                            </div>
                        @else
                            <form method='POST' action='{{ route('catalog.imports.commit', $import) }}'>
                                @csrf
                                <button type='submit' class='inline-flex items-center justify-center bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800'>Confirmar importacion</button>
                            </form>
                        @endif
                    @endcan
                @endif
            </div>
        </div>

        <section class='grid gap-4 sm:grid-cols-2 xl:grid-cols-6' aria-label='Resumen de importacion'>
            @foreach ([
                ['label' => 'Total filas', 'value' => $import->total_rows, 'dot' => 'bg-sky-500'],
                ['label' => 'Productos detectados', 'value' => $import->products_detected, 'dot' => 'bg-violet-500'],
                ['label' => 'Lotes detectados', 'value' => $import->lots_detected, 'dot' => 'bg-emerald-500'],
                ['label' => 'Errores criticos', 'value' => $import->critical_errors, 'dot' => 'bg-rose-500'],
                ['label' => 'Inconsistencias', 'value' => $import->inconsistencies, 'dot' => 'bg-orange-500'],
                ['label' => 'Advertencias', 'value' => $import->warnings, 'dot' => 'bg-amber-500'],
            ] as $metric)
                <article class='border border-slate-200 bg-white p-5 shadow-sm'>
                    <div class='flex items-center justify-between gap-3'>
                        <p class='text-sm font-medium text-slate-500'>{{ $metric['label'] }}</p>
                        <span class='h-2.5 w-2.5 rounded-full {{ $metric['dot'] }}' aria-hidden='true'></span>
                    </div>
                    <p class='mt-4 text-3xl font-semibold tracking-tight text-slate-950'>{{ $metric['value'] }}</p>
                </article>
            @endforeach
        </section>

        <section class='grid gap-6 lg:grid-cols-3' aria-label='Diagnostico agrupado'>
            <div class='border border-rose-200 bg-white shadow-sm'>
                <div class='border-b border-rose-100 bg-rose-50 px-5 py-4'>
                    <h2 class='font-semibold text-rose-900'>Errores criticos por tipo</h2>
                    <p class='mt-1 text-sm text-rose-700'>Bloquean la confirmacion. Una fila puede aportar mas de un error.</p>
                </div>
                <div class='divide-y divide-slate-100'>
                    @foreach ($diagnostics['critical'] as $item)
                        <div class='flex items-center justify-between gap-4 px-5 py-3'>
                            <span class='text-sm {{ $item['count'] > 0 ? 'font-semibold text-slate-900' : 'text-slate-500' }}'>{{ $item['label'] }}</span>
                            <span class='inline-flex min-w-10 justify-center px-2.5 py-1 text-xs font-semibold {{ $item['count'] > 0 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-500' }}'>{{ $item['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class='border border-amber-200 bg-white shadow-sm'>
                <div class='border-b border-amber-100 bg-amber-50 px-5 py-4'>
                    <h2 class='font-semibold text-amber-900'>Advertencias por tipo</h2>
                    <p class='mt-1 text-sm text-amber-700'>Se conservan separadas y no bloquean por si solas la confirmacion.</p>
                </div>
                <div class='divide-y divide-slate-100'>
                    @foreach ($diagnostics['warnings'] as $item)
                        <div class='flex items-center justify-between gap-4 px-5 py-3'>
                            <span class='text-sm {{ $item['count'] > 0 ? 'font-semibold text-slate-900' : 'text-slate-500' }}'>{{ $item['label'] }}</span>
                            <span class='inline-flex min-w-10 justify-center px-2.5 py-1 text-xs font-semibold {{ $item['count'] > 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500' }}'>{{ $item['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class='border border-orange-200 bg-white shadow-sm'>
                <div class='border-b border-orange-100 bg-orange-50 px-5 py-4'>
                    <h2 class='font-semibold text-orange-900'>Inconsistencias por tipo</h2>
                    <p class='mt-1 text-sm text-orange-700'>No bloquean por si solas, pero exigen confirmacion antes del commit.</p>
                </div>
                <div class='divide-y divide-slate-100'>
                    @foreach ($diagnostics['inconsistencies'] as $item)
                        <div class='flex items-center justify-between gap-4 px-5 py-3'>
                            <span class='text-sm {{ $item['count'] > 0 ? 'font-semibold text-slate-900' : 'text-slate-500' }}'>{{ $item['label'] }}</span>
                            <span class='inline-flex min-w-10 justify-center px-2.5 py-1 text-xs font-semibold {{ $item['count'] > 0 ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-500' }}'>{{ $item['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class='border border-slate-200 bg-white shadow-sm' aria-label='Regla de vencimientos no aplicables'>
            <div class='border-b border-slate-200 px-5 py-4'>
                <h2 class='font-semibold text-slate-950'>Regla de vencimiento N/A</h2>
                <p class='mt-1 text-sm text-slate-500'>N/A se acepta en reutilizables/equipos y bloquea consumibles que requieren vencimiento.</p>
            </div>
            <div class='grid gap-4 p-5 sm:grid-cols-2'>
                <div class='border border-emerald-200 bg-emerald-50 p-4'>
                    <p class='text-sm font-semibold text-emerald-800'>N/A aceptados por ser reutilizables</p>
                    <p class='mt-2 text-2xl font-semibold text-emerald-900'>{{ $import->na_accepted }}</p>
                </div>
                <div class='border border-rose-200 bg-rose-50 p-4'>
                    <p class='text-sm font-semibold text-rose-800'>N/A bloqueados por ser consumibles</p>
                    <p class='mt-2 text-2xl font-semibold text-rose-900'>{{ $import->na_blocked }}</p>
                </div>
            </div>
        </section>

        <section class='border border-slate-200 bg-white shadow-sm'>
            <div class='border-b border-slate-200 px-5 py-4'>
                <h2 class='font-semibold text-slate-950'>Stock detectado por almacen</h2>
                <p class='mt-1 text-sm text-slate-500'>YSAN tiene lead time minimo de 48 horas y desvalorizado nunca es elegible.</p>
            </div>
            <div class='grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4'>
                @foreach ($warehouseLabels as $key => $label)
                    <div class='border border-slate-200 p-4'>
                        <p class='text-xs font-semibold uppercase tracking-wide text-slate-500'>{{ $label }}</p>
                        <p class='mt-2 text-2xl font-semibold text-slate-950'>{{ $import->stock_by_warehouse[$key] ?? 0 }}</p>
                        <p class='mt-1 text-xs {{ $key === 'principal' || $key === 'consignacion' ? 'text-emerald-700' : 'text-amber-700' }}'>{{ $key === 'principal' || $key === 'consignacion' ? 'Disponibilidad inmediata' : 'No inmediata' }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class='border border-slate-200 bg-white shadow-sm'>
            <div class='flex flex-col justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-end'>
                <div>
                    <h2 class='font-semibold text-slate-950'>Filas del archivo y validaciones</h2>
                    <p class='mt-1 text-sm text-slate-500'>Ordenadas por criticidad. Mostrando <span data-visible-count>{{ $import->rows->count() }}</span> de {{ $import->rows->count() }} filas.</p>
                </div>
                <div class='flex items-center gap-3'>
                    <label for='catalog-import-status-filter' class='text-sm font-semibold text-slate-700'>Filtrar estado</label>
                    <select id='catalog-import-status-filter' data-import-status-filter class='border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700'>
                        <option value='all'>Todos ({{ $import->rows->count() }})</option>
                        <option value='error'>Error ({{ $statusCounts['error'] }})</option>
                        <option value='warning'>Warning ({{ $statusCounts['warning'] }})</option>
                        <option value='ok'>OK ({{ $statusCounts['ok'] }})</option>
                    </select>
                </div>
            </div>

            @if ($import->rows->isEmpty())
                <p class='px-5 py-8 text-sm text-slate-500'>No hay filas para mostrar.</p>
            @else
                <div class='overflow-x-auto'>
                    <table class='min-w-full divide-y divide-slate-200 text-left text-sm'>
                        <thead class='bg-slate-50 text-xs uppercase tracking-wide text-slate-500'>
                            <tr>
                                <th class='px-5 py-3 font-semibold'>Fila</th>
                                <th class='px-5 py-3 font-semibold'>Codigo</th>
                                <th class='px-5 py-3 font-semibold'>Producto</th>
                                <th class='px-5 py-3 font-semibold'>Lote / serie</th>
                                <th class='px-5 py-3 font-semibold'>Ubicacion</th>
                                <th class='px-5 py-3 font-semibold'>Stock</th>
                                <th class='px-5 py-3 font-semibold'>Estado</th>
                                <th class='px-5 py-3 font-semibold'>Detalle</th>
                            </tr>
                        </thead>
                        <tbody class='divide-y divide-slate-100'>
                            @foreach ($import->rows as $row)
                                @php
                                    $rowStatus = $row->status === 'valid' ? 'ok' : $row->status;
                                    $rowClass = $row->status === 'error' ? 'bg-rose-50/40' : ($row->status === 'warning' ? 'bg-amber-50/40' : '');
                                    $rowLabel = $row->status === 'valid' ? 'OK' : ucfirst($row->status);
                                    $rowStatusClass = $row->status === 'error' ? 'bg-rose-100 text-rose-700' : ($row->status === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700');
                                @endphp
                                <tr data-import-row data-status='{{ $rowStatus }}' class='{{ $rowClass }} align-top'>
                                    <td class='px-5 py-4 text-slate-700'>{{ $row->row_number }}</td>
                                    <td class='px-5 py-4 font-semibold text-slate-900'>{{ $row->product_code ?? 'Vacio' }}</td>
                                    <td class='px-5 py-4 text-slate-700'>{{ $row->product_name ?? 'Vacio' }}</td>
                                    <td class='px-5 py-4 text-slate-700'>{{ $row->lot ?? 'Vacio' }}{{ $row->serial ? ' / '.$row->serial : '' }}</td>
                                    <td class='px-5 py-4 text-slate-700'>{{ $row->location ?? 'Vacia' }}</td>
                                    <td class='px-5 py-4 text-slate-700'>{{ $row->total_quantity ?? 0 }}</td>
                                    <td class='px-5 py-4'><span class='inline-flex px-2.5 py-1 text-xs font-semibold {{ $rowStatusClass }}'>{{ $rowLabel }}</span></td>
                                    <td class='max-w-md px-5 py-4 text-xs text-slate-600'>
                                        @foreach (($row->errors ?? []) as $error)
                                            <p class='text-rose-700'>{{ $error }}</p>
                                        @endforeach
                                        @foreach (($row->inconsistencies ?? []) as $inconsistency)
                                            <p class='text-orange-700'>{{ $inconsistency['message'] ?? 'Inconsistencia de cantidad negativa.' }}</p>
                                        @endforeach
                                        @foreach (($row->warnings ?? []) as $warning)
                                            <p class='text-amber-700'>{{ $warning }}</p>
                                        @endforeach
                                        @if ($row->status === 'valid' && empty($row->errors) && empty($row->warnings) && empty($row->inconsistencies))
                                            <span class='text-emerald-700'>Sin observaciones.</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p data-import-empty class='hidden px-5 py-8 text-sm text-slate-500'>No hay filas para el filtro seleccionado.</p>
            @endif
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filter = document.querySelector('[data-import-status-filter]');
            const rows = Array.from(document.querySelectorAll('[data-import-row]'));
            const emptyMessage = document.querySelector('[data-import-empty]');
            const visibleCount = document.querySelector('[data-visible-count]');

            if (!filter) {
                return;
            }

            const applyFilter = function () {
                let visible = 0;

                rows.forEach(function (row) {
                    const shouldShow = filter.value === 'all' || row.dataset.status === filter.value;
                    row.classList.toggle('hidden', !shouldShow);
                    visible += shouldShow ? 1 : 0;
                });

                if (emptyMessage) {
                    emptyMessage.classList.toggle('hidden', visible !== 0);
                }
                if (visibleCount) {
                    visibleCount.textContent = visible;
                }
            };

            filter.addEventListener('change', applyFilter);
            applyFilter();
        });
    </script>
@endsection
