@extends('layouts.ops')

@section('title', 'Nueva solicitud | OPS BIOMED MR8')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <a href="{{ route('cases.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver a solicitudes</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Nueva solicitud quirurgica</h1>
            <p class="mt-2 text-sm text-slate-500">Completa los datos operativos para registrar el caso MR8.</p>
        </div>

        <form method="POST" action="{{ route('cases.store') }}" class="space-y-8 border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            @csrf

            @if ($errors->any())
                <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Revisa los campos marcados antes de guardar la solicitud.</div>
            @endif

            <section class="space-y-5">
                <div class="border-b border-slate-200 pb-3">
                    <h2 class="font-semibold text-slate-950">Datos de la cirugia</h2>
                    <p class="mt-1 text-sm text-slate-500">La institucion, el medico y el tipo de cirugia definen el contexto operativo.</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <div class="flex items-center justify-between gap-3"><label for="institution_id" class="block text-sm font-medium text-slate-700">Institucion <span class="text-rose-600">*</span></label>@can('masters.manage')<a href="{{ route('masters.institutions.create') }}" class="text-xs font-semibold text-sky-700 hover:text-sky-900">Crear institucion</a>@endcan</div>
                        <select id="institution_id" name="institution_id" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Selecciona una institucion</option>
                            @foreach ($institutions as $institution)
                                <option value="{{ $institution->id }}" @selected(old('institution_id') == $institution->id)>{{ $institution->name }}</option>
                            @endforeach
                        </select>
                        @error('institution_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-3"><label for="doctor_id" class="block text-sm font-medium text-slate-700">Medico <span class="text-rose-600">*</span></label>@can('masters.manage')<a href="{{ route('masters.doctors.create') }}" class="text-xs font-semibold text-sky-700 hover:text-sky-900">Crear medico</a>@endcan</div>
                        <select id="doctor_id" name="doctor_id" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Selecciona un medico</option>
                            @foreach ($doctors as $doctor)
                                <option value="{{ $doctor->id }}" @selected(old('doctor_id') == $doctor->id)>{{ $doctor->name }}{{ $doctor->specialty ? ' - '.$doctor->specialty : '' }}</option>
                            @endforeach
                        </select>
                        @error('doctor_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="patient_name" class="block text-sm font-medium text-slate-700">Paciente <span class="text-rose-600">*</span></label>
                        <input id="patient_name" name="patient_name" type="text" value="{{ old('patient_name') }}" required maxlength="180" autocomplete="off" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        @error('patient_name')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="surgery_type_id" class="block text-sm font-medium text-slate-700">Tipo de cirugia <span class="text-rose-600">*</span></label>
                        <select id="surgery_type_id" name="surgery_type_id" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Selecciona un tipo</option>
                            @foreach ($surgeryTypes as $surgeryType)
                                <option value="{{ $surgeryType->id }}" @selected(old('surgery_type_id') == $surgeryType->id)>{{ $surgeryType->name }}</option>
                            @endforeach
                        </select>
                        @error('surgery_type_id')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="scheduled_at" class="block text-sm font-medium text-slate-700">Fecha y hora de cirugia <span class="text-rose-600">*</span></label>
                        <input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}" required class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        @error('scheduled_at')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="request_origin" class="block text-sm font-medium text-slate-700">Origen de solicitud <span class="text-rose-600">*</span></label>
                        <select id="request_origin" name="request_origin" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Selecciona un origen</option>
                            @foreach (['whatsapp' => 'WhatsApp', 'correo' => 'Correo', 'llamada' => 'Llamada', 'visita_comercial' => 'Visita comercial', 'otro' => 'Otro'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('request_origin') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('request_origin')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="space-y-5">
                <div class="border-b border-slate-200 pb-3">
                    <h2 class="font-semibold text-slate-950">Solicitud operativa</h2>
                    <p class="mt-1 text-sm text-slate-500">Registra el material requerido y las observaciones que el equipo debe considerar.</p>
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="priority" class="block text-sm font-medium text-slate-700">Prioridad <span class="text-rose-600">*</span></label>
                        <select id="priority" name="priority" required class="mt-1.5 block w-full border-slate-300 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            @foreach (['normal' => 'Normal', 'urgente' => 'Urgente', 'emergencia' => 'Emergencia'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('priority')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="commercial_condition" class="block text-sm font-medium text-slate-700">Condicion comercial</label>
                        <input id="commercial_condition" name="commercial_condition" type="text" value="{{ old('commercial_condition') }}" maxlength="120" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        @error('commercial_condition')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="material_requested" class="block text-sm font-medium text-slate-700">Material solicitado <span class="text-rose-600">*</span></label>
                    <textarea id="material_requested" name="material_requested" rows="3" required maxlength="180" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('material_requested') }}</textarea>
                    @error('material_requested')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium text-slate-700">Observaciones <span class="text-rose-600">*</span></label>
                    <textarea id="notes" name="notes" rows="4" required maxlength="2000" class="mt-1.5 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('notes') }}</textarea>
                    @error('notes')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('cases.index') }}" class="inline-flex items-center justify-center border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button type="submit" class="inline-flex items-center justify-center bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-800">Registrar solicitud</button>
            </div>
        </form>
    </div>
@endsection
