@extends('layouts.ops')

@section('title', 'Editar '.$case->case_code.' | OPS BIOMED MR8')

@section('content')
    @php
        $canEdit = fn (string $field): bool => in_array($field, $editableFields, true);
        $disabledClass = 'disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500';
    @endphp

    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <a href="{{ route('cases.show', $case) }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al detalle</a>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Editar solicitud</h1>
                <span class="inline-flex bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">{{ $case->case_code }}</span>
            </div>
            <p class="mt-2 text-sm text-slate-500">Estado actual: {{ $case->status->label() }}. El codigo interno no puede modificarse.</p>
        </div>

        @if ($case->status->value === 'programada')
            <div class="border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">En estado programada solo puedes cambiar fecha y hora, material solicitado y observaciones.</div>
        @elseif (in_array($case->status->value, ['reservado', 'reservada'], true))
            <div class="border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">En estado reservada solo puedes cambiar observaciones.</div>
        @endif

        <form method="POST" action="{{ route('cases.update', $case) }}" class="space-y-8 border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            @csrf
            @method('PATCH')

            @if ($errors->any())
                <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Revisa los campos marcados antes de guardar los cambios.</div>
            @endif

            <section class="space-y-5">
                <div class="border-b border-slate-200 pb-3">
                    <h2 class="font-semibold text-slate-950">Datos de la cirugia</h2>
                    <p class="mt-1 text-sm text-slate-500">Los campos deshabilitados se conservan sin aceptar cambios desde el request.</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <div class="flex items-center justify-between gap-3"><label for="institution_id" class="block text-sm font-medium text-slate-700">Institucion <span class="text-rose-600">*</span></label>@can('masters.manage')<a href="{{ route('masters.institutions.create') }}" class="text-xs font-semibold text-sky-700 hover:text-sky-900">Crear institucion</a>@endcan</div>
                        <select id="institution_id" name="institution_id" @disabled(! $canEdit('institution_id')) class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 {{ $disabledClass }}">
                            @foreach ($institutions as $institution)
                                <option value="{{ $institution->id }}" @selected(old('institution_id', $case->institution_id) == $institution->id)>{{ $institution->name }}</option>
                            @endforeach
                        </select>
                        @error('institution_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-3"><label for="doctor_id" class="block text-sm font-medium text-slate-700">Medico <span class="text-rose-600">*</span></label>@can('masters.manage')<a href="{{ route('masters.doctors.create') }}" class="text-xs font-semibold text-sky-700 hover:text-sky-900">Crear medico</a>@endcan</div>
                        <select id="doctor_id" name="doctor_id" @disabled(! $canEdit('doctor_id')) class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 {{ $disabledClass }}">
                            @foreach ($doctors as $doctor)
                                <option value="{{ $doctor->id }}" @selected(old('doctor_id', $case->doctor_id) == $doctor->id)>{{ $doctor->name }}{{ $doctor->specialty ? ' - '.$doctor->specialty : '' }}</option>
                            @endforeach
                        </select>
                        @error('doctor_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="patient_name" class="block text-sm font-medium text-slate-700">Paciente <span class="text-rose-600">*</span></label>
                        <input id="patient_name" name="patient_name" type="text" value="{{ old('patient_name', $case->patient?->full_name) }}" @disabled(! $canEdit('patient_name')) maxlength="180" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 {{ $disabledClass }}">
                        @error('patient_name')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="surgery_type_id" class="block text-sm font-medium text-slate-700">Tipo de cirugia <span class="text-rose-600">*</span></label>
                        <select id="surgery_type_id" name="surgery_type_id" @disabled(! $canEdit('surgery_type_id')) class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 {{ $disabledClass }}">
                            @foreach ($surgeryTypes as $surgeryType)
                                <option value="{{ $surgeryType->id }}" @selected(old('surgery_type_id', $case->surgery_type_id) == $surgeryType->id)>{{ $surgeryType->name }}</option>
                            @endforeach
                        </select>
                        @error('surgery_type_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="scheduled_at" class="block text-sm font-medium text-slate-700">Fecha y hora de cirugia <span class="text-rose-600">*</span></label>
                        <input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at', $case->scheduled_at?->format('Y-m-d\TH:i')) }}" @disabled(! $canEdit('scheduled_at')) class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 {{ $disabledClass }}">
                        @error('scheduled_at')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="request_origin" class="block text-sm font-medium text-slate-700">Origen de solicitud <span class="text-rose-600">*</span></label>
                        <select id="request_origin" name="request_origin" @disabled(! $canEdit('request_origin')) class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 {{ $disabledClass }}">
                            @foreach (['whatsapp' => 'WhatsApp', 'correo' => 'Correo', 'llamada' => 'Llamada', 'visita_comercial' => 'Visita comercial', 'otro' => 'Otro'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('request_origin', $case->request_origin) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('request_origin')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="space-y-5">
                <div class="border-b border-slate-200 pb-3">
                    <h2 class="font-semibold text-slate-950">Solicitud operativa</h2>
                </div>

                <div>
                    <label for="material_requested" class="block text-sm font-medium text-slate-700">Material solicitado <span class="text-rose-600">*</span></label>
                    <textarea id="material_requested" name="material_requested" rows="3" @disabled(! $canEdit('material_requested')) maxlength="180" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 {{ $disabledClass }}">{{ old('material_requested', $case->procedure_name) }}</textarea>
                    @error('material_requested')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-slate-700">Observaciones <span class="text-rose-600">*</span></label>
                    <textarea id="notes" name="notes" rows="4" required maxlength="2000" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('notes', $case->notes) }}</textarea>
                    @error('notes')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('cases.show', $case) }}" class="inline-flex items-center justify-center border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button type="submit" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-800">Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
