@extends('layouts.ops')

@section('title', 'Facturacion y cobranza | OPS BIOMED MR8')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-sky-700">Control administrativo</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Facturacion y cobranza</h1>
                <p class="mt-2 text-sm text-slate-500">Seguimiento de valorizacion, ordenes de compra, facturas y saldos.</p>
            </div>
            @can('manual.view')
                <a href="{{ route('manual.show', 'revisar-facturacion-y-cobranza') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Guia de cobranza</a>
            @endcan
        </div>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Caso</th>
                            <th class="px-5 py-3 font-semibold">Institucion</th>
                            <th class="px-5 py-3 font-semibold">Cirugia</th>
                            <th class="px-5 py-3 font-semibold">Estado facturacion</th>
                            @if ($canViewAmounts)
                                <th class="px-5 py-3 text-right font-semibold">Total</th>
                                <th class="px-5 py-3 text-right font-semibold">Pagado</th>
                                <th class="px-5 py-3 text-right font-semibold">Saldo</th>
                                <th class="px-5 py-3 font-semibold">Pago</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($records as $record)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-4"><a href="{{ route('billing.show', $record->case) }}" class="font-semibold text-sky-700 hover:text-sky-900">{{ $record->case?->case_code }}</a><span class="mt-1 block text-xs text-slate-500">{{ $record->case?->doctor?->name }}</span></td>
                                <td class="px-5 py-4 text-slate-700">{{ $record->case?->institution?->name }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $record->case?->scheduled_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-4"><span class="inline-flex bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">{{ $record->invoice_status }}</span></td>
                                @if ($canViewAmounts)
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-slate-900">S/ {{ number_format((float) $record->amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">S/ {{ number_format((float) $record->amount_paid, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold {{ $record->balance > 0 ? 'text-rose-700' : 'text-emerald-700' }}">S/ {{ number_format($record->balance, 2) }}</td>
                                    <td class="px-5 py-4"><span class="inline-flex px-2.5 py-1 text-xs font-semibold {{ $record->payment_status === 'vencido' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-700' }}">{{ $record->payment_status }}</span></td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-8 text-sm text-slate-500">No hay facturas pendientes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($records->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $records->links() }}</div>
            @endif
        </section>
    </div>
@endsection
