@extends('layouts.ops')

@section('title', 'Politica de uso de IA | OPS BIOMED MR8')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end">
            <div>
                <p class="ops-eyebrow text-sm font-semibold uppercase tracking-[0.14em]">Cumplimiento y uso responsable</p>
                <h1 class="ops-section-title mt-2 text-2xl font-semibold">Politica de uso de IA</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Marco interno para cualquier función futura de inteligencia artificial en la Torre de Control Quirurgica MR8.</p>
            </div>
            <a href="{{ route('dashboard.ops') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Volver al dashboard</a>
        </header>

        <section class="border border-sky-200 bg-sky-50 p-5 sm:p-6" aria-labelledby="ai-notice-title">
            <div class="flex items-start gap-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-sky-200 bg-white text-sky-700">
                    <x-nav.icon name="shield-check" class="h-5 w-5" />
                </span>
                <div>
                    <h2 id="ai-notice-title" class="font-semibold text-sky-900">Apoyo operativo, nunca decisión autónoma</h2>
                    <p class="mt-2 text-sm leading-6 text-sky-800">OPS BIOMED puede usar funciones asistidas por IA únicamente como apoyo operativo. Las decisiones clínicas, técnicas, comerciales y administrativas requieren validación humana.</p>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(280px,0.75fr)]">
            <div class="space-y-6">
                <section class="ops-card p-5 sm:p-6" aria-labelledby="scope-title">
                    <h2 id="scope-title" class="text-lg font-semibold text-slate-950">Alcance y límites</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">La IA, cuando exista, se limita a resumir información operativa autorizada, detectar inconsistencias no clínicas y proponer borradores o prioridades de trabajo. No es un dispositivo médico ni un sistema de diagnóstico, pronóstico, indicación, triaje o decisión clínica autónoma.</p>
                    <div class="mt-5 grid gap-4 lg:grid-cols-2">
                        <div class="border border-emerald-200 bg-emerald-50 p-4">
                            <h3 class="font-semibold text-emerald-900">Usos permitidos</h3>
                            <ul class="mt-3 space-y-2 text-sm leading-6 text-emerald-800">
                                <li>Resúmenes y borradores de apoyo operativo.</li>
                                <li>Priorización explicable de tareas de inventario y documentación.</li>
                                <li>Detección de inconsistencias en datos agregados o desidentificados.</li>
                                <li>Preparación de reportes para revisión humana.</li>
                            </ul>
                        </div>
                        <div class="border border-rose-200 bg-rose-50 p-4">
                            <h3 class="font-semibold text-rose-900">Usos prohibidos</h3>
                            <ul class="mt-3 space-y-2 text-sm leading-6 text-rose-800">
                                <li>Diagnosticar, pronosticar, indicar tratamiento o sustituir criterio clínico.</li>
                                <li>Decidir autónomamente sobre atención, priorización clínica o acceso a salud.</li>
                                <li>Cerrar casos, aprobar costo cero, liberar fallas o inventario, facturar o cobrar sin confirmación humana.</li>
                                <li>Enviar datos sensibles identificables a una IA externa sin autorización y controles aprobados.</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section class="ops-card p-5 sm:p-6" aria-labelledby="human-review-title">
                    <h2 id="human-review-title" class="text-lg font-semibold text-slate-950">Supervisión humana obligatoria</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div class="border border-slate-200 p-4">
                            <span class="ops-badge-info">1. Etiquetar</span>
                            <p class="mt-3 text-sm leading-6 text-slate-600">Toda salida futura se presentará como <strong class="font-semibold text-slate-900">Sugerencia asistida</strong>.</p>
                        </div>
                        <div class="border border-slate-200 p-4">
                            <span class="ops-badge-warning">2. Revisar</span>
                            <p class="mt-3 text-sm leading-6 text-slate-600">Una persona autorizada debe revisar contexto, datos y consecuencias antes de usar una sugerencia.</p>
                        </div>
                        <div class="border border-slate-200 p-4">
                            <span class="ops-badge-success">3. Decidir</span>
                            <p class="mt-3 text-sm leading-6 text-slate-600">La sugerencia debe poder ser aceptada, corregida o descartada; la decisión final siempre es humana.</p>
                        </div>
                    </div>
                </section>

                <section class="ops-card p-5 sm:p-6" aria-labelledby="privacy-title">
                    <h2 id="privacy-title" class="text-lg font-semibold text-slate-950">Datos, trazabilidad y seguridad</h2>
                    <div class="mt-4 space-y-4 text-sm leading-6 text-slate-600">
                        <p>Se aplican minimización, necesidad y control de acceso. No se deben remitir a proveedores externos nombres de pacientes, historias clínicas, diagnósticos, imágenes, identificadores personales u otros datos sensibles sin una base legal, autorización interna, evaluación de privacidad y controles contractuales y técnicos aplicables.</p>
                        <p>La estructura <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">ai_usage_logs</code> registra únicamente metadatos de uso, proveedor, categoría de datos y revisión humana. No se almacenan prompts ni respuestas con datos sensibles.</p>
                        <p>Las incidencias, desviaciones o posibles envíos indebidos de datos deben escalarse a Gerencia y al Administrador del sistema para contención, análisis y trazabilidad.</p>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="ops-card p-5" aria-labelledby="policy-status-title">
                    <h2 id="policy-status-title" class="font-semibold text-slate-950">Estado de la política</h2>
                    <dl class="mt-4 space-y-4 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Última actualización</dt>
                            <dd class="mt-1 font-semibold text-slate-900">11 de septiembre de 2026</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</dt>
                            <dd class="mt-1 font-semibold text-slate-900">Gerencia y Administrador de OPS BIOMED</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Clasificación preliminar</dt>
                            <dd class="mt-1 text-slate-700">Uso interno de apoyo operativo de riesgo aceptable, condicionado a que no procese datos sensibles identificables ni influya en decisiones clínicas.</dd>
                        </div>
                    </dl>
                </section>

                <section class="ops-card p-5" aria-labelledby="principles-title">
                    <h2 id="principles-title" class="font-semibold text-slate-950">Principios</h2>
                    <ul class="mt-4 space-y-3 text-sm text-slate-700">
                        @foreach (['Transparencia', 'Supervisión humana', 'Protección de datos', 'No discriminación', 'Seguridad', 'Trazabilidad', 'Rendición de cuentas'] as $principle)
                            <li class="flex items-center gap-2"><x-nav.icon name="check-circle" class="h-4 w-4 text-emerald-700" /> {{ $principle }}</li>
                        @endforeach
                    </ul>
                </section>

                <section class="border border-amber-200 bg-amber-50 p-5" aria-labelledby="review-title">
                    <h2 id="review-title" class="font-semibold text-amber-900">Reevaluación obligatoria</h2>
                    <p class="mt-2 text-sm leading-6 text-amber-800">Antes de incorporar una IA que trate información de salud, datos personales sensibles o que influya en una decisión clínica, técnica o de acceso, debe realizarse una evaluación legal, de privacidad, seguridad y riesgo específica.</p>
                </section>

                <section class="ops-card p-5" aria-labelledby="legal-title">
                    <h2 id="legal-title" class="font-semibold text-slate-950">Marco de referencia</h2>
                    <ul class="mt-3 space-y-2 text-sm leading-6">
                        <li><a class="font-semibold text-sky-700 hover:text-sky-900" href="https://www.gob.pe/institucion/congreso-de-la-republica/normas-legales/4565760-31814" target="_blank" rel="noreferrer">Ley N.° 31814</a></li>
                        <li><a class="font-semibold text-sky-700 hover:text-sky-900" href="https://busquedas.elperuano.pe/dispositivo/NL/2436426-1" target="_blank" rel="noreferrer">DS N.° 115-2025-PCM</a></li>
                        <li><a class="font-semibold text-sky-700 hover:text-sky-900" href="https://leyes.congreso.gob.pe/documentos/leyes/29733.pdf" target="_blank" rel="noreferrer">Ley N.° 29733</a></li>
                    </ul>
                </section>
            </aside>
        </div>
    </div>
@endsection
