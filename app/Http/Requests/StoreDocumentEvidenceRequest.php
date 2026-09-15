<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.upload') ?? false;
    }

    public function rules(): array
    {
        $isCaseRoute = $this->route('case') !== null;

        return [
            'documentable_type' => [
                Rule::requiredIf(! $isCaseRoute),
                'nullable',
                'string',
                Rule::in(['case', 'inventory_lot', 'failure', 'return', 'billing', 'approval']),
            ],
            'documentable_id' => [Rule::requiredIf(! $isCaseRoute), 'nullable', 'integer', 'min:1'],
            'document_type' => [
                'required',
                'string',
                Rule::in([
                    'solicitud',
                    'guia_internamiento',
                    'cargo_recepcion',
                    'evidencia_consumo',
                    'hoja_consumo',
                    'reporte_falla',
                    'evidencia_falla',
                    'evidencia_devolucion',
                    'inspeccion',
                    'orden_compra',
                    'factura',
                    'boleta',
                    'aprobacion_costo_cero',
                    'solicitud_pago',
                    'comprobante_pago',
                    'otro',
                ]),
            ],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,xlsx,docx', 'extensions:pdf,jpg,jpeg,png,xlsx,docx', 'max:10240', 'required_without:link_url'],
            'link_url' => ['nullable', 'string', 'max:2048', 'required_without:file'],
            'document_date' => ['nullable', 'date'],
            'is_required' => ['nullable', 'boolean'],
        ];
    }
}
