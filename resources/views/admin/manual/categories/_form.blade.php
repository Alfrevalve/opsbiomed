@php($category = $category ?? null)
@if ($errors->any())
    <div class="border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">Revisa los campos marcados antes de guardar.</div>
@endif
<div>
    <label for="name" class="block text-sm font-semibold text-slate-700">Nombre *</label>
    <input id="name" name="name" value="{{ old('name', $category?->name) }}" required maxlength="150" class="mt-1.5 block w-full border-slate-300">
    @error('name')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
</div>
<div>
    <label for="slug" class="block text-sm font-semibold text-slate-700">Slug *</label>
    <input id="slug" name="slug" value="{{ old('slug', $category?->slug) }}" required maxlength="150" class="mt-1.5 block w-full border-slate-300">
    @error('slug')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
</div>
<div>
    <label for="description" class="block text-sm font-semibold text-slate-700">Descripcion</label>
    <textarea id="description" name="description" rows="4" maxlength="1000" class="mt-1.5 block w-full border-slate-300">{{ old('description', $category?->description) }}</textarea>
    @error('description')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
</div>
<div class="grid gap-5 sm:grid-cols-2">
    <div><label for="sort_order" class="block text-sm font-semibold text-slate-700">Orden *</label><input id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $category?->sort_order ?? 0) }}" required class="mt-1.5 block w-full border-slate-300">@error('sort_order')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
    <div><span class="block text-sm font-semibold text-slate-700">Estado</span><label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-700"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $category?->active ?? true)) class="border-slate-300 text-sky-700"> Categoria activa</label></div>
</div>
<div class="flex flex-col gap-3 sm:flex-row sm:justify-between"><a href="{{ route('admin.manual.index') }}" class="ops-button-secondary inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold">Cancelar</a><button type="submit" class="ops-button-primary inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold">Guardar categoria</button></div>
