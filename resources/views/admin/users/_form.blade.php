@php
    $isEdit = isset($user) && $user;
    $formAction = $isEdit ? route('admin.users.update', $user) : route('admin.users.store');
    $selectedRole = old('role', $isEdit ? $user->roles->first()?->name : '');
@endphp

<form method="POST" action="{{ $formAction }}" class="space-y-6 border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
    @csrf
    @if ($isEdit)
        @method('PATCH')
    @endif

    @if ($errors->any())
        <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-5 sm:grid-cols-2">
        <label class="text-sm font-medium text-slate-700">Nombre <span class="text-rose-600">*</span>
            <input name="name" type="text" required maxlength="255" value="{{ old('name', $user->name ?? '') }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            @error('name')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
        </label>
        <label class="text-sm font-medium text-slate-700">Email <span class="text-rose-600">*</span>
            <input name="email" type="email" required maxlength="255" value="{{ old('email', $user->email ?? '') }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            @error('email')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
        </label>
        <label class="text-sm font-medium text-slate-700">Rol <span class="text-rose-600">*</span>
            <select name="role" required class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">Selecciona un rol</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}" @selected($selectedRole === $role->name)>{{ $role->name }}</option>
                @endforeach
            </select>
            @error('role')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
        </label>
        <label class="text-sm font-medium text-slate-700">Estado <span class="text-rose-600">*</span>
            @if ($isEdit)
                <input type="hidden" name="active" value="0">
            @endif
            <span class="mt-3 flex items-center gap-3 text-sm font-normal text-slate-700">
                <input name="active" type="checkbox" value="1" @checked(old('active', $isEdit ? $user->active : true)) class="border-slate-300 text-sky-700 focus:ring-sky-500">
                Usuario activo
            </span>
            @error('active')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
        </label>
        @if (! $isEdit)
            <label class="text-sm font-medium text-slate-700">Password temporal <span class="text-rose-600">*</span>
                <input name="password" type="password" required minlength="8" autocomplete="new-password" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                @error('password')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm font-medium text-slate-700">Confirmar password <span class="text-rose-600">*</span>
                <input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            </label>
        @endif
        <label class="text-sm font-medium text-slate-700">Cargo
            <select name="job_title" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">Sin cargo registrado</option>
                @foreach ($jobTitles as $value => $label)
                    <option value="{{ $value }}" @selected(old('job_title', $user->job_title ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('job_title')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
        </label>
        <label class="text-sm font-medium text-slate-700">Area
            <input name="area" type="text" maxlength="100" value="{{ old('area', $user->area ?? '') }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            @error('area')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
        </label>
        <label class="text-sm font-medium text-slate-700">Telefono
            <input name="phone" type="text" maxlength="50" value="{{ old('phone', $user->phone ?? '') }}" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            @error('phone')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
        </label>
        <label class="text-sm font-medium text-slate-700">Institucion
            <select name="institution_id" class="mt-2 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">Sin institucion asignada</option>
                @foreach ($institutions as $institution)
                    <option value="{{ $institution->id }}" @selected((string) old('institution_id', $user->institution_id ?? '') === (string) $institution->id)>{{ $institution->name }}</option>
                @endforeach
            </select>
            @error('institution_id')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
        </label>
    </div>

    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
        <a href="{{ $isEdit ? route('admin.users.show', $user) : route('admin.users.index') }}" class="inline-flex items-center justify-center border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
        <button type="submit" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-800">{{ $isEdit ? 'Guardar cambios' : 'Crear usuario' }}</button>
    </div>
</form>
