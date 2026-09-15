@php
    $accountStatus = $user->getAttribute('active') === null
        ? 'No registrado'
        : ($user->active ? 'Activo' : 'Inactivo');
    $accountStatusClass = $user->active
        ? 'ops-badge-success'
        : 'ops-badge-neutral';
    $initials = collect(preg_split('/\s+/', trim($user->name)))
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

@extends('layouts.ops')

@section('title', 'Mi perfil | OPS BIOMED MR8')

@section('content')
    <div class="ops-profile space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="ops-eyebrow text-sm font-medium">Cuenta personal</p>
                <h1 class="ops-section-title mt-1 text-2xl font-semibold tracking-tight">Mi perfil</h1>
                <p class="mt-2 text-sm text-slate-500">Datos de cuenta, seguridad y acceso operativo.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('manual.view')
                    <a href="{{ route('manual.index') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Manual operativo</a>
                @endcan
                <a href="{{ route('dashboard.ops') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Volver al dashboard</a>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.35fr)]">
            <div class="space-y-6">
                <section class="ops-card">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resumen del usuario</p>
                    </div>
                    <div class="p-5">
                        <div class="flex items-center gap-4">
                            <span class="ops-avatar flex h-14 w-14 shrink-0 items-center justify-center text-lg font-semibold" aria-hidden="true">{{ $initials ?: 'OB' }}</span>
                            <div class="min-w-0">
                                <h2 class="truncate text-lg font-semibold text-slate-950">{{ $user->name }}</h2>
                                <p class="mt-1 truncate text-sm text-slate-500">{{ $user->email }}</p>
                            </div>
                        </div>

                        <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rol principal</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $role ?: 'No registrado' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</dt>
                                <dd class="mt-1"><span class="{{ $accountStatusClass }}">{{ $accountStatus }}</span></dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Area</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $user->area ?: 'No registrado' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $user->job_title ?: 'No registrado' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefono</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $user->phone ?: 'No registrado' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Institucion</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $user->institution?->name ?: 'No registrado' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Creado</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $user->created_at?->format('d/m/Y H:i') ?: 'No registrado' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ultimo login</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $user->last_login_at?->format('d/m/Y H:i') ?: 'No registrado' }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section class="ops-card">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="font-semibold text-slate-950">Acceso operativo</h2>
                        <p class="mt-1 text-sm text-slate-500">Permisos efectivos asignados a tu cuenta.</p>
                    </div>
                    <div class="p-5">
                        @forelse ($permissionGroups as $area => $permissions)
                            <div class="{{ $loop->first ? '' : 'mt-5 border-t border-slate-100 pt-5' }}">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $area }}</h3>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($permissions as $permission)
                                        <span class="ops-badge-neutral">{{ $permission }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No hay permisos operativos registrados.</p>
                        @endforelse
                    </div>
                </section>

                <section class="border border-sky-200 bg-sky-50 p-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-sky-200 bg-white text-sky-700">
                            <x-nav.icon name="shield-check" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="font-semibold text-sky-900">Uso responsable de IA</h2>
                            <p class="mt-2 text-sm leading-6 text-sky-800">OPS BIOMED puede usar funciones asistidas por IA únicamente como apoyo operativo. Las decisiones clínicas, técnicas, comerciales y administrativas requieren validación humana.</p>
                            @can('compliance.view')
                                <a href="{{ route('compliance.ai-policy') }}" class="mt-3 inline-flex text-sm font-semibold text-sky-700 hover:text-sky-900">Ver politica de uso de IA</a>
                            @endcan
                        </div>
                    </div>
                </section>
            </div>

            <div class="space-y-6">
                <section class="ops-card">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="font-semibold text-slate-950">Informacion de cuenta</h2>
                        <p class="mt-1 text-sm text-slate-500">Actualiza tu nombre y correo de acceso.</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </section>

                <section class="ops-card">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="font-semibold text-slate-950">Seguridad</h2>
                        <p class="mt-1 text-sm text-slate-500">Cambia tu password manteniendo el acceso protegido.</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        @include('profile.partials.update-password-form')
                    </div>
                </section>

                <section class="ops-card ops-card-danger">
                    <div class="border-b border-rose-100 px-5 py-4">
                        <h2 class="font-semibold text-rose-900">Zona de riesgo</h2>
                        <p class="mt-1 text-sm text-rose-700">La eliminacion de la cuenta es permanente y no se puede deshacer.</p>
                    </div>
                    <div class="p-5 sm:p-6">
                        @include('profile.partials.delete-user-form')
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
