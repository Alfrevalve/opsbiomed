@extends('layouts.ops')

@section('title', 'Solicitudes quirurgicas | OPS BIOMED MR8')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-sky-700">Operacion MR8</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Solicitudes quirurgicas</h1>
                <p class="mt-2 text-sm text-slate-500">Registro y seguimiento de cirugias programadas.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('manual.view')
                    <a href="{{ route('manual.show', 'consultar-solicitudes') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Guia de solicitudes</a>
                @endcan
                @can('cases.create')
                    <a href="{{ route('cases.create') }}" class="ops-button-primary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Nueva solicitud</a>
                @endcan
            </div>
        </div>

        <section class="border border-slate-200 bg-white shadow-sm">
            @if ($cases->isEmpty())
                <div class="px-5 py-12 text-center">
                    <h2 class="font-semibold text-slate-950">No hay solicitudes registradas.</h2>
                    <p class="mt-2 text-sm text-slate-500">Registra la primera solicitud quirurgica para alimentar el dashboard.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Solicitud</th>
                                <th class="px-5 py-3 font-semibold">Cirugia</th>
                                <th class="px-5 py-3 font-semibold">Institucion</th>
                                <th class="px-5 py-3 font-semibold">Medico</th>
                                <th class="px-5 py-3 font-semibold">Paciente</th>
                                <th class="px-5 py-3 font-semibold">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($cases as $case)
                                <tr class="hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <a href="{{ route('cases.show', $case) }}" class="font-semibold text-sky-700 hover:text-sky-900">{{ $case->case_code }}</a>
                                        <p class="mt-1 text-xs text-slate-500">{{ $case->created_at?->format('d/m/Y H:i') }}</p>
                                        <a href="{{ route('cases.control', $case) }}" class="mt-2 inline-flex text-xs font-semibold text-sky-700 hover:text-sky-900">Control</a>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $case->scheduled_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $case->institution?->name }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $case->doctor?->name }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $case->patient?->full_name }}</td>
                                    <td class="whitespace-nowrap px-5 py-4"><span class="inline-flex bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $case->status->label() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-4">{{ $cases->links() }}</div>
            @endif
        </section>
    </div>
@endsection
