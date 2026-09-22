@php
    $user = auth()->user();
    $userRole = $user?->getRoleNames()->first();
    $canBilling = $user?->can('billing.view') || $user?->can('commercial.view');
    $canApprovals = $user?->can('approvals.approve') && $user->hasAnyRole(['Administrador', 'Gerencia', 'Jefe de Linea']);
    $canForecast = $user?->hasAnyRole(['Administrador', 'Jefe de Linea', 'Direccion Tecnica', 'Almacen', 'Gerencia']);
    $statusMessage = match (session('status')) {
        'profile-updated' => 'Datos de cuenta actualizados correctamente.',
        'password-updated' => 'Password actualizado correctamente.',
        'verification-link-sent' => 'Se envio un nuevo enlace de verificacion.',
        'manual-progress-updated' => 'Tutorial marcado como leido.',
        default => session('status'),
    };
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'OPS BIOMED MR8')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ops-page min-h-screen antialiased">
    <aside
        id="ops-sidebar"
        class="ops-sidebar fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r transition-[width,transform] duration-200 ease-in-out lg:translate-x-0 lg:w-72"
        aria-label="Navegacion principal"
    >
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-slate-200 px-4">
            <a href="{{ route('dashboard.ops') }}" class="ops-sidebar-brand flex min-w-0 items-center gap-3" data-sidebar-brand>
                <img src="{{ asset('images/ops-biomed-logo.png') }}" alt="OPS BIOMED MR8" class="h-10 w-10 shrink-0 rounded-md object-contain" />
                <span class="min-w-0" data-sidebar-label>
                    <span class="ops-sidebar-brand-title block truncate text-sm font-bold tracking-wide">OPS BIOMED</span>
                    <span class="ops-sidebar-brand-subtitle block truncate text-[11px] font-medium">MR8 · Torre de Control</span>
                </span>
            </a>
            <button
                type="button"
                class="ops-sidebar-control inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md lg:hidden"
                aria-label="Cerrar menu"
                title="Cerrar menu"
                data-sidebar-close
            >
                <x-nav.icon name="close" class="h-5 w-5" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-5" aria-label="Areas del sistema">
            @can('dashboard.view')
                <details class="ops-sidebar-section" data-sidebar-section="dashboard" @if (request()->routeIs('dashboard.*')) open @endif>
                    <summary class="ops-sidebar-section-trigger flex cursor-pointer list-none items-center justify-between gap-3 rounded-md px-3 py-2 text-[10px] font-bold uppercase tracking-[0.14em]" title="Inicio">
                        <span class="flex min-w-0 items-center gap-3"><x-nav.icon name="dashboard" class="h-4 w-4 shrink-0" /><span data-sidebar-label>Inicio</span></span>
                        <x-nav.icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform" data-sidebar-chevron />
                    </summary>
                    <div class="mt-1 space-y-1">
                    <x-nav.link
                        :href="route('dashboard.ops')"
                        label="Dashboard operativo"
                        icon="dashboard"
                        :active="request()->routeIs('dashboard.ops')"
                    />
                    </div>
                </details>
            @endcan

            @if ($user?->can('schedule.view') || $user?->can('cases.view') || $user?->can('returns.view') || $user?->can('failures.view') || $user?->can('documents.view') || $user?->can('trace.view'))
                <details class="ops-sidebar-section mt-3" data-sidebar-section="operation" @if (request()->routeIs('schedule.*', 'cases.*', 'returns.*', 'failures.*', 'documents.*', 'trace.*')) open @endif>
                    <summary class="ops-sidebar-section-trigger flex cursor-pointer list-none items-center justify-between gap-3 rounded-md px-3 py-2 text-[10px] font-bold uppercase tracking-[0.14em]" title="Operacion quirurgica">
                        <span class="flex min-w-0 items-center gap-3"><x-nav.icon name="calendar" class="h-4 w-4 shrink-0" /><span data-sidebar-label>Operacion quirurgica</span></span>
                        <x-nav.icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform" data-sidebar-chevron />
                    </summary>
                    <div class="mt-1 space-y-1">
                    @can('schedule.view')
                        <x-nav.link
                            :href="route('schedule.index')"
                            label="Agenda quirurgica"
                            icon="calendar"
                            :active="request()->routeIs('schedule.*')"
                        />
                    @endcan
                    @can('cases.view')
                        <x-nav.link
                            :href="route('cases.index')"
                            label="Solicitudes"
                            icon="clipboard"
                            :active="request()->routeIs('cases.*')"
                        />
                    @endcan
                    @can('returns.view')
                        <x-nav.link
                            :href="route('returns.index')"
                            label="Devoluciones postoperatorias"
                            icon="rotate"
                            :active="request()->routeIs('returns.*')"
                        />
                    @endcan
                    @can('failures.view')
                        <x-nav.link
                            :href="route('failures.index')"
                            label="Fallas tecnicas"
                            icon="warning"
                            :active="request()->routeIs('failures.*')"
                        />
                    @endcan
                    @can('documents.view')
                        <x-nav.link
                            :href="route('documents.index')"
                            label="Documentos"
                            icon="file"
                            :active="request()->routeIs('documents.*') || request()->routeIs('cases.documents.*')"
                        />
                    @endcan
                    @can('trace.view')
                        <x-nav.link
                            :href="route('trace.index')"
                            label="Escanear"
                            icon="scan"
                            :active="request()->routeIs('trace.*')"
                        />
                    @endcan
                    </div>
                </details>
            @endif

            @if ($user?->can('inventory.view') || $user?->can('catalog.import'))
                <details class="ops-sidebar-section mt-3" data-sidebar-section="inventory" @if (request()->routeIs('inventory.*', 'catalog.imports.*')) open @endif>
                    <summary class="ops-sidebar-section-trigger flex cursor-pointer list-none items-center justify-between gap-3 rounded-md px-3 py-2 text-[10px] font-bold uppercase tracking-[0.14em]" title="Inventario MR8">
                        <span class="flex min-w-0 items-center gap-3"><x-nav.icon name="boxes" class="h-4 w-4 shrink-0" /><span data-sidebar-label>Inventario MR8</span></span>
                        <x-nav.icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform" data-sidebar-chevron />
                    </summary>
                    <div class="mt-1 space-y-1">
                    @can('inventory.view')
                        <x-nav.link
                            :href="route('inventory.index')"
                            label="Inventario"
                            icon="boxes"
                            :active="request()->routeIs('inventory.index', 'inventory.show', 'inventory.edit', 'inventory.adjust.create', 'inventory.adjust')"
                        />
                        <x-nav.link
                            :href="route('inventory.coverage')"
                            label="Cobertura MR8"
                            icon="shield-check"
                            :active="request()->routeIs('inventory.coverage')"
                        />
                        @if ($canForecast)
                            <x-nav.link
                                :href="route('inventory.forecast')"
                                label="Forecast MR8"
                                icon="trend"
                                :active="request()->routeIs('inventory.forecast*')"
                            />
                        @endif
                    @endcan
                    @can('catalog.import')
                        <x-nav.link
                            :href="route('catalog.imports.index')"
                            label="Catalogo / Importar Excel"
                            icon="upload"
                            :active="request()->routeIs('catalog.imports.*')"
                        />
                    @endcan
                    </div>
                </details>
            @endif

            @if ($canBilling || $canApprovals || $user?->can('reports.view'))
                <details class="ops-sidebar-section mt-3" data-sidebar-section="commercial" @if (request()->routeIs('billing.*', 'approvals.*', 'reports.*')) open @endif>
                    <summary class="ops-sidebar-section-trigger flex cursor-pointer list-none items-center justify-between gap-3 rounded-md px-3 py-2 text-[10px] font-bold uppercase tracking-[0.14em]" title="Comercial y cobranza">
                        <span class="flex min-w-0 items-center gap-3"><x-nav.icon name="receipt" class="h-4 w-4 shrink-0" /><span data-sidebar-label>Comercial y cobranza</span></span>
                        <x-nav.icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform" data-sidebar-chevron />
                    </summary>
                    <div class="mt-1 space-y-1">
                    @if ($canBilling)
                        <x-nav.link
                            :href="route('billing.index')"
                            label="Facturacion / Cobranza"
                            icon="receipt"
                            :active="request()->routeIs('billing.*')"
                        />
                    @endif
                    @if ($canApprovals)
                        <x-nav.link
                            :href="route('approvals.cost-zero.index')"
                            label="Aprobaciones"
                            icon="check-circle"
                            :active="request()->routeIs('approvals.cost-zero.*')"
                        />
                    @endif
                    @can('reports.view')
                        <x-nav.link
                            :href="route('reports.index')"
                            label="Reportes"
                            icon="chart"
                            :active="request()->routeIs('reports.*')"
                        />
                    @endcan
                    </div>
                </details>
            @endif

            @if ($user?->can('masters.view') || $user?->can('prices.view'))
                <details class="ops-sidebar-section mt-3" data-sidebar-section="masters" @if (request()->routeIs('masters.*')) open @endif>
                    <summary class="ops-sidebar-section-trigger flex cursor-pointer list-none items-center justify-between gap-3 rounded-md px-3 py-2 text-[10px] font-bold uppercase tracking-[0.14em]" title="Maestros">
                        <span class="flex min-w-0 items-center gap-3"><x-nav.icon name="building" class="h-4 w-4 shrink-0" /><span data-sidebar-label>Maestros</span></span>
                        <x-nav.icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform" data-sidebar-chevron />
                    </summary>
                    <div class="mt-1 space-y-1">
                    @can('masters.view')
                        <x-nav.link
                            :href="route('masters.institutions.index')"
                            label="Instituciones"
                            icon="building"
                            :active="request()->routeIs('masters.institutions.*')"
                        />
                        <x-nav.link
                            :href="route('masters.doctors.index')"
                            label="Medicos"
                            icon="doctor"
                            :active="request()->routeIs('masters.doctors.*')"
                        />
                    @endcan
                    @can('prices.view')
                        <x-nav.link
                            :href="route('masters.prices.index')"
                            label="Precios"
                            icon="currency"
                            :active="request()->routeIs('masters.prices.*')"
                        />
                    @endcan
                    </div>
                </details>
            @endif

            @can('users.manage')
                <details class="ops-sidebar-section mt-3" data-sidebar-section="administration" @if (request()->routeIs('admin.users.*')) open @endif>
                    <summary class="ops-sidebar-section-trigger flex cursor-pointer list-none items-center justify-between gap-3 rounded-md px-3 py-2 text-[10px] font-bold uppercase tracking-[0.14em]" title="Administracion">
                        <span class="flex min-w-0 items-center gap-3"><x-nav.icon name="users" class="h-4 w-4 shrink-0" /><span data-sidebar-label>Administracion</span></span>
                        <x-nav.icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform" data-sidebar-chevron />
                    </summary>
                    <div class="mt-1 space-y-1">
                    <x-nav.link
                        :href="route('admin.users.index')"
                        label="Usuarios"
                        icon="users"
                        :active="request()->routeIs('admin.users.*')"
                    />
                    </div>
                </details>
            @endcan

            @can('alerts.view')
                <details class="ops-sidebar-section mt-3" data-sidebar-section="alerts" @if (request()->routeIs('alerts.*')) open @endif>
                    <summary class="ops-sidebar-section-trigger flex cursor-pointer list-none items-center justify-between gap-3 rounded-md px-3 py-2 text-[10px] font-bold uppercase tracking-[0.14em]" title="Alertas SLA">
                        <span class="flex min-w-0 items-center gap-3"><x-nav.icon name="warning" class="h-4 w-4 shrink-0" /><span data-sidebar-label>Alertas SLA</span></span>
                        <x-nav.icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform" data-sidebar-chevron />
                    </summary>
                    <div class="mt-1 space-y-1">
                    <x-nav.link
                        :href="route('alerts.index')"
                        label="Centro de alertas"
                        icon="warning"
                        :active="request()->routeIs('alerts.*')"
                    />
                    </div>
                </details>
            @endcan

            @can('compliance.view')
                <details class="ops-sidebar-section mt-3" data-sidebar-section="compliance" @if (request()->routeIs('compliance.ai-policy')) open @endif>
                    <summary class="ops-sidebar-section-trigger flex cursor-pointer list-none items-center justify-between gap-3 rounded-md px-3 py-2 text-[10px] font-bold uppercase tracking-[0.14em]" title="Cumplimiento">
                        <span class="flex min-w-0 items-center gap-3"><x-nav.icon name="shield-check" class="h-4 w-4 shrink-0" /><span data-sidebar-label>Cumplimiento</span></span>
                        <x-nav.icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform" data-sidebar-chevron />
                    </summary>
                    <div class="mt-1 space-y-1">
                    <x-nav.link
                        :href="route('compliance.ai-policy')"
                        label="Politica de uso de IA"
                        icon="shield-check"
                        :active="request()->routeIs('compliance.ai-policy')"
                    />
                    </div>
                </details>
            @endcan

            @can('manual.view')
                <details class="ops-sidebar-section mt-3" data-sidebar-section="manual" @if (request()->routeIs('manual.*', 'admin.manual.*')) open @endif>
                    <summary class="ops-sidebar-section-trigger flex cursor-pointer list-none items-center justify-between gap-3 rounded-md px-3 py-2 text-[10px] font-bold uppercase tracking-[0.14em]" title="Ayuda y manual">
                        <span class="flex min-w-0 items-center gap-3"><x-nav.icon name="book" class="h-4 w-4 shrink-0" /><span data-sidebar-label>Ayuda y manual</span></span>
                        <x-nav.icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform" data-sidebar-chevron />
                    </summary>
                    <div class="mt-1 space-y-1">
                        <x-nav.link
                            :href="route('manual.index')"
                            label="Manual operativo"
                            icon="book"
                            :active="request()->routeIs('manual.index', 'manual.search', 'manual.show')"
                        />
                        <x-nav.link
                            :href="route('manual.quick-start')"
                            label="Inicio rapido"
                            icon="check-circle"
                            :active="request()->routeIs('manual.quick-start')"
                        />
                        @can('manual.manage')
                            <x-nav.link
                                :href="route('admin.manual.index')"
                                label="Administrar contenido"
                                icon="book"
                                :active="request()->routeIs('admin.manual.*')"
                            />
                        @endcan
                    </div>
                </details>
            @endcan
        </nav>

        <div class="ops-sidebar-footer hidden border-t p-3 lg:block">
            <div class="mb-3 flex items-center gap-3 px-3">
                <span class="ops-avatar flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold" aria-hidden="true">{{ str($user->name)->substr(0, 1)->upper() }}</span>
                <div class="min-w-0" data-sidebar-label>
                    <p class="truncate text-xs font-semibold text-white">{{ $user->name }}</p>
                    <p class="mt-0.5 truncate text-[11px] text-slate-300">{{ $userRole ?: 'Usuario operativo' }}</p>
                </div>
            </div>
            <a href="{{ route('profile.edit') }}" class="ops-nav-link group flex items-center justify-start gap-3 rounded-md border-l-2 border-transparent px-3 py-2.5 text-sm font-medium" title="Mi perfil" aria-label="Mi perfil" data-sidebar-link>
                <x-nav.icon name="users" class="h-5 w-5" />
                <span class="min-w-0 truncate" data-sidebar-label>Mi perfil</span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="ops-nav-link group flex w-full items-center justify-start gap-3 rounded-md border-l-2 border-transparent px-3 py-2.5 text-left text-sm font-medium" title="Cerrar sesion" aria-label="Cerrar sesion" data-sidebar-link>
                    <x-nav.icon name="logout" class="h-5 w-5" />
                    <span class="min-w-0 truncate" data-sidebar-label> Cerrar sesion</span>
                </button>
            </form>
            <p class="ops-sidebar-footer-copy mt-3 truncate px-3 text-[10px]" data-sidebar-label>OPS BIOMED MR8 · v1.0</p>
        </div>
    </aside>

    <div id="ops-sidebar-overlay" class="ops-sidebar-overlay fixed inset-0 z-40 hidden lg:hidden" data-sidebar-overlay></div>

    <div id="ops-main-wrapper" class="min-h-screen transition-[padding] duration-200 ease-in-out lg:pl-72">
        <header class="ops-topbar sticky top-0 z-30 border-b backdrop-blur">
            <div class="mx-auto flex h-16 max-w-[1600px] items-center gap-3 px-4 sm:px-6 lg:px-8">
                <button
                    type="button"
                    class="ops-topbar-control inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md lg:hidden"
                    aria-label="Abrir menu"
                    title="Abrir menu"
                    aria-expanded="false"
                    data-sidebar-toggle
                >
                    <x-nav.icon name="menu" class="h-5 w-5" />
                </button>
                <div class="min-w-0 lg:hidden">
                    <p class="truncate text-sm font-bold tracking-wide text-slate-950">OPS BIOMED <span class="font-normal text-slate-500">MR8</span></p>
                </div>
                <button
                    type="button"
                    class="ops-topbar-control hidden h-9 w-9 shrink-0 items-center justify-center rounded-md lg:inline-flex"
                    aria-label="Contraer menu"
                    title="Contraer menu"
                    aria-pressed="false"
                    data-sidebar-toggle
                >
                    <span data-sidebar-expanded-icon><x-nav.icon name="chevron-left" class="h-5 w-5" /></span>
                    <span class="hidden" data-sidebar-collapsed-icon><x-nav.icon name="chevron-right" class="h-5 w-5" /></span>
                </button>

                <div class="ml-auto flex min-w-0 items-center gap-2 text-sm sm:gap-3">
                    <div class="ops-clock flex shrink-0 items-center gap-2 rounded-md border px-2.5 py-1.5" role="timer" aria-label="Hora local Lima">
                        <span class="ops-clock-meta hidden text-[10px] font-bold uppercase tracking-[0.12em] sm:inline">LIMA</span>
                        <time class="ops-clock-time text-xs font-semibold" data-ops-clock-time datetime="">--:--:--</time>
                        <span class="ops-clock-date hidden text-[10px] font-medium md:inline" data-ops-clock-date>--/--</span>
                    </div>
                </div>
            </div>
        </header>

        <main class="ops-main mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="ops-flash-success mb-6 border px-4 py-3 text-sm">{{ $statusMessage }}</div>
            @endif

            @if (session('error'))
                <div class="ops-flash-danger mb-6 border px-4 py-3 text-sm">{{ session('error') }}</div>
            @endif

            @yield('content')
        </main>

        <footer class="border-t border-slate-200 px-4 py-4 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-[1600px] flex-col justify-between gap-2 text-xs text-slate-500 sm:flex-row sm:items-center">
                <p>Sistema de apoyo operativo. No reemplaza criterio clínico ni técnico.</p>
                @can('compliance.view')
                    <a href="{{ route('compliance.ai-policy') }}" class="font-semibold text-sky-700 hover:text-sky-900">Politica de uso de IA</a>
                @endcan
            </div>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('ops-sidebar');
            const wrapper = document.getElementById('ops-main-wrapper');
            const overlay = document.querySelector('[data-sidebar-overlay]');
            const toggles = document.querySelectorAll('[data-sidebar-toggle]');
            const closeButton = document.querySelector('[data-sidebar-close]');
            const labels = document.querySelectorAll('[data-sidebar-label]');
            const links = document.querySelectorAll('[data-sidebar-link]');
            const sections = document.querySelectorAll('[data-sidebar-section]');
            const sectionTriggers = document.querySelectorAll('[data-sidebar-section] > summary');
            const sectionChevrons = document.querySelectorAll('[data-sidebar-chevron]');
            const expandedIcon = document.querySelector('[data-sidebar-expanded-icon]');
            const collapsedIcon = document.querySelector('[data-sidebar-collapsed-icon]');
            const clockTime = document.querySelector('[data-ops-clock-time]');
            const clockDate = document.querySelector('[data-ops-clock-date]');
            const desktopQuery = window.matchMedia('(min-width: 1024px)');
            let isCollapsed = false;
            let isMobileOpen = false;

            if (clockTime && clockDate) {
                const timeFormatter = new Intl.DateTimeFormat('es-PE', {
                    timeZone: 'America/Lima',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hourCycle: 'h23',
                });
                const dateFormatter = new Intl.DateTimeFormat('es-PE', {
                    timeZone: 'America/Lima',
                    day: '2-digit',
                    month: 'short',
                });

                const updateClock = () => {
                    const now = new Date();
                    const time = timeFormatter.format(now);
                    const date = dateFormatter.format(now).replace('.', '');

                    clockTime.textContent = time;
                    clockTime.dateTime = now.toISOString();
                    clockDate.textContent = date;
                    clockTime.closest('[role="timer"]')?.setAttribute('aria-label', `Hora local Lima: ${time}, ${date}`);
                };

                updateClock();
                window.setInterval(updateClock, 1000);
            }

            const syncSectionState = (section) => {
                const chevron = section.querySelector('[data-sidebar-chevron]');
                chevron?.classList.toggle('rotate-90', section.open);
                try {
                    window.localStorage.setItem(`ops-sidebar-section-${section.dataset.sidebarSection}`, String(section.open));
                } catch (error) {
                    // La navegacion funciona aunque el navegador bloquee localStorage.
                }
            };

            sections.forEach((section) => {
                const savedState = window.localStorage?.getItem(`ops-sidebar-section-${section.dataset.sidebarSection}`);
                if (!section.querySelector('.ops-nav-link--active') && savedState !== null) {
                    section.open = savedState === 'true';
                }

                section.addEventListener('toggle', () => {
                    if (section.open) {
                        sections.forEach((otherSection) => {
                            if (otherSection !== section) {
                                otherSection.open = false;
                            }
                        });
                    }
                    syncSectionState(section);
                });
                syncSectionState(section);
            });

            try {
                isCollapsed = window.localStorage.getItem('ops-sidebar-collapsed') === 'true';
            } catch (error) {
                isCollapsed = false;
            }

            const setCollapsed = (collapsed) => {
                isCollapsed = collapsed;
                sidebar.classList.toggle('lg:w-20', collapsed);
                sidebar.classList.toggle('lg:w-72', !collapsed);
                wrapper.classList.toggle('lg:pl-20', collapsed);
                wrapper.classList.toggle('lg:pl-72', !collapsed);
                labels.forEach((label) => label.classList.toggle('lg:hidden', collapsed));
                links.forEach((link) => {
                    link.classList.toggle('lg:justify-center', collapsed);
                    link.classList.toggle('lg:px-0', collapsed);
                });
                sectionTriggers.forEach((trigger) => {
                    trigger.classList.toggle('lg:justify-center', collapsed);
                    trigger.classList.toggle('lg:px-0', collapsed);
                });
                sectionChevrons.forEach((chevron) => chevron.classList.toggle('lg:hidden', collapsed));
                expandedIcon?.classList.toggle('hidden', collapsed);
                collapsedIcon?.classList.toggle('hidden', !collapsed);
                toggles.forEach((toggle) => {
                    if (toggle.matches('.lg\\:inline-flex')) {
                        toggle.setAttribute('aria-label', collapsed ? 'Expandir menu' : 'Contraer menu');
                        toggle.setAttribute('title', collapsed ? 'Expandir menu' : 'Contraer menu');
                        toggle.setAttribute('aria-pressed', String(collapsed));
                    }
                });

                try {
                    window.localStorage.setItem('ops-sidebar-collapsed', String(collapsed));
                } catch (error) {
                    // La navegacion funciona aunque el navegador bloquee localStorage.
                }
            };

            const setMobileOpen = (open) => {
                isMobileOpen = open;
                sidebar.classList.toggle('-translate-x-full', !open);
                sidebar.classList.toggle('translate-x-0', open);
                overlay.classList.toggle('hidden', !open);
                document.body.classList.toggle('overflow-hidden', open && !desktopQuery.matches);
                toggles.forEach((toggle) => {
                    if (toggle.matches('.lg\\:hidden')) {
                        toggle.setAttribute('aria-expanded', String(open));
                        toggle.setAttribute('aria-label', open ? 'Cerrar menu' : 'Abrir menu');
                        toggle.setAttribute('title', open ? 'Cerrar menu' : 'Abrir menu');
                    }
                });
            };

            setCollapsed(isCollapsed);
            setMobileOpen(false);

            toggles.forEach((toggle) => {
                toggle.addEventListener('click', () => {
                    if (desktopQuery.matches) {
                        setCollapsed(!isCollapsed);
                    } else {
                        setMobileOpen(!isMobileOpen);
                    }
                });
            });

            closeButton?.addEventListener('click', () => setMobileOpen(false));
            overlay?.addEventListener('click', () => setMobileOpen(false));
            links.forEach((link) => link.addEventListener('click', () => {
                if (!desktopQuery.matches) {
                    setMobileOpen(false);
                }
            }));
            window.addEventListener('resize', () => {
                if (desktopQuery.matches) {
                    setMobileOpen(false);
                }
            });

            document.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'b' && desktopQuery.matches) {
                    event.preventDefault();
                    setCollapsed(!isCollapsed);
                }
            });
        });
    </script>
</body>
</html>
