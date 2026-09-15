@extends('layouts.ops')

@section('title', $user->name.' | Administracion OPS BIOMED')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver a usuarios</a>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ $user->name }}</h1>
                <p class="mt-2 text-sm text-slate-500">Ficha de acceso y trazabilidad de la cuenta.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-800">Editar usuario</a>
                @if ($user->id !== auth()->id())
                    @if ($user->active)
                        <form method="POST" action="{{ route('admin.users.disable', $user) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center border border-rose-300 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">Inactivar</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.users.enable', $user) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center border border-emerald-300 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Activar</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Datos de usuario</h2></div>
                <dl class="grid gap-5 p-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nombre</dt><dd class="mt-1 text-sm text-slate-900">{{ $user->name }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt><dd class="mt-1 break-words text-sm text-slate-900">{{ $user->email }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</dt><dd class="mt-1 text-sm font-semibold {{ $user->active ? 'text-emerald-700' : 'text-slate-600' }}">{{ $user->active ? 'Activo' : 'Inactivo' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ultimo login</dt><dd class="mt-1 text-sm text-slate-900">{{ $user->last_login_at?->format('d/m/Y H:i') ?: 'Nunca registrado' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo</dt><dd class="mt-1 text-sm text-slate-900">{{ $user->job_title ?: 'No registrado' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Area</dt><dd class="mt-1 text-sm text-slate-900">{{ $user->area ?: 'No registrada' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefono</dt><dd class="mt-1 text-sm text-slate-900">{{ $user->phone ?: 'No registrado' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Institucion</dt><dd class="mt-1 text-sm text-slate-900">{{ $user->institution?->name ?: 'No asignada' }}</dd></div>
                </dl>
            </div>
            <div class="border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Roles y permisos</h2></div>
                <div class="p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Roles</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse ($user->roles as $role)
                            <span class="border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">{{ $role->name }}</span>
                        @empty
                            <span class="text-sm text-slate-500">Sin roles asignados.</span>
                        @endforelse
                    </div>
                    <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-slate-500">Permisos efectivos</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @forelse ($permissions as $permission)
                            <span class="text-sm text-slate-700">{{ $permission }}</span>
                        @empty
                            <span class="text-sm text-slate-500">Sin permisos efectivos.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Historial de auditoria</h2></div>
            @if ($auditLogs->isEmpty())
                <p class="px-5 py-8 text-sm text-slate-500">No hay eventos de auditoria para este usuario.</p>
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
    </div>
@endsection
