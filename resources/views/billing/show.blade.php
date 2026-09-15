@extends('layouts.ops')

@section('title', 'Facturacion | '.$case->case_code)

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('billing.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver a facturacion</a>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Facturacion y cobranza</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $case->case_code }} · {{ $case->institution?->name }} · {{ $case->doctor?->name }}</p>
            </div>
            <span class="inline-flex h-fit bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-700">{{ $billing->invoice_status }}</span>
        </div>

        @can('alerts.view')
            @include('alerts._related', ['slaAlerts' => $slaAlerts])
        @endcan

        @if ($errors->any())
            <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Datos del caso</h2></div>
            <dl class="grid gap-5 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Institucion</dt><dd class="mt-1 text-sm text-slate-900">{{ $case->institution?->name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Medico</dt><dd class="mt-1 text-sm text-slate-900">{{ $case->doctor?->name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha de cirugia</dt><dd class="mt-1 text-sm text-slate-900">{{ $case->scheduled_at?->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Conciliacion</dt><dd class="mt-1 text-sm text-slate-900">{{ $case->reconciliation?->status ?? 'pendiente' }}</dd></div>
            </dl>
        </section>

        <section class="grid gap-4 sm:grid-cols-3">
            <div class="border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total valorizado</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ $canViewAmounts ? 'S/ '.number_format((float) $billing->amount, 2) : 'Restringido' }}</p></div>
            <div class="border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Monto pagado</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ $canViewAmounts ? 'S/ '.number_format((float) $billing->amount_paid, 2) : 'Restringido' }}</p></div>
            <div class="border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo</p><p class="mt-2 text-2xl font-semibold {{ $canViewAmounts && $billing->balance > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $canViewAmounts ? 'S/ '.number_format($billing->balance, 2) : 'Restringido' }}</p></div>
        </section>

        @if ($canUpdate && $canViewAmounts)
            <form method="POST" action="{{ route('billing.update', $case) }}" class="border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                @method('PATCH')
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm font-medium text-slate-700">Estado facturacion
                        <select name="invoice_status" required class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            @foreach ($invoiceStatuses as $status)<option value="{{ $status }}" @selected(old('invoice_status', $billing->invoice_status) === $status)>{{ $status }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Estado pago
                        <select name="payment_status" required class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            @foreach ($paymentStatuses as $status)<option value="{{ $status }}" @selected(old('payment_status', $billing->payment_status) === $status)>{{ $status }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Monto pagado
                        <input type="number" name="amount_paid" min="0" step="0.01" required value="{{ old('amount_paid', $billing->amount_paid ?? 0) }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Orden de compra
                        <input type="text" name="purchase_order" value="{{ old('purchase_order', $billing->purchase_order) }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Numero de factura
                        <input type="text" name="invoice_number" value="{{ old('invoice_number', $billing->invoice_number) }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Fecha factura
                        <input type="date" name="invoice_date" value="{{ old('invoice_date', $billing->invoice_date?->format('Y-m-d')) }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Fecha vencimiento
                        <input type="date" name="due_date" value="{{ old('due_date', $billing->due_date?->format('Y-m-d')) }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    </label>
                    <label class="text-sm font-medium text-slate-700 sm:col-span-2">Observaciones
                        <textarea name="observations" rows="3" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('observations', $billing->observations) }}</textarea>
                    </label>
                </div>
                <div class="mt-5 flex justify-end"><button type="submit" class="bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Guardar estado administrativo</button></div>
            </form>
        @else
            <section class="border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
                Tu perfil puede consultar el estado comercial, pero no editar importes ni pagos.
            </section>
        @endif

        @can('documents.view')
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center"><div><h2 class="font-semibold text-slate-950">Documentos de facturacion</h2><p class="mt-1 text-sm text-slate-500">OC, factura, boleta, pagos y respaldo administrativo del caso.</p></div><a href="{{ route('cases.documents.index', $case) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Gestionar documentos</a></div>
                @include('documents._list', ['documents' => $case->documents->merge($case->billingRecord?->documents ?? collect())])
            </section>
        @endcan

        @if ($canAudit)
            <section class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Historial de auditoria</h2></div>
                @if ($auditLogs->isEmpty())
                    <p class="px-5 py-8 text-sm text-slate-500">No hay eventos de auditoria para esta cuenta.</p>
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
