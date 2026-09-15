<section>
    <details class="group border border-rose-200 bg-rose-50/50" @if ($errors->userDeletion->isNotEmpty()) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 text-sm font-semibold text-rose-800 marker:hidden">
            <span>Eliminar mi cuenta</span>
            <span class="text-xs text-rose-600 group-open:hidden">Mostrar</span>
            <span class="hidden text-xs text-rose-600 group-open:inline">Ocultar</span>
        </summary>

        <div class="border-t border-rose-200 px-4 py-4">
            <p class="text-sm leading-6 text-rose-800">Esta accion elimina tu cuenta y sus datos de forma permanente. Ingresa tu password para confirmar.</p>

            <form method="post" action="{{ route('profile.destroy') }}" class="mt-4 space-y-4">
                @csrf
                @method('delete')

                <div>
                    <label for="delete_account_password" class="block text-sm font-medium text-rose-900">Password actual</label>
                    <input id="delete_account_password" name="password" type="password" class="mt-1.5 block w-full border-rose-300 bg-white text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500" placeholder="Ingresa tu password" required autocomplete="current-password">
                    <x-input-error class="mt-1.5 text-sm text-rose-700" :messages="$errors->userDeletion->get('password')" />
                </div>

                <button type="submit" class="inline-flex items-center justify-center bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2">
                    Confirmar eliminacion
                </button>
            </form>
        </div>
    </details>
</section>
