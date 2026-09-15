@extends('layouts.ops')

@section('title', 'Torre de Control Quirurgica | OPS BIOMED MR8')

@section('content')
    @php
        $todayAgenda = $agendaCases->filter(fn ($case): bool => $case->scheduled_at?->isToday() ?? false);
        $nextAgenda = $agendaCases->reject(fn ($case): bool => $case->scheduled_at?->isToday() ?? false);
        $preoperativePendingCaseIds = $preoperativePendingCases->pluck('id')->all();
        $coverageRows = $riskSummaries->sortBy(fn (array $risk): int => match ($risk['risk']) {
            'red' => 0,
            'yellow' => 1,
            default => 2,
        })->take(8);
        $criticalMetrics = [
            [
                'label' => 'Cirugias de hoy',
                'value' => $todayCases,
                'classes' => 'ops-critical-card ops-critical-card-info',
                'href' => $canViewSchedule ? route('schedule.index') : route('cases.index'),
            ],
            [
                'label' => 'Proximas 48 horas',
                'value' => $upcoming48Cases,
                'classes' => 'ops-critical-card ops-critical-card-warning',
                'href' => $canViewSchedule ? route('schedule.index') : route('cases.index'),
            ],
            [
                'label' => 'Pendientes de cierre',
                'value' => $pendingClosureCases,
                'classes' => 'ops-critical-card ops-critical-card-danger',
                'href' => route('cases.index'),
            ],
        ];

        if ($canViewAlerts) {
            $criticalMetrics = array_merge([
                [
                    'label' => 'Alertas críticas',
                    'value' => $alertSummary['critical'],
                    'classes' => 'ops-critical-card ops-critical-card-danger',
                    'href' => route('alerts.index', ['priority' => 'critical']),
                ],
                [
                    'label' => 'Alertas vencidas',
                    'value' => $alertSummary['expired'],
                    'classes' => 'ops-critical-card ops-critical-card-warning',
                    'href' => route('alerts.index', ['status' => 'expired']),
                ],
            ], $criticalMetrics);
        }

        if ($canViewSchedule && $scheduleSummary !== null) {
            $criticalMetrics[] = [
                'label' => 'Conflictos criticos',
                'value' => $scheduleSummary['critical_conflicts'],
                'classes' => 'ops-critical-card ops-critical-card-danger',
                'href' => route('schedule.conflicts'),
            ];
            $criticalMetrics[] = [
                'label' => 'Sin instrumentista',
                'value' => $scheduleSummary['missing_instrumentist'],
                'classes' => 'ops-critical-card ops-critical-card-warning',
                'href' => route('schedule.index'),
            ];
            $criticalMetrics[] = [
                'label' => 'Casos sin reserva completa',
                'value' => $scheduleSummary['incomplete_reservations'],
                'classes' => 'ops-critical-card ops-critical-card-warning',
                'href' => route('schedule.index'),
            ];
        } elseif ($canViewInventory) {
            $criticalMetrics[] = [
                'label' => 'Reservas incompletas',
                'value' => $incompleteReservationCases->count(),
                'classes' => 'ops-critical-card ops-critical-card-warning',
                'href' => route('cases.index'),
            ];
        }

        if ($canPrepareCases) {
            $criticalMetrics[] = [
                'label' => 'Preoperatorios pendientes',
                'value' => $preoperativePendingCases->count(),
                'classes' => 'ops-critical-card ops-critical-card-warning',
                'href' => $preoperativePendingCases->isNotEmpty()
                    ? route('cases.preparation', $preoperativePendingCases->first())
                    : route('cases.index'),
            ];
        }

        if ($canViewFailures) {
            $criticalMetrics[] = [
                'label' => 'Fallas abiertas / bloqueadas',
                'value' => $openFailures,
                'classes' => 'ops-critical-card ops-critical-card-danger',
                'href' => route('failures.index'),
            ];
        }

        if ($canViewBilling) {
            $criticalMetrics[] = [
                'label' => 'Deuda vencida',
                'value' => $overdueDebtCases,
                'classes' => 'ops-critical-card ops-critical-card-danger',
                'href' => route('billing.index'),
            ];
        }

        if ($canViewDocuments) {
            $criticalMetrics[] = [
                'label' => 'Documentos pendientes de validar',
                'value' => $pendingDocumentsToValidate,
                'classes' => 'ops-critical-card ops-critical-card-warning',
                'href' => route('documents.index'),
            ];
        }

        if ($canViewReturns) {
            $criticalMetrics[] = [
                'label' => 'Devoluciones pendientes de inspeccion',
                'value' => $returnsPendingInspection,
                'classes' => 'ops-critical-card ops-critical-card-warning',
                'href' => route('returns.index'),
            ];
        }

        if ($canViewInventory) {
            $criticalMetrics[] = [
                'label' => 'Inconsistencias de inventario',
                'value' => $inventoryAlerts['import_inconsistencies_pending'] ?? 0,
                'classes' => 'ops-critical-card ops-critical-card-warning',
                'href' => route('catalog.imports.index'),
            ];
        }

        $recommendedActions = collect([
            $upcoming48Cases > 0 && $incompleteReservationCases->isNotEmpty()
                ? [
                    'priority' => 'Alta',
                    'label' => 'Completar reservas de cirugias proximas',
                    'detail' => $incompleteReservationCases->count().' caso(s) requieren cobertura.',
                    'href' => $canViewSchedule ? route('schedule.index') : route('cases.index'),
                    'class' => 'border-amber-200 bg-amber-50 text-amber-900',
                ]
                : null,
            $canViewFailures && $criticalFailures > 0
                ? [
                    'priority' => 'Critica',
                    'label' => 'Revisar fallas criticas',
                    'detail' => $criticalFailures.' falla(s) mantienen riesgo tecnico.',
                    'href' => route('failures.index'),
                    'class' => 'border-rose-200 bg-rose-50 text-rose-900',
                ]
                : null,
            $canViewReturns && $returnsPendingInspection > 0
                ? [
                    'priority' => 'Alta',
                    'label' => 'Inspeccionar devoluciones pendientes',
                    'detail' => $returnsPendingInspection.' devolucion(es) esperan decision.',
                    'href' => route('returns.index'),
                    'class' => 'border-amber-200 bg-amber-50 text-amber-900',
                ]
                : null,
            $canViewDocuments && $pendingDocumentsToValidate > 0
                ? [
                    'priority' => 'Media',
                    'label' => 'Validar documentos pendientes',
                    'detail' => $pendingDocumentsToValidate.' documento(s) requieren revision.',
                    'href' => route('documents.index'),
                    'class' => 'border-sky-200 bg-sky-50 text-sky-900',
                ]
                : null,
            $forecastSummary !== null && ($forecastSummary['items_critical'] ?? 0) > 0
                ? [
                    'priority' => 'Critica',
                    'label' => 'Gestionar compra urgente',
                    'detail' => $forecastSummary['items_critical'].' item(s) bajo minimo operativo.',
                    'href' => route('inventory.forecast', ['urgency' => 'critical']),
                    'class' => 'border-rose-200 bg-rose-50 text-rose-900',
                ]
                : null,
            $canViewBilling && $overdueDebtCases > 0
                ? [
                    'priority' => 'Alta',
                    'label' => 'Dar seguimiento a deuda vencida',
                    'detail' => $overdueDebtCases.' cuenta(s) tienen saldo vencido.',
                    'href' => route('billing.index'),
                    'class' => 'border-rose-200 bg-rose-50 text-rose-900',
                ]
                : null,
            $pendingClosureCases > 0
                ? [
                    'priority' => 'Alta',
                    'label' => 'Cerrar cirugias pendientes',
                    'detail' => $pendingClosureCases.' caso(s) esperan cierre operativo.',
                    'href' => route('cases.index'),
                    'class' => 'border-amber-200 bg-amber-50 text-amber-900',
                ]
                : null,
        ])->filter()->take(5);
    @endphp

    <div class="ops-dashboard space-y-8">
        <header class="flex flex-col gap-5 border-b border-slate-200 pb-6 xl:flex-row xl:items-end xl:justify-between">
            <div class="flex items-start gap-4">
                <div class="hidden h-14 w-14 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white p-2 shadow-sm sm:flex">
                    <img src="{{ asset('images/ops-biomed-logo.png') }}" alt="OPS BIOMED MR8" class="h-full w-full object-contain" />
                </div>
                <div>
                <p class="ops-eyebrow text-sm font-semibold uppercase tracking-[0.14em]">Linea Midas Rex MR8</p>
                <h1 class="ops-section-title mt-2 text-3xl font-semibold tracking-tight">Torre de Control Quirurgica</h1>
                <div class="mt-3 flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-5">
                    <time datetime="{{ $currentDateTime->toIso8601String() }}">{{ $currentDateTime->format('d/m/Y H:i') }}</time>
                    <span class="hidden h-1 w-1 bg-slate-300 sm:block" aria-hidden="true"></span>
                    <span>Usuario: <strong class="font-semibold text-slate-700">{{ auth()->user()->name }}</strong></span>
                    <span class="hidden h-1 w-1 bg-slate-300 sm:block" aria-hidden="true"></span>
                    <span>Rol: <strong class="font-semibold text-slate-700">{{ $currentUserRole ?: 'No registrado' }}</strong></span>
                </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2" aria-label="Acciones rapidas">
                <span class="inline-flex items-center gap-2 border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm font-semibold text-emerald-800" role="status">
                    <span class="h-2 w-2 rounded-full bg-emerald-600" aria-hidden="true"></span>
                    Sistema operativo
                </span>
                @can('cases.create')
                    <a href="{{ route('cases.create') }}" class="ops-button-primary inline-flex items-center gap-2 px-3.5 py-2.5 text-sm font-semibold shadow-sm">
                        <x-nav.icon name="clipboard" class="h-4 w-4" />
                        Nueva solicitud
                    </a>
                @endcan
                @can('catalog.import')
                    <a href="{{ route('catalog.imports.index') }}" class="ops-button-secondary inline-flex items-center gap-2 px-3.5 py-2.5 text-sm font-semibold">
                        <x-nav.icon name="upload" class="h-4 w-4" />
                        Importar catalogo
                    </a>
                @endcan
                @can('inventory.view')
                    <a href="{{ route('inventory.coverage') }}" class="ops-button-secondary inline-flex items-center gap-2 px-3.5 py-2.5 text-sm font-semibold">
                        <x-nav.icon name="shield-check" class="h-4 w-4" />
                        Ver cobertura
                    </a>
                @endcan
                @if ($forecastSummary !== null)
                    <a href="{{ route('inventory.forecast') }}" class="ops-button-secondary inline-flex items-center gap-2 px-3.5 py-2.5 text-sm font-semibold">
                        <x-nav.icon name="trend" class="h-4 w-4" />
                        Ver forecast
                    </a>
                @endif
                @can('reports.view')
                    <a href="{{ route('reports.index') }}" class="ops-button-secondary inline-flex items-center gap-2 px-3.5 py-2.5 text-sm font-semibold">
                        <x-nav.icon name="chart" class="h-4 w-4" />
                        Reportes ejecutivos
                    </a>
                @endcan
                @can('trace.scan')
                    <a href="{{ route('trace.index') }}" class="ops-button-secondary inline-flex items-center gap-2 px-3.5 py-2.5 text-sm font-semibold">
                        <x-nav.icon name="scan" class="h-4 w-4" />
                        Escanear
                    </a>
                @endcan
                @can('manual.view')
                    <a href="{{ route('manual.index') }}" class="ops-button-secondary inline-flex items-center gap-2 px-3.5 py-2.5 text-sm font-semibold">
                        <x-nav.icon name="book" class="h-4 w-4" />
                        Manual operativo
                    </a>
                @endcan
                @can('failures.create')
                    <a href="{{ route('failures.create') }}" class="ops-button-danger inline-flex items-center gap-2 px-3.5 py-2.5 text-sm font-semibold">
                        <x-nav.icon name="warning" class="h-4 w-4" />
                        Reportar falla
                    </a>
                @endcan
            </div>
        </header>

        <section aria-labelledby="critical-heading">
            <div class="mb-3 flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-700">Atencion inmediata</p>
                    <h2 id="critical-heading" class="mt-1 text-xl font-semibold text-slate-950">Prioridades de la operacion</h2>
                </div>
                <span class="hidden text-xs text-slate-500 sm:inline">Actualizado {{ $currentDateTime->format('H:i') }}</span>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($criticalMetrics as $metric)
                    <div class="flex min-h-32 flex-col border-l-4 border-y border-r p-4 {{ $metric['classes'] }}">
                        <p class="text-sm font-semibold">{{ $metric['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight">{{ number_format((int) $metric['value']) }}</p>
                        <a href="{{ $metric['href'] }}" class="mt-auto pt-3 text-xs font-semibold underline decoration-current underline-offset-2 hover:no-underline">Ver detalle</a>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($canViewAlerts)
            <section class="ops-card" aria-labelledby="alerts-heading">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Cumplimiento de tiempos</p>
                        <h2 id="alerts-heading" class="mt-1 text-xl font-semibold text-slate-950">Alertas SLA</h2>
                        <p class="mt-1 text-sm text-slate-500">Seguimiento de pendientes que requieren intervención humana.</p>
                    </div>
                    <a href="{{ route('alerts.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ver todas las alertas</a>
                </div>
                <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
                    <a href="{{ route('alerts.index') }}" class="border border-sky-200 bg-sky-50 p-4 hover:bg-sky-100">
                        <p class="text-sm text-sky-700">Alertas abiertas</p>
                        <p class="mt-2 text-2xl font-semibold text-sky-900">{{ number_format((int) $alertSummary['open']) }}</p>
                    </a>
                    <a href="{{ route('alerts.index', ['status' => 'expired']) }}" class="border border-orange-200 bg-orange-50 p-4 hover:bg-orange-100">
                        <p class="text-sm text-orange-700">Alertas vencidas</p>
                        <p class="mt-2 text-2xl font-semibold text-orange-900">{{ number_format((int) $alertSummary['expired']) }}</p>
                    </a>
                    <a href="{{ route('alerts.index', ['status' => 'open']) }}" class="border border-amber-200 bg-amber-50 p-4 hover:bg-amber-100">
                        <p class="text-sm text-amber-700">Próximas 24 horas</p>
                        <p class="mt-2 text-2xl font-semibold text-amber-900">{{ number_format((int) $alertSummary['due_soon']) }}</p>
                    </a>
                    <div class="border border-slate-200 p-4">
                        <p class="text-sm text-slate-500">Resolución promedio</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format((int) $alertSummary['average_resolution_minutes']) }} min</p>
                    </div>
                </div>
                @if ($alertSummary['by_responsible']->isNotEmpty())
                    <div class="border-t border-slate-200 px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Abiertas por responsable</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($alertSummary['by_responsible'] as $responsible)
                                <a href="{{ route('alerts.index', ['responsible_role' => $responsible->responsible]) }}" class="ops-badge-neutral">{{ $responsible->responsible }}: {{ $responsible->total }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endif

        <section class="ops-card" aria-labelledby="agenda-heading">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Operacion quirurgica</p>
                    <h2 id="agenda-heading" class="mt-1 text-xl font-semibold text-slate-950">Agenda quirurgica</h2>
                    <p class="mt-1 text-sm text-slate-500">Cirugias programadas y estado de cobertura de reserva.</p>
                </div>
                @if ($canViewSchedule)
                    <a href="{{ route('schedule.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Abrir agenda quirurgica</a>
                @else
                    <a href="{{ route('cases.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ver solicitudes</a>
                @endif
            </div>
            <div class="grid lg:grid-cols-2 lg:divide-x lg:divide-slate-200">
                @foreach ([['title' => 'Hoy', 'cases' => $todayAgenda], ['title' => 'Proximas 48 horas', 'cases' => $nextAgenda]] as $agendaGroup)
                    <div class="min-w-0 {{ $loop->first ? '' : 'border-t border-slate-200 lg:border-t-0' }}">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
                            <h3 class="text-sm font-semibold text-slate-900">{{ $agendaGroup['title'] }}</h3>
                            <span class="text-xs font-medium text-slate-500">{{ $agendaGroup['cases']->count() }} casos</span>
                        </div>
                        @if ($agendaGroup['cases']->isEmpty())
                            <p class="px-5 py-10 text-sm text-slate-500">No hay cirugias programadas en este periodo.</p>
                        @else
                            <div class="divide-y divide-slate-100">
                                @foreach ($agendaGroup['cases'] as $case)
                                    @php
                                        $isReservationIncomplete = $canViewInventory && $incompleteReservationCases->contains('id', $case->id);
                                        $hasActiveReservation = $canViewInventory && $case->relationLoaded('reservations') && $case->reservations->isNotEmpty();
                                        $hasPendingPreparation = $canPrepareCases && in_array($case->id, $preoperativePendingCaseIds, true);
                                        $reservationLabel = $isReservationIncomplete ? 'Reserva incompleta' : ($hasActiveReservation ? 'Reserva completa' : 'Sin reserva');
                                        $reservationClass = $isReservationIncomplete
                                            ? 'bg-rose-50 text-rose-700'
                                            : ($hasActiveReservation ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600');
                                    @endphp
                                    <a href="{{ route('cases.control', $case) }}" class="block px-5 py-4 hover:bg-slate-50" aria-label="Abrir control operativo de {{ $case->case_code }}">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="font-semibold text-sky-700">{{ $case->case_code }}</span>
                                                    <span class="inline-flex bg-sky-50 px-2 py-1 text-[11px] font-semibold text-sky-700">{{ $case->status->label() }}</span>
                                                    @if ($hasPendingPreparation)
                                                        <span class="inline-flex bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-800">Preoperatorio pendiente</span>
                                                    @endif
                                                </div>
                                                <p class="mt-2 truncate text-sm font-medium text-slate-900">{{ $case->institution?->name ?: 'Institucion no registrada' }}</p>
                                                <p class="mt-1 truncate text-xs text-slate-500">{{ $case->doctor?->name ?: 'Medico no registrado' }} - {{ $case->surgeryType?->name ?: 'Tipo no registrado' }}</p>
                                            </div>
                                            <div class="shrink-0 text-left sm:text-right">
                                                <time class="text-lg font-semibold text-slate-950" datetime="{{ $case->scheduled_at?->toIso8601String() }}">{{ $case->scheduled_at?->format('H:i') }}</time>
                                                @if ($agendaGroup['title'] !== 'Hoy')
                                                    <p class="text-xs text-slate-500">{{ $case->scheduled_at?->format('d/m/Y') }}</p>
                                                @endif
                                                @if ($canViewInventory)
                                                    <span class="mt-2 inline-flex px-2 py-1 text-[11px] font-semibold {{ $reservationClass }}">{{ $reservationLabel }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        @if ($canViewInventory)
            <section class="ops-card" aria-labelledby="inventory-heading">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Abastecimiento</p>
                        <h2 id="inventory-heading" class="mt-1 text-xl font-semibold text-slate-950">Inventario MR8</h2>
                        <p class="mt-1 text-sm text-slate-500">Riesgo por tipo de cirugia y alertas que afectan disponibilidad inmediata.</p>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm font-semibold">
                        <a href="{{ route('inventory.coverage') }}" class="text-sky-700 hover:text-sky-900">Ver cobertura</a>
                        @if ($forecastSummary !== null)
                            <a href="{{ route('inventory.forecast') }}" class="text-sky-700 hover:text-sky-900">Ver forecast</a>
                        @endif
                        <a href="{{ route('inventory.index') }}" class="text-slate-700 hover:text-slate-950">Ver inventario</a>
                    </div>
                </div>
                <div class="grid gap-3 border-b border-slate-200 p-5 sm:grid-cols-2 xl:grid-cols-4">
                    <a href="{{ route('inventory.coverage') }}" class="border border-rose-200 bg-rose-50 p-4 hover:bg-rose-100">
                        <p class="text-sm text-rose-700">Combinaciones en rojo</p>
                        <p class="mt-2 text-2xl font-semibold text-rose-900">{{ number_format((int) $coverageSummary['red']) }}</p>
                    </a>
                    <a href="{{ route('inventory.coverage') }}" class="border border-amber-200 bg-amber-50 p-4 hover:bg-amber-100">
                        <p class="text-sm text-amber-700">Combinaciones en amarillo</p>
                        <p class="mt-2 text-2xl font-semibold text-amber-900">{{ number_format((int) $coverageSummary['yellow']) }}</p>
                    </a>
                    <a href="{{ route('inventory.coverage') }}" class="border border-emerald-200 bg-emerald-50 p-4 hover:bg-emerald-100">
                        <p class="text-sm text-emerald-700">Tipos con cobertura completa</p>
                        <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ number_format((int) $coverageSummary['complete_types']) }}</p>
                    </a>
                    <a href="{{ route('inventory.coverage') }}" class="border border-slate-200 bg-slate-50 p-4 hover:bg-slate-100">
                        <p class="text-sm text-slate-600">Tipos en riesgo</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((int) $coverageSummary['at_risk_types']) }}</p>
                    </a>
                </div>
                <div class="grid lg:grid-cols-2 lg:divide-x lg:divide-slate-200">
                    <div class="p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-900">Cobertura critica por tipo de cirugia</h3>
                            <span class="text-xs text-slate-500">Minimo 3 / objetivo 5</span>
                        </div>
                        <div class="mt-3 divide-y divide-slate-100">
                            @forelse ($coverageSummary['types'] as $type)
                                <div class="flex items-center justify-between gap-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $type['name'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $type['green'] }} verdes - {{ $type['yellow'] }} amarillas - {{ $type['red'] }} rojas</p>
                                    </div>
                                    <span class="shrink-0 px-2.5 py-1 text-xs font-semibold {{ $type['complete'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $type['complete'] ? 'Completa' : 'En riesgo' }}</span>
                                </div>
                            @empty
                                <p class="py-8 text-sm text-slate-500">No hay reglas MR8 activas para mostrar cobertura.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="border-t border-slate-200 p-5 lg:border-t-0">
                        <h3 class="text-sm font-semibold text-slate-900">Alertas de inventario</h3>
                        <div class="mt-3 divide-y divide-slate-100">
                            @foreach ([
                                ['label' => 'Reservas activas', 'value' => $activeReservations, 'href' => route('cases.index'), 'class' => 'text-sky-700'],
                                ['label' => 'Productos criticos bajo minimo', 'value' => $coverageSummary['critical_under_minimum'], 'href' => route('inventory.coverage'), 'class' => 'text-rose-700'],
                                ['label' => 'Productos bajo objetivo', 'value' => $coverageSummary['under_target'], 'href' => route('inventory.coverage'), 'class' => 'text-amber-700'],
                                ['label' => 'Inconsistencias pendientes', 'value' => $inventoryAlerts['import_inconsistencies_pending'] ?? 0, 'href' => route('catalog.imports.index'), 'class' => 'text-orange-700'],
                                ['label' => 'Lotes vencidos', 'value' => $inventoryAlerts['expired'] ?? 0, 'href' => route('inventory.index'), 'class' => 'text-rose-700'],
                                ['label' => 'Lotes proximos a vencer', 'value' => $inventoryAlerts['expiring_soon'] ?? 0, 'href' => route('inventory.index'), 'class' => 'text-amber-700'],
                                ['label' => 'Lotes bloqueados / cuarentena', 'value' => ($inventoryAlerts['blocked'] ?? 0) + ($inventoryAlerts['quarantine'] ?? 0), 'href' => route('inventory.index'), 'class' => 'text-rose-700'],
                                ['label' => 'Falla preventiva', 'value' => $inventoryAlerts['preventive_failure'] ?? 0, 'href' => route('failures.index'), 'class' => 'text-orange-700'],
                                ['label' => 'Stock negativo', 'value' => $inventoryAlerts['negative'] ?? 0, 'href' => route('inventory.index'), 'class' => 'text-rose-700'],
                            ] as $alert)
                                <a href="{{ $alert['href'] }}" class="flex items-center justify-between gap-4 py-3 hover:bg-slate-50">
                                    <span class="text-sm text-slate-700">{{ $alert['label'] }}</span>
                                    <span class="text-sm font-semibold {{ $alert['class'] }}">{{ number_format((int) $alert['value']) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-200 p-5">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Cobertura por combinacion</h3>
                            <p class="mt-1 text-xs text-slate-500">Stock elegible inmediato neto por regla critica.</p>
                        </div>
                        <a href="{{ route('inventory.coverage') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ver matriz completa</a>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @forelse ($coverageRows as $risk)
                            <a href="{{ route('inventory.coverage') }}" class="border border-slate-200 p-3 hover:bg-slate-50">
                                <p class="truncate text-xs font-semibold text-slate-700">{{ $risk['surgery_type'] }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $risk['label'] }}</p>
                                <p class="mt-2 text-sm font-semibold {{ $risk['risk'] === 'red' ? 'text-rose-700' : ($risk['risk'] === 'yellow' ? 'text-amber-700' : 'text-emerald-700') }}">{{ number_format((int) $risk['available_net']) }} unidades netas - {{ $risk['risk_label'] }}</p>
                            </a>
                        @empty
                            <p class="border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 sm:col-span-2 xl:col-span-4">No hay combinaciones MR8 para mostrar.</p>
                        @endforelse
                    </div>
                </div>
                @if ($forecastSummary !== null)
                    <div class="border-t border-slate-200 p-5">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Forecast de reposicion MR8</h3>
                                <p class="mt-1 text-xs text-slate-500">Prioridad de compra con base en stock neto inmediato.</p>
                            </div>
                            <a href="{{ route('inventory.forecast') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Ver forecast</a>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <a href="{{ route('inventory.forecast', ['urgency' => 'critical']) }}" class="border border-rose-200 bg-rose-50 p-4 hover:bg-rose-100">
                                <p class="text-sm text-rose-700">Items para compra urgente</p>
                                <p class="mt-2 text-2xl font-semibold text-rose-900">{{ number_format((int) $forecastSummary['items_critical']) }}</p>
                            </a>
                            <a href="{{ route('inventory.forecast') }}" class="border border-amber-200 bg-amber-50 p-4 hover:bg-amber-100">
                                <p class="text-sm text-amber-700">Items bajo objetivo</p>
                                <p class="mt-2 text-2xl font-semibold text-amber-900">{{ number_format((int) $forecastSummary['under_target']) }}</p>
                            </a>
                            <a href="{{ route('inventory.forecast') }}" class="border border-slate-200 bg-slate-50 p-4 hover:bg-slate-100">
                                <p class="text-sm text-slate-600">Cirugias afectadas por falta de stock</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((int) $forecastSummary['affected_surgery_types']) }}</p>
                            </a>
                            <a href="{{ route('inventory.forecast') }}" class="border border-sky-200 bg-sky-50 p-4 hover:bg-sky-100">
                                <p class="text-sm text-sky-700">Respaldo YSAN disponible</p>
                                <p class="mt-2 text-2xl font-semibold text-sky-900">{{ number_format((int) $forecastSummary['ysan_support_available']) }}</p>
                            </a>
                        </div>
                    </div>
                @endif
            </section>
        @endif

        @if ($canViewBilling)
            <section class="ops-card" aria-labelledby="finance-heading">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Control administrativo</p>
                        <h2 id="finance-heading" class="mt-1 text-xl font-semibold text-slate-950">Finanzas y cobranza</h2>
                        <p class="mt-1 text-sm text-slate-500">Seguimiento de valorizacion, facturacion, ordenes de compra y deuda.</p>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm font-semibold">
                        <a href="{{ route('billing.index') }}" class="text-sky-700 hover:text-sky-900">Ver facturacion</a>
                        @if ($canApprovals)
                            <a href="{{ route('approvals.cost-zero.index') }}" class="text-slate-700 hover:text-slate-950">Ver aprobaciones</a>
                        @endif
                    </div>
                </div>
                <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-5">
                    <a href="{{ route('billing.index') }}" class="border border-slate-200 p-4 hover:bg-slate-50">
                        <p class="text-sm text-slate-500">Total valorizado pendiente</p>
                        <p class="mt-3 text-2xl font-semibold text-slate-950">S/ {{ number_format((float) $pendingValuedAmount, 2) }}</p>
                    </a>
                    <a href="{{ route('billing.index') }}" class="border border-slate-200 p-4 hover:bg-slate-50">
                        <p class="text-sm text-slate-500">Facturas pendientes</p>
                        <p class="mt-3 text-2xl font-semibold text-amber-700">{{ number_format((int) $pendingInvoiceCases) }}</p>
                    </a>
                    <a href="{{ route('billing.index') }}" class="border border-rose-200 bg-rose-50 p-4 hover:bg-rose-100">
                        <p class="text-sm text-rose-700">Deuda vencida</p>
                        <p class="mt-3 text-2xl font-semibold text-rose-900">S/ {{ number_format((float) $overdueDebtAmount, 2) }}</p>
                    </a>
                    <a href="{{ $canApprovals ? route('approvals.cost-zero.index') : route('billing.index') }}" class="border border-amber-200 bg-amber-50 p-4 hover:bg-amber-100">
                        <p class="text-sm text-amber-700">Costos cero pendientes</p>
                        <p class="mt-3 text-2xl font-semibold text-amber-900">{{ number_format((int) $zeroCostPendingCases) }}</p>
                    </a>
                    <a href="{{ route('billing.index') }}" class="border border-slate-200 p-4 hover:bg-slate-50">
                        <p class="text-sm text-slate-500">Solicitudes sin valorizacion</p>
                        <p class="mt-3 text-2xl font-semibold text-slate-950">{{ number_format((int) $closedPendingValuationCases) }}</p>
                    </a>
                </div>
            </section>
        @endif

        @if ($canViewFailures || $canViewReturns || $canViewDocuments)
            <section class="ops-card" aria-labelledby="quality-heading">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Trazabilidad documental y mantenimiento</p>
                        <h2 id="quality-heading" class="mt-1 text-xl font-semibold text-slate-950">Calidad tecnica</h2>
                        <p class="mt-1 text-sm text-slate-500">Incidencias y retornos que requieren revision o evidencia.</p>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm font-semibold">
                        @if ($canViewFailures)<a href="{{ route('failures.index') }}" class="text-sky-700 hover:text-sky-900">Ver fallas</a>@endif
                        @if ($canViewReturns)<a href="{{ route('returns.index') }}" class="text-sky-700 hover:text-sky-900">Ver devoluciones</a>@endif
                        @if ($canViewDocuments)<a href="{{ route('documents.index') }}" class="text-slate-700 hover:text-slate-950">Ver documentos</a>@endif
                    </div>
                </div>
                <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    @if ($canViewFailures)
                        <a href="{{ route('failures.index') }}" class="border border-rose-200 bg-rose-50 p-4 hover:bg-rose-100">
                            <p class="text-sm text-rose-700">Fallas abiertas</p>
                            <p class="mt-3 text-2xl font-semibold text-rose-900">{{ number_format((int) $openFailures) }}</p>
                        </a>
                    @endif
                    <a href="{{ route('cases.index') }}" class="border border-orange-200 bg-orange-50 p-4 hover:bg-orange-100">
                        <p class="text-sm text-orange-700">Diferencias de consumo</p>
                        <p class="mt-3 text-2xl font-semibold text-orange-900">{{ number_format((int) $consumptionDifferenceCases) }}</p>
                    </a>
                    @if ($canViewFailures)
                        <a href="{{ route('failures.index') }}" class="border border-rose-200 bg-rose-50 p-4 hover:bg-rose-100">
                            <p class="text-sm text-rose-700">Fallas criticas</p>
                            <p class="mt-3 text-2xl font-semibold text-rose-900">{{ number_format((int) $criticalFailures) }}</p>
                        </a>
                        <a href="{{ route('inventory.index') }}" class="border border-orange-200 bg-orange-50 p-4 hover:bg-orange-100">
                            <p class="text-sm text-orange-700">Lotes bloqueados por falla</p>
                            <p class="mt-3 text-2xl font-semibold text-orange-900">{{ number_format((int) $failureBlockedLots) }}</p>
                        </a>
                    @endif
                    @if ($canViewReturns)
                        <a href="{{ route('returns.index') }}" class="border border-amber-200 bg-amber-50 p-4 hover:bg-amber-100">
                            <p class="text-sm text-amber-700">Devoluciones en cuarentena</p>
                            <p class="mt-3 text-2xl font-semibold text-amber-900">{{ number_format((int) $returnsQuarantine) }}</p>
                        </a>
                        <a href="{{ route('returns.index') }}" class="border border-rose-200 bg-rose-50 p-4 hover:bg-rose-100">
                            <p class="text-sm text-rose-700">Devoluciones con falla</p>
                            <p class="mt-3 text-2xl font-semibold text-rose-900">{{ number_format((int) $returnsWithFailure) }}</p>
                        </a>
                    @endif
                    @if ($canViewDocuments)
                        <a href="{{ route('documents.index') }}" class="border border-orange-200 bg-orange-50 p-4 hover:bg-orange-100">
                            <p class="text-sm text-orange-700">Documentos observados</p>
                            <p class="mt-3 text-2xl font-semibold text-orange-900">{{ number_format((int) $observedDocuments) }}</p>
                        </a>
                    @endif
                    @if ($canViewFailures)
                        <div class="border border-slate-200 p-4 sm:col-span-2 lg:col-span-3">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-slate-900">Fallas por tipo</h3>
                                <a href="{{ route('failures.index') }}" class="text-xs font-semibold text-sky-700 hover:text-sky-900">Ver fallas</a>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse ($failureTypeSummary as $failureType)
                                    <span class="bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700">{{ ucfirst(str_replace('_', ' ', $failureType->failure_type)) }}: {{ $failureType->total }}</span>
                                @empty
                                    <span class="text-sm text-slate-500">No hay fallas reportadas.</span>
                                @endforelse
                            </div>
                        </div>
                    @endif
                    @if ($canViewFailures && $criticalFailures === 0 && $failureBlockedLots === 0 && (! $canViewReturns || ($returnsQuarantine === 0 && $returnsWithFailure === 0)) && (! $canViewDocuments || $observedDocuments === 0))
                        <p class="border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">No hay incidencias tecnicas abiertas que requieran accion.</p>
                    @endif
                </div>
            </section>
        @endif

        @if ($canViewCommercial)
            <section class="ops-card" aria-labelledby="commercial-heading">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Relacion comercial</p>
                        <h2 id="commercial-heading" class="mt-1 text-xl font-semibold text-slate-950">Actividad comercial</h2>
                        <p class="mt-1 text-sm text-slate-500">Senales del mes para seguimiento de cuentas y medicos.</p>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm font-semibold">
                        <a href="{{ route('reports.index') }}" class="text-slate-700 hover:text-slate-950">Reportes ejecutivos</a>
                        <a href="{{ route('reports.commercial') }}" class="text-sky-700 hover:text-sky-900">Ver reporte comercial</a>
                    </div>
                </div>
                <div class="grid gap-3 p-5 sm:grid-cols-2">
                    <div class="border border-slate-200 p-4">
                        <p class="text-sm text-slate-500">Cirugias del mes</p>
                        <p class="mt-3 text-2xl font-semibold text-slate-950">{{ number_format((int) $surgeriesThisMonth) }}</p>
                    </div>
                    <div class="border border-slate-200 p-4">
                        <p class="text-sm text-slate-500">Consumo valorizado del mes</p>
                        <p class="mt-3 text-2xl font-semibold text-slate-950">S/ {{ number_format((float) $monthlyConsumptionValue, 2) }}</p>
                    </div>
                </div>
                <div class="grid gap-6 border-t border-slate-200 p-5 lg:grid-cols-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Top instituciones</h3>
                        <div class="mt-3 divide-y divide-slate-100">
                            @forelse ($topInstitutions as $institution)
                                <div class="flex items-center justify-between gap-3 py-3">
                                    <span class="truncate text-sm text-slate-700">{{ $institution->institution?->name ?: 'Institucion no registrada' }}</span>
                                    <span class="shrink-0 text-sm font-semibold text-slate-900">{{ number_format((int) $institution->total) }}</span>
                                </div>
                            @empty
                                <p class="py-3 text-sm text-slate-500">No hay instituciones con cirugias registradas este mes.</p>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Top medicos</h3>
                        <div class="mt-3 divide-y divide-slate-100">
                            @forelse ($topDoctors as $doctor)
                                <div class="flex items-center justify-between gap-3 py-3">
                                    <span class="truncate text-sm text-slate-700">{{ $doctor->doctor?->name ?: 'Medico no registrado' }}</span>
                                    <span class="shrink-0 text-sm font-semibold text-slate-900">{{ number_format((int) $doctor->total) }}</span>
                                </div>
                            @empty
                                <p class="py-3 text-sm text-slate-500">No hay medicos con cirugias registradas este mes.</p>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Oportunidades comerciales</h3>
                        <p class="mt-3 border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">No hay una fuente de oportunidades comerciales configurada. El seguimiento detallado esta disponible en el reporte comercial.</p>
                    </div>
                </div>
            </section>
        @endif

        <section class="ops-card" aria-labelledby="recommended-actions-heading">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Seguimiento operativo</p>
                    <h2 id="recommended-actions-heading" class="mt-1 text-xl font-semibold text-slate-950">Siguiente accion recomendada</h2>
                    <p class="mt-1 text-sm text-slate-500">Prioridades calculadas desde el estado actual de la operacion. Requieren validacion humana.</p>
                </div>
                <span class="text-xs font-medium text-slate-500">Maximo 5 acciones</span>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($recommendedActions as $action)
                    <a href="{{ $action['href'] }}" class="border p-4 transition-colors hover:bg-white {{ $action['class'] }}">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[11px] font-bold uppercase tracking-[0.12em]">{{ $action['priority'] }}</span>
                            <span aria-hidden="true">→</span>
                        </div>
                        <p class="mt-3 text-sm font-semibold">{{ $action['label'] }}</p>
                        <p class="mt-1 text-xs leading-5 opacity-80">{{ $action['detail'] }}</p>
                    </a>
                @empty
                    <div class="border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 sm:col-span-2 lg:col-span-3">
                        No hay acciones urgentes identificadas. La operacion se encuentra bajo control.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
