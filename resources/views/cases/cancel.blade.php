@extends('layouts.ops')

@section('title', 'Cancelar caso | '.$case->case_code)

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <a href="{{ route('cases.control', $case) }}" class="text-sm font-semibold text-cyan-800 hover:underline">Volver al control operativo</a>
            <p class="ops-eyebrow mt-4">Accion de riesgo</p>
            <h1 class="ops-section-title mt-1 text-2xl font-semibold">Cancelar caso y revisar reservas</h1>
            <p class="ops-muted mt-2 text-sm">{{ $case->case_code }} / {{ $case->institution?->name ?: 'Institucion no registrada' }} / Estado: {{ $case->status->operationalLabel() }}</p>
        </div>

        @if ($errors->has('cancellation'))
            <div class="border border-rose-300 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900" role="alert">{{ $errors->first('cancellation') }}</div>
        @endif

        @if ($preview['case_reason'])
            <div class="border border-rose-300 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900" role="alert">{{ $preview['case_reason'] }}</div>
        @endif

        <section class="ops-card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="ops-section-title text-lg font-semibold">Reservas activas que se evaluaran</h2>
                <p class="ops-muted mt-1 text-sm">La operacion completa se cancela si una sola reserva no es elegible. Las reservas historicas se conservan.</p>
            </div>
            @if ($preview['reservations']->isEmpty())
                <p class="ops-muted px-5 py-6 text-sm">No hay reservas activas que liberar.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead><tr><th class="px-5 py-3">Producto / codigo</th><th class="px-5 py-3">Lote / almacen</th><th class="px-5 py-3 text-right">Cantidad</th><th class="px-5 py-3">Resultado</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($preview['reservations'] as $row)
                                @php($reservation = $row['reservation'])
                                <tr>
                                    <td class="px-5 py-4"><p class="font-semibold">{{ $reservation->inventoryLot?->product?->name ?: 'Producto no registrado' }}</p><p class="ops-muted font-mono text-xs">{{ $reservation->inventoryLot?->product?->product_code ?: 'Sin codigo' }}</p></td>
                                    <td class="px-5 py-4">{{ $reservation->inventoryLot?->lot ?: 'Sin lote' }} / {{ $reservation->inventoryLot?->warehouse?->name ?: 'Almacen no registrado' }}</td>
                                    <td class="px-5 py-4 text-right font-semibold">{{ number_format((int) $reservation->quantity) }}</td>
                                    <td class="px-5 py-4">
                                        @if ($row['eligible'])<span class="ops-badge-success">Se liberara</span>
                                        @else<span class="ops-badge-danger">Bloqueada</span><p class="mt-1 max-w-sm text-xs text-rose-900">{{ $row['reason'] }}</p>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($preview['can_cancel'])
            <form method="POST" action="{{ route('cases.cancel', $case) }}" class="ops-card space-y-4 p-5">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <div>
                    <label for="reason" class="block text-sm font-semibold">Motivo obligatorio de cancelacion</label>
                    <textarea id="reason" name="reason" rows="3" required minlength="5" maxlength="2000" class="ops-input mt-1 w-full">{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-1 text-sm text-rose-800">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-3 text-sm font-medium text-slate-900">
                    <input type="checkbox" name="confirmation" value="1" required class="mt-1 rounded border-slate-400 text-teal-800 focus:ring-teal-700">
                    <span>Confirmo cancelar este caso y liberar todas las reservas activas elegibles. Entiendo que el stock fisico no cambiara y que una reserva entregada requiere devolucion e inspeccion.</span>
                </label>
                @error('confirmation')<p class="text-sm text-rose-800">{{ $message }}</p>@enderror
                <button type="submit" class="ops-button-danger px-4 py-2.5 text-sm font-semibold">Confirmar cancelacion y liberacion total</button>
            </form>
        @else
            <section class="ops-card border-l-4 border-amber-600 p-5" role="status">
                <h2 class="font-semibold text-amber-950">Cancelacion bloqueada; no se liberara ninguna reserva</h2>
                <p class="ops-muted mt-2 text-sm">Resuelva los bloqueos anteriores. Si el material ya fue transferido, complete devolucion e inspeccion antes de cancelar.</p>
            </section>
        @endif
    </div>
@endsection
