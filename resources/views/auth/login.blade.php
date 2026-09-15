<x-guest-layout>
    @php
        $translateLoginError = static fn (string $message): string => match ($message) {
            'These credentials do not match our records.' => 'Las credenciales no coinciden con nuestros registros.',
            'The email field is required.' => 'El correo electrónico es obligatorio.',
            'The email field must be a valid email address.' => 'Ingresa un correo electrónico válido.',
            'The password field is required.' => 'La contraseña es obligatoria.',
            default => $message,
        };
    @endphp

    <main class="ops-login min-h-screen lg:flex">
        <section class="ops-login-panel flex min-h-[280px] flex-col justify-between px-6 py-8 text-white sm:px-10 sm:py-10 lg:min-h-screen lg:w-[40%] lg:max-w-[560px] lg:px-14 lg:py-16" aria-labelledby="institution-title">
            <div>
                <a href="{{ route('home') }}" class="inline-flex rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white" aria-label="OPS BIOMED MR8">
                    <img src="{{ asset('images/ops-biomed-logo.png') }}" alt="OPS BIOMED MR8" class="h-16 w-16 object-contain sm:h-20 sm:w-20" />
                </a>

                <div class="mt-8 max-w-md">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-100">Plataforma operativa</p>
                    <h1 id="institution-title" class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">OPS BIOMED MR8</h1>
                    <p class="mt-3 text-lg font-medium text-cyan-50">Torre de Control Quirúrgica</p>
                    <p class="mt-5 max-w-sm text-sm leading-7 text-slate-200">
                        Gestión segura de solicitudes, inventario, trazabilidad y operaciones quirúrgicas.
                    </p>
                </div>

                <ul class="mt-8 grid gap-3 text-sm text-slate-200 sm:grid-cols-2 lg:grid-cols-1" aria-label="Capacidades de la plataforma">
                    @foreach (['Disponibilidad de recursos', 'Control de inventario', 'Trazabilidad por lote y serie', 'Evidencia y auditoría operativa'] as $capability)
                        <li class="flex items-center gap-3">
                            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-teal-300/40 bg-teal-500/10 text-teal-200" aria-hidden="true">✓</span>
                            <span>{{ $capability }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="mt-10 text-xs leading-5 text-slate-300">Operación MR8 con control, evidencia y responsabilidad humana.</p>
        </section>

        <section class="flex flex-1 items-center justify-center bg-[var(--ops-bg)] px-5 py-10 sm:px-8 lg:px-12 lg:py-16" aria-labelledby="login-title">
            <div class="w-full max-w-md">
                <div class="mb-8">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--ops-primary)]">Acceso seguro</p>
                    <h2 id="login-title" class="mt-3 text-3xl font-semibold tracking-tight text-[var(--ops-text)]">Iniciar sesión</h2>
                    <p class="mt-3 text-sm leading-6 text-[var(--ops-text-muted)]">Accede a la plataforma operativa OPS BIOMED MR8.</p>
                </div>

                @if (session('status'))
                    <div class="mb-5 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="ops-login-surface rounded-lg p-5 sm:p-7">
                    <form method="POST" action="{{ route('login') }}" data-login-form>
                        @csrf

                        <div>
                            <label for="email" class="block text-sm font-semibold text-[var(--ops-text)]">Correo electrónico</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="email"
                                @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif
                                class="ops-login-input mt-2 block min-h-12 w-full px-3.5 py-3 text-sm"
                            />
                            @if ($errors->has('email'))
                                <p id="email-error" class="mt-2 text-sm font-medium text-[var(--ops-danger)]" role="alert">
                                    {{ $translateLoginError($errors->first('email')) }}
                                </p>
                            @endif
                        </div>

                        <div class="mt-5">
                            <div class="flex items-center justify-between gap-3">
                                <label for="password" class="block text-sm font-semibold text-[var(--ops-text)]">Contraseña</label>
                                @if (Route::has('password.request'))
                                    <a class="ops-login-link text-xs font-semibold" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                                @endif
                            </div>
                            <div class="relative mt-2">
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    required
                                    autocomplete="current-password"
                                    @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif
                                    class="ops-login-input block min-h-12 w-full px-3.5 py-3 pr-20 text-sm"
                                />
                                <button type="button" class="absolute inset-y-1 right-1 inline-flex min-w-16 items-center justify-center rounded-md px-2 text-xs font-semibold text-[var(--ops-primary)] hover:bg-[var(--ops-bg-soft)] focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--ops-primary)]" data-password-toggle aria-controls="password" aria-pressed="false">
                                    Mostrar
                                </button>
                            </div>
                            @if ($errors->has('password'))
                                <p id="password-error" class="mt-2 text-sm font-medium text-[var(--ops-danger)]" role="alert">
                                    {{ $translateLoginError($errors->first('password')) }}
                                </p>
                            @endif
                        </div>

                        <label for="remember" class="mt-5 inline-flex min-h-11 cursor-pointer items-center gap-3 text-sm text-[var(--ops-text-muted)]">
                            <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember')) class="h-4 w-4 rounded border-[var(--ops-border)] text-[var(--ops-primary)] focus:ring-[var(--ops-primary)]" />
                            <span>Recordarme en este equipo</span>
                        </label>

                        <button type="submit" class="ops-login-submit mt-6 inline-flex min-h-12 w-full items-center justify-center px-4 py-3 text-sm font-semibold" data-login-submit>
                            <span data-submit-label>Ingresar</span>
                            <span class="hidden items-center gap-2" data-submit-loading aria-live="polite">
                                <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                                Ingresando...
                            </span>
                        </button>
                    </form>
                </div>

                <p class="mt-6 text-center text-xs font-medium text-[var(--ops-text-muted)]">Acceso restringido a usuarios autorizados.</p>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const password = document.getElementById('password');
            const passwordToggle = document.querySelector('[data-password-toggle]');
            const loginForm = document.querySelector('[data-login-form]');
            const loginSubmit = document.querySelector('[data-login-submit]');
            const submitLabel = document.querySelector('[data-submit-label]');
            const submitLoading = document.querySelector('[data-submit-loading]');

            passwordToggle?.addEventListener('click', () => {
                const isVisible = password.type === 'text';
                password.type = isVisible ? 'password' : 'text';
                passwordToggle.textContent = isVisible ? 'Mostrar' : 'Ocultar';
                passwordToggle.setAttribute('aria-pressed', String(!isVisible));
            });

            loginForm?.addEventListener('submit', () => {
                loginSubmit.disabled = true;
                loginSubmit.setAttribute('aria-disabled', 'true');
                submitLabel.classList.add('hidden');
                submitLoading.classList.remove('hidden');
                submitLoading.classList.add('inline-flex');
            });
        });
    </script>
</x-guest-layout>
