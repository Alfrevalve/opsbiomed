@extends('layouts.ops')

@section('title', 'Documentos del caso '.$case->case_code.' | OPS BIOMED MR8')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('cases.show', $case) }}" class="text-sm font-medium text-sky-700 hover:text-sky-900">Volver al detalle del caso</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Documentos del caso {{ $case->case_code }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $case->institution?->name }} · {{ $case->doctor?->name }} · {{ $case->patient?->full_name }}</p>
        </div>

        @if ($errors->any())
            <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @can('documents.upload')
            <section class="border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-slate-200 pb-4">
                    <h2 class="font-semibold text-slate-950">Cargar evidencia del caso</h2>
                    <p class="mt-1 text-sm text-slate-500">Registra la hoja de consumo, evidencia de falla, OC, factura u otro respaldo.</p>
                </div>
                @include('documents._upload-form', ['case' => $case])
            </section>
        @endcan

        <section class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Evidencias asociadas</h2></div>
            @include('documents._list', ['documents' => $documents, 'documentTypes' => $documentTypes])
        </section>
    </div>
@endsection
