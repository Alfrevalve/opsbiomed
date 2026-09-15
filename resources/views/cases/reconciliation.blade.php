@extends('layouts.ops')

@section('title', 'Conciliacion | '.$case->case_code)

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('cases.show', $case) }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al detalle</a>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Conciliacion quirurgica</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $case->case_code }} · {{ $case->institution?->name }} · {{ $case->doctor?->name }}</p>
            </div>
            <span class="inline-flex h-fit bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-700">{{ $summary['reconciliation']?->status ?? 'pendiente' }}</span>
        </div>

        @if ($errors->any())
            <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Estado de conciliacion: {{ $summary['status'] }}</h2>
                <p class="mt-1 text-sm text-slate-500">La suma de usado, devuelto, abierto no usado y falla debe coincidir con lo reservado.</p>
            </div>
            @if ($summary['rows']->isEmpty())
                <p class="px-5 py-8 text-sm text-slate-500">No hay consumos registrados.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-5 py-3 font-semibold">Producto / lote</th><th class="px-5 py-3 font-semibold">Reservado</th><th class="px-5 py-3 font-semibold">Usado</th><th class="px-5 py-3 font-semibold">Devuelto</th><th class="px-5 py-3 font-semibold">Abierto no usado</th><th class="px-5 py-3 font-semibold">Falla</th><th class="px-5 py-3 font-semibold">Diferencia</th><th class="px-5 py-3 font-semibold">Motivo</th><th class="px-5 py-3 font-semibold">Estado</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($summary['rows'] as $row)
                                <tr>
                                    <td class="px-5 py-4 text-slate-700">{{ $row['product_code'] }}<span class="mt-1 block text-xs text-slate-500">{{ $row['lot'] ?? 'Sin lote' }}</span></td>
                                    <td class="px-5 py-4 text-slate-700">{{ $row['reserved_qty'] }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $row['used_qty'] }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $row['returned_qty'] }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $row['unused_opened_qty'] }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $row['failure_qty'] }}</td>
                                    <td class="px-5 py-4 {{ $row['difference_qty'] > 0 ? 'font-semibold text-rose-700' : 'text-slate-700' }}">{{ $row['difference_qty'] }}</td>
                                    <td class="max-w-xs px-5 py-4 text-slate-700">{{ $row['difference_reason'] ?? 'Sin diferencia' }}</td>
                                    <td class="px-5 py-4"><span class="inline-flex bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $row['status'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($summary['rows']->isNotEmpty())
            <form method="POST" action="{{ route('cases.reconciliation.store', $case) }}" class="border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <label class="block text-sm font-medium text-slate-700">
                    Observaciones
                    <textarea name="observations" rows="3" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('observations', $summary['reconciliation']?->observations) }}</textarea>
                </label>
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Completar conciliacion</button>
                </div>
            </form>
        @endif
    </div>
@endsection
