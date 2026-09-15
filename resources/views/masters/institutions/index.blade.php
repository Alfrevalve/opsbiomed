@extends('layouts.ops')

@section('title', 'Instituciones | OPS BIOMED MR8')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-sky-700">Maestros operativos</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Instituciones</h1>
                <p class="mt-2 text-sm text-slate-500">Administra cuentas, contactos y condiciones comerciales.</p>
            </div>
            @can('masters.manage')
                <a href="{{ route('masters.institutions.create') }}" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-800">Nueva institucion</a>
            @endcan
        </div>

        <form method="GET" class="grid gap-4 border border-slate-200 bg-white p-4 sm:grid-cols-3">
            <div>
                <label for="active" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</label>
                <select id="active" name="active" class="mt-1.5 block w-full border-slate-300 bg-white text-sm">
                    <option value="">Todos</option>
                    <option value="active" @selected(request('active') === 'active')>Activas</option>
                    <option value="inactive" @selected(request('active') === 'inactive')>Inactivas</option>
                </select>
            </div>
            <div>
                <label for="billing_policy" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Condicion comercial</label>
                <select id="billing_policy" name="billing_policy" class="mt-1.5 block w-full border-slate-300 bg-white text-sm">
                    <option value="">Todas</option>
                    @foreach ($commercialConditions as $value)
                        <option value="{{ $value }}" @selected(request('billing_policy') === $value)>{{ str_replace('_', ' ', ucfirst($value)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="debt_status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Estado deuda</label>
                <select id="debt_status" name="debt_status" class="mt-1.5 block w-full border-slate-300 bg-white text-sm">
                    <option value="">Todos</option>
                    @foreach ($debtStatuses as $value)
                        <option value="{{ $value }}" @selected(request('debt_status') === $value)>{{ str_replace('_', ' ', ucfirst($value)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-3 flex justify-end gap-3">
                <a href="{{ route('masters.institutions.index') }}" class="border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpiar</a>
                <button type="submit" class="bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
            </div>
        </form>

        <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
            @if ($institutions->isEmpty())
                <div class="px-6 py-12 text-center text-sm text-slate-500">No hay instituciones registradas.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Institucion</th><th class="px-5 py-3">RUC</th><th class="px-5 py-3">Condicion</th><th class="px-5 py-3">Deuda</th><th class="px-5 py-3">Medicos</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($institutions as $institution)
                                <tr class="align-top"><td class="px-5 py-4"><a href="{{ route('masters.institutions.show', $institution) }}" class="font-semibold text-sky-700 hover:text-sky-900">{{ $institution->name }}</a><p class="mt-1 text-xs text-slate-500">{{ str_replace('_', ' ', ucfirst($institution->institution_type ?? 'otro')) }}</p></td><td class="px-5 py-4 text-slate-600">{{ $institution->ruc ?: '—' }}</td><td class="px-5 py-4 text-slate-600">{{ str_replace('_', ' ', ucfirst($institution->billing_policy ?: 'regular')) }}</td><td class="px-5 py-4 text-slate-600">{{ str_replace('_', ' ', ucfirst($institution->debt_status ?: 'al_dia')) }}</td><td class="px-5 py-4 text-slate-600">{{ $institution->doctors_count }}</td><td class="px-5 py-4"><span class="inline-flex px-2 py-1 text-xs font-semibold {{ $institution->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $institution->active ? 'Activa' : 'Inactiva' }}</span></td><td class="px-5 py-4 text-right"><a href="{{ route('masters.institutions.edit', $institution) }}" class="font-semibold text-slate-700 hover:text-sky-700">Editar</a></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-4">{{ $institutions->links() }}</div>
            @endif
        </div>
    </div>
@endsection
