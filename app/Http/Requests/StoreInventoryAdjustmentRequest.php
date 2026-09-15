<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') ?? false;
    }

    public function rules(): array
    {
        return [
            'adjustment_type' => [
                'required',
                Rule::in(['conteo_fisico', 'correccion_importacion', 'devolucion', 'perdida', 'baja', 'traslado', 'canje']),
            ],
            'quantity_adjustment' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'allow_inconsistency' => ['sometimes', 'boolean'],
            'adjusted_at' => ['required', 'date'],
        ];
    }
}
