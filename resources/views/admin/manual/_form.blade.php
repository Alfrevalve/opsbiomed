@php
    $article = $article ?? null;
    $fieldValue = function (string $key, mixed $default = null): string {
        $value = old($key, $default);

        return is_array($value) ? implode("\n", $value) : (string) ($value ?? '');
    };
    $selectedRoles = (array) old('roles', $article?->roles_json ?? []);
    $selectedStatus = old('status', $article?->active ? 'published' : 'disabled');
@endphp

@if ($errors->any())
    <div class="border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <p class="font-semibold">Revisa los campos marcados.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="border-b border-slate-200 pb-6">
    <h2 class="text-base font-semibold text-slate-900">Identificacion y publicacion</h2>
    <p class="mt-1 text-sm text-slate-500">No incluyas nombres, diagnosticos ni datos reales de pacientes.</p>
    <div class="mt-5 grid gap-5 md:grid-cols-2">
        <div class="md:col-span-2">
            <label for="title" class="block text-sm font-semibold text-slate-700">Titulo *</label>
            <input id="title" name="title" value="{{ old('title', $article?->title) }}" required class="mt-1.5 block w-full border-slate-300" maxlength="180">
            @error('title')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="slug" class="block text-sm font-semibold text-slate-700">Slug *</label>
            <input id="slug" name="slug" value="{{ old('slug', $article?->slug) }}" required class="mt-1.5 block w-full border-slate-300" maxlength="180">
            <p class="mt-1 text-xs text-slate-500">Usa letras, numeros y guiones. Se normaliza al guardar.</p>
            @error('slug')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="category_id" class="block text-sm font-semibold text-slate-700">Categoria *</label>
            <select id="category_id" name="category_id" required class="mt-1.5 block w-full border-slate-300 bg-white">
                <option value="">Selecciona una categoria</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $article?->category_id) === (string) $category->id)>{{ $category->name }}{{ $category->active ? '' : ' (inactiva)' }}</option>
                @endforeach
            </select>
            @error('category_id')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="level" class="block text-sm font-semibold text-slate-700">Nivel *</label>
            <select id="level" name="level" required class="mt-1.5 block w-full border-slate-300 bg-white">
                @foreach ($levels as $value => $label)<option value="{{ $value }}" @selected(old('level', $article?->level) === $value)>{{ $label }}</option>@endforeach
            </select>
            @error('level')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="version" class="block text-sm font-semibold text-slate-700">Version *</label>
            <input id="version" name="version" value="{{ old('version', $article?->version ?: '1.0') }}" required class="mt-1.5 block w-full border-slate-300" maxlength="30">
            @error('version')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="sort_order" class="block text-sm font-semibold text-slate-700">Orden de visualizacion *</label>
            <input id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $article?->sort_order ?? 0) }}" required class="mt-1.5 block w-full border-slate-300">
            @error('sort_order')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="status" class="block text-sm font-semibold text-slate-700">Estado *</label>
            <select id="status" name="status" required class="mt-1.5 block w-full border-slate-300 bg-white">
                @foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>@endforeach
            </select>
            @error('status')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

