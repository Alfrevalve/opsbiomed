<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'invoice_status' => ['required', 'in:pendiente_valorizacion,valorizado,pendiente_oc,pendiente_factura,facturado,no_facturable,costo_cero_pendiente_aprobacion,costo_cero_aprobado'],
            'purchase_order' => ['nullable', 'string', 'max:100'],
            'invoice_number' => ['nullable', 'string', 'max:100', 'required_if:invoice_status,facturado'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_status' => ['required', 'in:pendiente,parcial,pagado,vencido'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
