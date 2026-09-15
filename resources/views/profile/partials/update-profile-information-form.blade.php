<section>
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-5">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Nombre</label>
            <input id="name" name="name" type="text" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            <x-input-error class="mt-1.5 text-sm text-rose-700" :messages="$errors->get('name')" />
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input id="email" name="email" type="email" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500" value="{{ old('email', $user->email) }}" required autocomplete="username">
            <x-input-error class="mt-1.5 text-sm text-rose-700" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <p>Tu direccion de email no esta verificada.</p>
                    <button form="send-verification" class="mt-2 font-semibold text-amber-900 underline decoration-amber-400 underline-offset-2 hover:text-amber-950">
                        Reenviar email de verificacion
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-emerald-700">Se envio un nuevo enlace de verificacion.</p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center">
            <button type="submit" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                Guardar cambios
            </button>
            <p class="text-xs text-slate-500">El rol y el estado se gestionan desde Administracion.</p>
        </div>
    </form>
</section>
