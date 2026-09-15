@extends('layouts.ops')

@section('title', 'Devoluciones postoperatorias | OPS BIOMED MR8')

@section('content')
    @php
        $conditionClasses = [
            'pendiente_inspeccion' => 'bg-amber-50 text-amber-700',
            'inspeccionado' => 'bg-sky-50 text-sky-700',
            'liberado' => 'bg-emerald-50 text-emerald-700',
            'cuarentena' => 'bg-amber-50 text-amber-700',
            'bloqueado' => 'bg-rose-50 text-rose-700',
            'desvalorizado' => 'bg-orange-50 text-orange-700',
            'dado_de_baja' => 'bg-slate-100 text-slate-700',
        ];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('dashboard.ops') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al dashboard</a>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Devoluciones postoperatorias</h1>
                <p class="mt-2 text-sm text-slate-500">Inspeccion y trazabilidad del material retornado despues de cada cirugia.</p>
            </div>
            @can('manual.view')
                <a href="{{ route('manual.show', 'inspeccionar-devolucion') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Guia de inspeccion</a>
            @endcan
        </div>

        <section class="border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('returns.index') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Estado</label>
                    <select id="status" name="status" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Todos</option>
                        @foreach ($conditions as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="institution_id" class="block text-sm font-medium text-slate-700">Institucion</label>
                    <select id="institution_id" name="institution_id" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Todas</option>
                        @foreach ($institutions as $institution)
                            <option value="{{ $institution->id }}" @selected((string) request('institution_id') === (string) $institution->id)>{{ $institution->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="product_id" class="block text-sm font-medium text-slate-700">Producto</label>
                    <select id="product_id" name="product_id" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Todos</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->product_code }} - {{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date" class="block text-sm font-medium text-slate-700">Fecha devolucion</label>
                    <input id="date" name="date" type="date" value="{{ request('date') }}" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>
                <div>
                    <label for="responsible_id" class="block text-sm font-medium text-slate-700">Responsable</label>
                    <select id="responsible_id" name="responsible_id" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Todos</option>
                        @foreach ($responsibleUsers as $responsibleUser)
                            <option value="{{ $responsibleUser->id }}" @selected((string) request('responsible_id') === (string) $responsibleUser->id)>{{ $responsibleUser->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-3 md:col-span-2 xl:col-span-5">
                    <button type="submit" class="bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Filtrar</button>
                    <a href="{{ route('returns.index') }}" class="border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col justify-between gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="font-semibold text-slate-950">Retornos registrados</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $returns->total() }} devolucion(es) encontradas.</p>
                </div>
            </div>
            @if ($returns->isEmpty())
                <p class="px-5 py-8 text-sm text-slate-500">No hay devoluciones pendientes.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Caso</th>
                                <th class="px-5 py-3 font-semibold">Institucion / medico</th>
                                <th class="px-5 py-3 font-semibold">Producto / lote</th>
                                <th class="px-5 py-3 font-semibold">Cantidad</th>
                                <th class="px-5 py-3 font-semibold">Estado</th>
                                <th class="px-5 py-3 font-semibold">Fecha devolucion</th>
                                <th class="px-5 py-3 font-semibold">Accion pendiente</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($returns as $return)
                                @php
                                    $condition = $return->condition;
                                    $action = match ($condition) {
                                        'pendiente_inspeccion' => 'Inspeccionar',
                                        'cuarentena' => 'Gestionar cuarentena',
                                        'bloqueado' => 'Revisar falla tecnica',
                                        default => 'Sin accion pendiente',
                                    };
                                @endphp
                                <tr class="align-top hover:bg-slate-50">
                                    <td class="px-5 py-4"><a href="{{ route('returns.show', $return) }}" class="font-semibold text-sky-700 hover:text-sky-900">{{ $return->case?->case_code ?: 'Caso no disponible' }}</a></td>
                                    <td class="px-5 py-4 text-slate-700"><p>{{ $return->case?->institution?->name ?: 'Sin institucion' }}</p><p class="mt-1 text-xs text-slate-500">{{ $return->case?->doctor?->name ?: 'Sin medico' }}</p></td>
                                    <td class="px-5 py-4 text-slate-700"><p>{{ $return->inventoryLot?->product?->product_code ?: 'Sin codigo' }}</p><p class="mt-1 text-xs text-slate-500">Lote {{ $return->inventoryLot?->lot ?: 'N/A' }}{{ $return->inventoryLot?->serial ? ' / Serie '.$return->inventoryLot->serial : '' }}</p></td>
                                    <td class="px-5 py-4 font-semibold text-slate-900">{{ $return->returned_qty }}</td>
                                    <td class="px-5 py-4"><span class="inline-flex px-2.5 py-1 text-xs font-semibold {{ $conditionClasses[$condition] ?? 'bg-slate-100 text-slate-700' }}">{{ $conditions[$condition] ?? ucfirst(str_replace('_', ' ', $condition)) }}</span><p class="mt-1 text-xs text-slate-500">{{ $return->inspection_result ? str_replace('_', ' ', ucfirst($return->inspection_result)) : 'Pendiente de resultado' }}</p></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $return->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-4">@if ($condition === 'pendiente_inspeccion')<a href="{{ route('returns.inspect.create', $return) }}" class="font-semibold text-sky-700 hover:text-sky-900">{{ $action }}</a>@else<span class="text-slate-500">{{ $action }}</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-4">{{ $returns->links() }}</div>
            @endif
        </section>
    </div>
@endsection
