<?php

namespace App\Http\Requests;

use App\Support\MasterOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('prices.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'minimum_price' => ['nullable', 'numeric', 'min:0', 'lte:unit_price'],
            'currency' => ['required', Rule::in(['PEN', 'USD'])],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'price_type' => ['required', Rule::in(MasterOptions::PRICE_TYPES)],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'active' => ['required', 'boolean'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('price_type') !== 'costo_cero') {
                return;
            }

            if ((float) $this->input('unit_price') !== 0.0) {
                $validator->errors()->add('unit_price', 'El tipo costo cero requiere precio 0.');
            }

            if (blank($this->input('observations'))) {
                $validator->errors()->add('observations', 'El tipo costo cero requiere una observacion.');
            }
        });
    }
}
