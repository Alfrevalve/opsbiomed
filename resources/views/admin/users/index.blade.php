@extends('layouts.ops')

@section('title', 'Usuarios | Administracion OPS BIOMED')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-sky-700">Administracion</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Usuarios y accesos</h1>
                <p class="mt-2 text-sm text-slate-500">Gestiona cuentas, roles y estado de acceso operativo.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('manual.view')
                    <a href="{{ route('manual.show', 'administrar-usuarios-y-permisos') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Guia de usuarios</a>
                @endcan
                <a href="{{ route('admin.users.create') }}" class="ops-button-primary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Nuevo usuario</a>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-4 border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-[1fr_200px_auto] sm:items-end">
            <label class="text-sm font-medium text-slate-700">Buscar
                <input name="search" type="search" value="{{ $filters['search'] }}" placeholder="Nombre o email" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            </label>
            <label class="text-sm font-medium text-slate-700">Estado
                <select name="status" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Todos</option>
                    <option value="active" @selected($filters['status'] === 'active')>Activos</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
                </select>
            </label>
            <div class="flex gap-3">
                <button type="submit" class="bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Filtrar</button>
                <a href="{{ route('admin.users.index') }}" class="border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpiar</a>
            </div>
        </form>

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Usuario</th>
                            <th class="px-5 py-3 font-semibold">Rol</th>
                            <th class="px-5 py-3 font-semibold">Estado</th>
                            <th class="px-5 py-3 font-semibold">Ultimo login</th>
                            <th class="px-5 py-3 text-right font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($users as $user)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4">
                                    <a href="{{ route('admin.users.show', $user) }}" class="font-semibold text-sky-700 hover:text-sky-900">{{ $user->name }}</a>
                                    <p class="mt-1 text-xs text-slate-500">{{ $user->email }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-700">{{ $user->roles->pluck('name')->join(', ') ?: 'Sin rol' }}</td>
                                <td class="px-5 py-4"><span class="inline-flex px-2.5 py-1 text-xs font-semibold {{ $user->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $user->active ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="px-5 py-4 text-slate-700">{{ $user->last_login_at?->format('d/m/Y H:i') ?: 'Nunca' }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap justify-end gap-3 text-xs font-semibold">
                                        <a href="{{ route('admin.users.show', $user) }}" class="text-sky-700 hover:text-sky-900">Ver</a>
                                        <a href="{{ route('admin.users.edit', $user) }}" class="text-slate-700 hover:text-slate-950">Editar</a>
                                        @if ($user->id !== auth()->id())
                                            @if ($user->active)
                                                <form method="POST" action="{{ route('admin.users.disable', $user) }}">
                                                    @csrf
                                                    <button type="submit" class="text-rose-700 hover:text-rose-900">Inactivar</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.users.enable', $user) }}">
                                                    @csrf
                                                    <button type="submit" class="text-emerald-700 hover:text-emerald-900">Activar</button>
                                                </form>
                                            @endif
                                        @endif
                                        <a href="{{ route('admin.users.edit', $user) }}#reset-password" class="text-slate-700 hover:text-slate-950">Reset password</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-sm text-slate-500">No hay usuarios registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $users->links() }}</div>
            @endif
        </section>
    </div>
@endsection
