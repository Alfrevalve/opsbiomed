<section>
    <form method="post" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-sm font-medium text-slate-700">Password actual</label>
            <input id="update_password_current_password" name="current_password" type="password" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500" autocomplete="current-password">
            <x-input-error class="mt-1.5 text-sm text-rose-700" :messages="$errors->updatePassword->get('current_password')" />
        </div>

        <div>
            <label for="update_password_password" class="block text-sm font-medium text-slate-700">Nuevo password</label>
            <input id="update_password_password" name="password" type="password" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500" autocomplete="new-password">
            <x-input-error class="mt-1.5 text-sm text-rose-700" :messages="$errors->updatePassword->get('password')" />
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block text-sm font-medium text-slate-700">Confirmar password</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500" autocomplete="new-password">
            <x-input-error class="mt-1.5 text-sm text-rose-700" :messages="$errors->updatePassword->get('password_confirmation')" />
        </div>

        <div class="border-t border-slate-200 pt-5">
            <button type="submit" class="inline-flex items-center justify-center bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                Actualizar password
            </button>
        </div>
    </form>
</section>