<div class="border-b border-slate-200 pb-6">
    <h2 class="text-base font-semibold text-slate-900">Contenido operativo</h2>
    <div class="mt-5 space-y-5">
        <div><label for="summary" class="block text-sm font-semibold text-slate-700">Resumen *</label><textarea id="summary" name="summary" rows="3" required class="mt-1.5 block w-full border-slate-300">{{ old('summary', $article?->summary) }}</textarea>@error('summary')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
        <div><label for="body" class="block text-sm font-semibold text-slate-700">Contenido en español</label><textarea id="body" name="body" rows="8" class="mt-1.5 block w-full border-slate-300">{{ old('body', $article?->body) }}</textarea>@error('body')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
        <div class="grid gap-5 md:grid-cols-2">
            <div><label for="steps" class="block text-sm font-semibold text-slate-700">Pasos numerados</label><textarea id="steps" name="steps" rows="7" class="mt-1.5 block w-full border-slate-300" placeholder="Un paso por linea">{{ $fieldValue('steps', $article?->steps_json) }}</textarea><p class="mt-1 text-xs text-slate-500">Escribe un paso por linea; el manual los numerara.</p></div>
            <div><label for="prerequisites" class="block text-sm font-semibold text-slate-700">Requisitos previos</label><textarea id="prerequisites" name="prerequisites" rows="7" class="mt-1.5 block w-full border-slate-300" placeholder="Un requisito por linea">{{ $fieldValue('prerequisites', $article?->prerequisites_json) }}</textarea></div>
            <div><label for="required_fields" class="block text-sm font-semibold text-slate-700">Campos o datos requeridos</label><textarea id="required_fields" name="required_fields" rows="5" class="mt-1.5 block w-full border-slate-300" placeholder="Un campo por linea">{{ $fieldValue('required_fields', $article?->required_fields_json) }}</textarea></div>
            <div><label for="common_errors" class="block text-sm font-semibold text-slate-700">Errores frecuentes</label><textarea id="common_errors" name="common_errors" rows="5" class="mt-1.5 block w-full border-slate-300" placeholder="Un error por linea">{{ $fieldValue('common_errors', $article?->common_errors_json) }}</textarea></div>
        </div>
        <div class="grid gap-5 md:grid-cols-2">
            <div><label for="expected_result" class="block text-sm font-semibold text-slate-700">Resultado esperado</label><textarea id="expected_result" name="expected_result" rows="4" class="mt-1.5 block w-full border-slate-300">{{ old('expected_result', $article?->expected_result) }}</textarea></div>
            <div><label for="blocked_action" class="block text-sm font-semibold text-slate-700">Accion bloqueada o advertencia</label><textarea id="blocked_action" name="blocked_action" rows="4" class="mt-1.5 block w-full border-slate-300">{{ old('blocked_action', $article?->blocked_action) }}</textarea></div>
        </div>
    </div>
</div>

<div class="border-b border-slate-200 pb-6">
    <h2 class="text-base font-semibold text-slate-900">Clasificacion y acceso</h2>
    <div class="mt-5 grid gap-5 md:grid-cols-2">
        <div class="md:col-span-2"><span class="block text-sm font-semibold text-slate-700">Roles autorizados</span><div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach ($roles as $role)<label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="roles[]" value="{{ $role }}" @checked(in_array($role, $selectedRoles, true)) class="border-slate-300 text-sky-700">{{ $role }}</label>@endforeach</div><p class="mt-2 text-xs text-slate-500">Sin roles seleccionados: visible para todos los usuarios autorizados por el manual.</p></div>
        <div><label for="module" class="block text-sm font-semibold text-slate-700">Modulo relacionado</label><input id="module" name="module" value="{{ old('module', $article?->module) }}" class="mt-1.5 block w-full border-slate-300" maxlength="120"></div>
        <div><label for="route_name" class="block text-sm font-semibold text-slate-700">Ruta relacionada</label><input id="route_name" name="route_name" value="{{ old('route_name', $article?->route_name) }}" class="mt-1.5 block w-full border-slate-300" maxlength="160" placeholder="cases.create"></div>
        <div><label for="permission" class="block text-sm font-semibold text-slate-700">Permiso relacionado</label><input id="permission" name="permission" value="{{ old('permission', $article?->permission) }}" class="mt-1.5 block w-full border-slate-300" maxlength="120"></div>
        <div><label for="escalation_role" class="block text-sm font-semibold text-slate-700">Responsable de escalamiento</label><input id="escalation_role" name="escalation_role" value="{{ old('escalation_role', $article?->escalation_role) }}" class="mt-1.5 block w-full border-slate-300" maxlength="120"></div>
        <div><label for="estimated_minutes" class="block text-sm font-semibold text-slate-700">Tiempo estimado (minutos) *</label><input id="estimated_minutes" type="number" min="1" max="600" name="estimated_minutes" value="{{ old('estimated_minutes', $article?->estimated_minutes ?? 10) }}" required class="mt-1.5 block w-full border-slate-300"></div>
        <div><label for="keywords" class="block text-sm font-semibold text-slate-700">Palabras clave</label><textarea id="keywords" name="keywords" rows="3" class="mt-1.5 block w-full border-slate-300" placeholder="Una palabra o frase por linea">{{ $fieldValue('keywords', $article?->keywords_json) }}</textarea></div>
    </div>
</div>

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <a href="{{ route('admin.manual.index') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Cancelar</a>
    <button type="submit" class="ops-button-primary inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold">Guardar tutorial</button>
</div>
