@extends('layouts.ops')

@section('title', 'Liberar reserva')

@section('content')
    @php($reservation = $preview['reservation'])

    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <a href="{{ route('cases.control', $reservation->case) }}" class="text-sm font-semibold text-cyan-800 hover:underline">Volver al control operativo</a>
            <p class="ops-eyebrow mt-4">Reserva quirurgica</p>
            <h1 class="ops-section-title mt-1 text-2xl font-semibold">Liberacion total de reserva</h1>
            <p class="ops-muted mt-2 text-sm">Esta accion no modifica el stock fisico ni permite liberaciones parciales.</p>
        </div>

        @if ($errors->has('release'))
            <div class="border border-rose-300 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900" role="alert">{{ $errors->first('release') }}</div>
        @endif

        <section class="ops-card p-5">
            <h2 class="ops-section-title text-lg font-semibold">Material y caso</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="ops-muted text-xs font-semibold uppercase">Caso</dt><dd class="mt-1 font-semibold">{{ $reservation->case?->case_code }} / {{ $reservation->case?->scheduled_at?->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="ops-muted text-xs font-semibold uppercase">Institucion</dt><dd class="mt-1 font-semibold">{{ $reservation->case?->institution?->name ?: 'No registrada' }}</dd></div>
                <div><dt class="ops-muted text-xs font-semibold uppercase">Producto</dt><dd class="mt-1 font-semibold">{{ $reservation->inventoryLot?->product?->name ?: 'Producto no registrado' }}</dd><p class="ops-muted font-mono text-xs">{{ $reservation->inventoryLot?->product?->product_code ?: 'Sin codigo' }}</p></div>
                <div><dt class="ops-muted text-xs font-semibold uppercase">Lote / serie</dt><dd class="mt-1 font-semibold">{{ $reservation->inventoryLot?->lot ?: 'Sin lote' }}{{ $reservation->inventoryLot?->serial ? ' / '.$reservation->inventoryLot->serial : '' }}</dd></div>
                <div><dt class="ops-muted text-xs font-semibold uppercase">Almacen</dt><dd class="mt-1 font-semibold">{{ $reservation->inventoryLot?->warehouse?->name ?: 'No registrado' }}</dd></div>
                <div><dt class="ops-muted text-xs font-semibold uppercase">Cantidad reservada</dt><dd class="mt-1 font-semibold">{{ number_format((int) $reservation->quantity) }}</dd></div>
                <div><dt class="ops-muted text-xs font-semibold uppercase">Estado de reserva</dt><dd class="mt-1"><span class="ops-badge-info">{{ $reservation->status }}</span></dd></div>
            </dl>
        </section>

        @if ($preview['eligible'])
            <section class="ops-card border-l-4 border-emerald-700 p-5">
                <p class="font-semibold text-emerald-900">Liberacion directa elegible</p>
                <p class="ops-muted mt-1 text-sm">La cantidad reservada se descontara de la reserva activa. El stock fisico permanece igual y la disponibilidad se recalcula con las reglas actuales.</p>
            </section>
            <form method="POST" action="{{ route('reservations.release', $reservation) }}" class="ops-card space-y-4 p-5">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <div>
                    <label for="reason" class="block text-sm font-semibold">Motivo obligatorio</label>
                    <textarea id="reason" name="reason" rows="3" required minlength="5" maxlength="2000" class="ops-input mt-1 w-full">{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-1 text-sm text-rose-800">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="ops-button-danger px-4 py-2.5 text-sm font-semibold">Liberar reserva completa</button>
            </form>
        @else
            <section class="ops-card border-l-4 border-amber-600 p-5" role="status">
                <h2 class="font-semibold text-amber-950">Liberacion directa bloqueada</h2>
                <p class="mt-2 text-sm text-amber-950">{{ $preview['reason'] }}</p>
                @if (str_contains((string) $preview['reason'], 'devolucion'))
                    <a href="{{ route('returns.index') }}" class="mt-3 inline-flex text-sm font-semibold text-cyan-900 underline">Ir a devoluciones e inspeccion</a>
                @endif
            </section>
        @endif
    </div>
@endsection
