<x-guest-layout>
    <main class="ops-login min-h-screen lg:flex">
        <section class="ops-login-panel flex min-h-[280px] w-full flex-col justify-between px-6 py-8 text-white sm:px-10 lg:min-h-screen lg:w-[40%] lg:max-w-[560px] lg:px-12 lg:py-12">
            <div>
                <img src="{{ asset('images/ops-biomed-logo.png') }}" alt="OPS BIOMED MR8" class="h-16 w-16 object-contain sm:h-20 sm:w-20" />
                <p class="mt-8 text-xs font-semibold uppercase tracking-[0.18em] text-slate-200">OPS BIOMED MR8</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Torre de Control Quirúrgica</h1>
                <p class="mt-5 max-w-md text-sm leading-6 text-slate-200 sm:text-base">Gestión segura de solicitudes, inventario, trazabilidad y operaciones quirúrgicas.</p>
            </div>
            <ul class="mt-10 hidden max-w-md space-y-4 text-sm text-slate-200 lg:block">
                <li class="flex items-center gap-3"><span class="h-2 w-2 rounded-full bg-teal-400"></span>Disponibilidad de recursos.</li>
                <li class="flex items-center gap-3"><span class="h-2 w-2 rounded-full bg-teal-400"></span>Control de inventario.</li>
                <li class="flex items-center gap-3"><span class="h-2 w-2 rounded-full bg-teal-400"></span>Trazabilidad por lote y serie.</li>
                <li class="flex items-center gap-3"><span class="h-2 w-2 rounded-full bg-teal-400"></span>Evidencia y auditoría operativa.</li>
            </ul>
        </section>
        <section class="flex min-h-[calc(100vh-280px)] flex-1 items-center justify-center px-5 py-10 sm:px-8 lg:min-h-screen lg:px-12">
            <div class="w-full max-w-md">
                <div class="mb-7">
                    <p class="text-sm font-semibold text-[var(--ops-primary)]">Acceso seguro</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-[var(--ops-text)] sm:text-3xl">Recuperar contraseña</h2>
                    <p class="mt-3 text-sm leading-6 text-[var(--ops-text-muted)]">Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.</p>
                </div>
                <div class="ops-login-surface rounded-lg p-6 sm:p-8">
                    @if (session('status'))
                        <div class="mb-5 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
                    @endif
                    <form method="POST" action="{{ route('password.email') }}" data-password-reset-form>
                        @csrf
                        <div>
                            <label for="email" class="block text-sm font-medium text-[var(--ops-text)]">Correo electrónico</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="ops-login-input mt-2 block w-full px-3 py-3 text-sm shadow-sm" aria-describedby="email-error" @if ($errors->has('email')) aria-invalid="true" @endif>
                            @if ($errors->has('email'))
                                <p id="email-error" class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $errors->first('email') }}</p>
                            @endif
                        </div>
                        <button type="submit" data-password-reset-submit class="ops-login-submit mt-6 inline-flex min-h-11 w-full items-center justify-center px-4 py-3 text-sm font-semibold">
                            <span data-submit-label>Enviar enlace de recuperación</span>
                            <span data-submit-loading class="hidden items-center gap-2" aria-live="polite"><span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>Enviando...</span>
                        </button>
                    </form>
                </div>
                <p class="mt-6 text-center text-xs text-[var(--ops-text-muted)]">Acceso restringido a usuarios autorizados.</p>
            </div>
        </section>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-password-reset-form]');
            const button = document.querySelector('[data-password-reset-submit]');
            const label = document.querySelector('[data-submit-label]');
            const loading = document.querySelector('[data-submit-loading]');
            form?.addEventListener('submit', () => {
                if (!button) return;
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
                label?.classList.add('hidden');
                loading?.classList.remove('hidden');
                loading?.classList.add('inline-flex');
            });
        });
    </script>
</x-guest-layout>
