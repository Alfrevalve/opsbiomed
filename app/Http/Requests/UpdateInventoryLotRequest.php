<?php

namespace App\Http\Requests;

use App\Enums\InventoryStatus;
use App\Models\InventoryLot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInventoryLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'subfamily' => ['sometimes', 'nullable', 'string', 'max:255'],
            'regulatory_record' => ['sometimes', 'nullable', 'string', 'max:255'],
            'regulatory_expiry' => ['sometimes', 'nullable', 'date'],
            'detail_expiry' => ['sometimes', 'nullable', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(array_column(InventoryStatus::cases(), 'value'))],
            'block_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'observations' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'classification' => ['sometimes', 'required', Rule::in(['consumible', 'reusable', 'equipo', 'accesorio', 'instrumental'])],
            'expiry_required' => ['sometimes', 'boolean'],
            'change_reason' => ['nullable', 'string', 'max:2000'],
            'product_code' => ['prohibited'],
            'lot' => ['prohibited'],
            'serial' => ['prohibited'],
            'quantity' => ['prohibited'],
            'reserved_quantity' => ['prohibited'],
            'available_net' => ['prohibited'],
            'warehouse_id' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $lot = $this->route('lot');

                if (! $lot instanceof InventoryLot) {
                    return;
                }

                $reasonRequired = false;

                if ($this->has('status') && $this->input('status') !== $lot->status?->value) {
                    $reasonRequired = true;
                }

                if ($this->has('detail_expiry') && $this->dateValue($this->input('detail_expiry')) !== $lot->expiry?->toDateString()) {
                    $reasonRequired = true;
                }

                if ($this->has('regulatory_expiry') && $this->dateValue($this->input('regulatory_expiry')) !== $lot->product?->regulatory_expiry?->toDateString()) {
                    $reasonRequired = true;
                }

                if ($reasonRequired && blank($this->input('change_reason'))) {
                    $validator->errors()->add('change_reason', 'Debes indicar una justificacion para cambiar estado o vencimiento.');
                }
            },
        ];
    }

    private function dateValue(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return date('Y-m-d', strtotime((string) $value));
        } catch (\Throwable) {
            return null;
        }
    }
}
