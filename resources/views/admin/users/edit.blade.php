@extends('layouts.ops')

@section('title', 'Editar '.$user->name.' | Administracion OPS BIOMED')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('admin.users.show', $user) }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al detalle</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Editar usuario</h1>
            <p class="mt-2 text-sm text-slate-500">Los cambios de rol y estado quedan registrados en auditoria.</p>
        </div>
        @include('admin.users._form', ['user' => $user])

        <section id="reset-password" class="border border-amber-200 bg-amber-50 p-5 shadow-sm sm:p-8">
            <h2 class="font-semibold text-amber-950">Restablecer password</h2>
            <p class="mt-1 text-sm text-amber-800">Define una nueva password temporal. El valor no se mostrara despues de guardar.</p>
            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="mt-5 grid gap-5 sm:grid-cols-2 sm:items-end">
                @csrf
                <label class="text-sm font-medium text-amber-950">Nueva password <span class="text-rose-700">*</span>
                    <input name="password" type="password" required minlength="8" autocomplete="new-password" class="mt-2 block w-full border-amber-300 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                </label>
                <label class="text-sm font-medium text-amber-950">Confirmar password <span class="text-rose-700">*</span>
                    <input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="mt-2 block w-full border-amber-300 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                </label>
                <div class="sm:col-span-2"><button type="submit" class="bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-800">Restablecer password</button></div>
            </form>
        </section>
    </div>
@endsection
