<?php

namespace App\Http\Requests;

use App\Models\InventoryLot;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFailureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('failures.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id', 'required_without:inventory_lot_id'],
            'inventory_lot_id' => ['nullable', 'integer', 'exists:inventory_lots,id'],
            'case_id' => ['nullable', 'integer', 'exists:surgery_cases,id'],
            'failure_type' => ['required', 'string', 'in:no_gira,vibracion,no_encaja,sobrecalentamiento,fractura,desgaste,falla_electrica,falla_mecanica,otro'],
            'occurrence_moment' => ['required', 'string', 'in:antes_cirugia,durante_cirugia,despues_cirugia,mantenimiento,inventario'],
            'severity' => ['required', 'string', 'in:baja,media,alta,critica'],
            'description' => ['required', 'string', 'min:3', 'max:5000'],
            'action_taken' => ['nullable', 'string', 'max:2000'],
            'evidence_reference' => ['nullable', 'string', 'max:500'],
            'responsible_technical_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('inventory_lot_id') || ! $this->filled('product_id')) {
                return;
            }

            $lot = InventoryLot::query()->find($this->integer('inventory_lot_id'));

            if ($lot !== null && $lot->product_id !== $this->integer('product_id')) {
                $validator->errors()->add('inventory_lot_id', 'El lote no pertenece al producto seleccionado.');
            }
        });
    }
}
