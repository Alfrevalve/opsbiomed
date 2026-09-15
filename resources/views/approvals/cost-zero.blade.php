@extends('layouts.ops')

@section('title', 'Aprobaciones de costo cero | OPS BIOMED MR8')

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium text-sky-700">Control administrativo</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Aprobaciones de costo cero</h1>
            <p class="mt-2 text-sm text-slate-500">Revisa las valorizaciones que requieren aprobacion antes de cerrar su tratamiento administrativo.</p>
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

        @if ($approvals->isEmpty())
            <section class="border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
                No hay solicitudes de costo cero pendientes de aprobacion.
            </section>
        @else
            <div class="space-y-4">
                @foreach ($approvals as $approval)
                    <section class="border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-start">
                            <div>
                                <a href="{{ route('cases.show', $approval->case) }}" class="font-semibold text-sky-700 hover:text-sky-900">{{ $approval->case?->case_code }}</a>
                                <p class="mt-1 text-sm text-slate-700">{{ $approval->case?->institution?->name }} · {{ $approval->case?->doctor?->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">Solicitado por {{ $approval->requestedBy?->name ?? 'Usuario' }} el {{ $approval->created_at?->format('d/m/Y H:i') }}</p>
                            </div>
                            <span class="inline-flex w-fit bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">{{ $approval->status }}</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-5 py-3 font-semibold">Producto</th>
                                        <th class="px-5 py-3 font-semibold">Cantidad</th>
                                        <th class="px-5 py-3 font-semibold">Motivo</th>
                                        <th class="px-5 py-3 text-right font-semibold">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($approval->valuation?->lines ?? [] as $line)
                                        <tr>
                                            <td class="px-5 py-4 text-slate-700">{{ $line->product?->product_code }} <span class="block text-xs text-slate-500">{{ $line->product?->name }}</span></td>
                                            <td class="px-5 py-4 text-slate-700">{{ $line->quantity_used }}</td>
                                            <td class="max-w-md px-5 py-4 text-slate-700">{{ $line->cost_zero_reason ?: 'Sin motivo registrado' }}</td>
                                            <td class="px-5 py-4 text-right font-semibold text-slate-900">S/ {{ number_format((float) $line->subtotal, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-5 py-4 text-sm text-slate-500">No hay lineas de valorizacion asociadas.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-slate-200 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total preliminar</p>
                                <p class="mt-1 text-xl font-semibold text-slate-950">S/ {{ number_format((float) ($approval->valuation?->total ?? 0), 2) }}</p>
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                <form method="POST" action="{{ route('approvals.cost-zero.approve', $approval->valuation) }}" class="flex flex-col gap-2">
                                    @csrf
                                    <label class="text-xs font-medium text-slate-600">Evidencia opcional
                                        <input type="text" name="evidence" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Acta o referencia">
                                    </label>
                                    <button type="submit" class="bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Aprobar costo cero</button>
                                </form>
                                <form method="POST" action="{{ route('approvals.cost-zero.reject', $approval->valuation) }}" class="flex flex-col gap-2">
                                    @csrf
                                    <label class="text-xs font-medium text-slate-600">Motivo del rechazo
                                        <input type="text" name="reason" required class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500" placeholder="Indica la correccion requerida">
                                    </label>
                                    <button type="submit" class="border border-rose-300 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">Rechazar</button>
                                </form>
                            </div>
                        </div>
                        @can('documents.view')
                            <div class="border-t border-slate-200 px-5 py-4">
                                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"><p class="text-sm font-semibold text-slate-900">Documentos de aprobacion</p><a href="{{ route('cases.documents.index', $approval->case) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Gestionar evidencias</a></div>
                                @include('documents._list', ['documents' => $approval->documents])
                            </div>
                        @endcan
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endsection
