@extends('layouts.ops')

@section('title', 'Fallas tecnicas | OPS BIOMED MR8')

@section('content')
    @php
        $statusClasses = [
            'reportada' => 'bg-amber-50 text-amber-700',
            'bloqueada' => 'bg-rose-50 text-rose-700',
            'en_revision' => 'bg-sky-50 text-sky-700',
            'pendiente_repuesto' => 'bg-orange-50 text-orange-700',
            'liberada' => 'bg-emerald-50 text-emerald-700',
            'dada_de_baja' => 'bg-slate-100 text-slate-600',
            'cerrada' => 'bg-slate-100 text-slate-600',
        ];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Mantenimiento y fallas tecnicas</h1>
                <p class="mt-2 text-sm text-slate-500">Control de reportes, bloqueos preventivos, revision, liberacion y baja.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('manual.view')
                    <a href="{{ route('manual.index', ['module' => 'Fallas técnicas']) }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Guia de fallas</a>
                @endcan
                @can('create', App\Models\Failure::class)
                    <a href="{{ route('failures.create') }}" class="ops-button-primary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Reportar falla</a>
                @endcan
            </div>
        </div>

        <form method="GET" class="grid gap-4 border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-4">
            <div><label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</label><select id="status" name="status" class="mt-1.5 block w-full border-slate-300 bg-white text-sm"><option value="">Todos</option>@foreach ($labels['statuses'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="severity" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Severidad</label><select id="severity" name="severity" class="mt-1.5 block w-full border-slate-300 bg-white text-sm"><option value="">Todas</option>@foreach ($labels['severities'] as $value => $label)<option value="{{ $value }}" @selected(($filters['severity'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="failure_type" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</label><select id="failure_type" name="failure_type" class="mt-1.5 block w-full border-slate-300 bg-white text-sm"><option value="">Todos</option>@foreach ($labels['types'] as $value => $label)<option value="{{ $value }}" @selected(($filters['failure_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="flex items-end"><button type="submit" class="inline-flex w-full items-center justify-center bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Filtrar</button></div>
        </form>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Reportes registrados</h2></div>
            @if ($failures->isEmpty())
                <p class="px-5 py-8 text-sm text-slate-500">No hay fallas reportadas.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3 font-semibold">Fecha</th><th class="px-5 py-3 font-semibold">Producto / lote</th><th class="px-5 py-3 font-semibold">Falla</th><th class="px-5 py-3 font-semibold">Severidad</th><th class="px-5 py-3 font-semibold">Estado</th><th class="px-5 py-3 font-semibold">Caso</th><th class="px-5 py-3"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($failures as $failure)
                                @php $status = $failure->status; @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $failure->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-4"><p class="font-medium text-slate-900">{{ $failure->product?->product_code ?? $failure->inventoryLot?->product?->product_code ?? 'No especificado' }}</p><p class="mt-1 text-xs text-slate-500">{{ $failure->inventoryLot?->lot ?: 'Sin lote' }}{{ $failure->inventoryLot?->warehouse ? ' / '.$failure->inventoryLot->warehouse->name : '' }}</p></td>
                                    <td class="px-5 py-4"><p class="font-medium text-slate-900">{{ $labels['types'][$failure->failure_type] ?? str_replace('_', ' ', ucfirst($failure->failure_type)) }}</p><p class="mt-1 text-xs text-slate-500">{{ $labels['moments'][$failure->occurrence_moment] ?? str_replace('_', ' ', ucfirst($failure->occurrence_moment)) }}</p></td>
                                    <td class="px-5 py-4 text-slate-700">{{ $labels['severities'][$failure->severity] ?? ucfirst($failure->severity) }}</td>
                                    <td class="px-5 py-4"><span class="inline-flex px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$status] ?? 'bg-slate-100 text-slate-600' }}">{{ $labels['statuses'][$status] ?? ucfirst($status) }}</span></td>
                                    <td class="px-5 py-4 text-slate-700">{{ $failure->case?->case_code ?: 'Manual' }}</td>
                                    <td class="px-5 py-4 text-right"><a href="{{ route('failures.show', $failure) }}" class="font-semibold text-sky-700 hover:text-sky-900">Ver detalle</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-4">{{ $failures->links() }}</div>
            @endif
        </section>
    </div>
@endsection
