<?php

namespace App\Http\Requests;

use App\Models\InventoryLot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservations.create') ?? false;
    }

    public function prepareForValidation(): void
    {
        $traceCode = preg_replace('/\s+/', '', Str::upper(trim((string) $this->input('inventory_lot_trace_code'))));

        if ($traceCode === '' || ! ($this->user()?->can('trace.scan') ?? false)) {
            return;
        }

        $inventoryLotId = InventoryLot::query()
            ->where('trace_code', $traceCode)
            ->value('id');

        $this->merge([
            'inventory_lot_trace_code' => $traceCode,
            'inventory_lot_id' => $inventoryLotId ?? $this->input('inventory_lot_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'inventory_lot_id' => ['required', 'integer', 'exists:inventory_lots,id'],
            'inventory_lot_trace_code' => ['nullable', 'string', 'max:160'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $traceCode = (string) $this->input('inventory_lot_trace_code');

            if ($traceCode !== '' && ! ($this->user()?->can('trace.scan') ?? false)) {
                $validator->errors()->add('inventory_lot_trace_code', 'No tienes permiso para escanear codigos.');

                return;
            }

            if ($traceCode !== '' && ! InventoryLot::query()->where('trace_code', $traceCode)->exists()) {
                $validator->errors()->add('inventory_lot_trace_code', 'No se encontro un lote para el codigo escaneado.');
            }
        });
    }
}
