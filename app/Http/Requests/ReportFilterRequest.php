<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'surgery_type_id' => ['nullable', 'integer', 'exists:surgery_types,id'],
            'case_status' => ['nullable', 'string', 'max:40'],
            'invoice_status' => ['nullable', 'in:pendiente_valorizacion,valorizado,pendiente_oc,pendiente_factura,facturado,no_facturable,costo_cero_pendiente_aprobacion,costo_cero_aprobado'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'urgency' => ['nullable', 'in:critical,high,normal'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'diameter' => ['nullable', 'numeric', 'min:0'],
            'product_type' => ['nullable', 'string', 'max:30'],
        ];
    }
}
