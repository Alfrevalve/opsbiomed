<?php

namespace App\Http\Requests;

use App\Models\InventoryLot;
use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class CloseSurgeryCaseRequest extends FormRequest
{
    public function prepareForValidation(): void
    {
        $materials = $this->input('materials');

        if (! is_array($materials)) {
            return;
        }

        foreach ($materials as $index => $material) {
            if (! is_array($material)) {
                continue;
            }

            foreach (['used_qty', 'unused_opened_qty', 'returned_qty', 'failure_qty'] as $field) {
                $value = $material[$field] ?? null;

                if ($value === null || (is_string($value) && trim($value) === '')) {
                    $materials[$index][$field] = 0;

                    continue;
                }

                if (is_string($value) && ctype_digit(trim($value))) {
                    $materials[$index][$field] = (int) trim($value);
                }
            }

            $traceCode = preg_replace('/\s+/', '', Str::upper(trim((string) ($material['inventory_lot_trace_code'] ?? ''))));
            $materials[$index]['inventory_lot_trace_code'] = $traceCode === '' ? null : $traceCode;
        }

        $this->merge(['materials' => $materials]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('cases.close') ?? false;
    }

    public function rules(): array
    {
        return [
            'materials' => ['required', 'array', 'min:1'],
            'materials.*.reservation_id' => ['required', 'integer', 'distinct', 'exists:reservations,id'],
            'materials.*.used_qty' => ['required', 'integer', 'min:0'],
            'materials.*.unused_opened_qty' => ['required', 'integer', 'min:0'],
            'materials.*.returned_qty' => ['required', 'integer', 'min:0'],
            'materials.*.failure_qty' => ['required', 'integer', 'min:0'],
            'materials.*.difference_reason' => ['nullable', 'string', 'max:2000'],
            'materials.*.failure_description' => ['nullable', 'string', 'max:2000'],
            'materials.*.inventory_lot_trace_code' => ['nullable', 'string', 'max:160'],
            'materials.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'materials.*.cost_zero' => ['nullable', 'boolean'],
            'materials.*.cost_zero_reason' => ['nullable', 'string', 'max:2000'],
            'evidence_description' => ['required', 'string', 'min:3', 'max:2000'],
            'evidence_reference' => ['nullable', 'string', 'max:500'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'materials.*.used_qty.integer' => "La cantidad usada debe ser un n\u{00fa}mero entero.",
            'materials.*.unused_opened_qty.integer' => "La cantidad abierta no usada debe ser un n\u{00fa}mero entero.",
            'materials.*.returned_qty.integer' => "La cantidad devuelta debe ser un n\u{00fa}mero entero.",
            'materials.*.failure_qty.integer' => "La cantidad con falla debe ser un n\u{00fa}mero entero.",
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $reservationLotIds = Reservation::query()
                ->whereIn('id', collect($this->input('materials', []))->pluck('reservation_id')->filter())
                ->pluck('inventory_lot_id', 'id');

            foreach ($this->input('materials', []) as $index => $material) {
                if ($this->isTrue($material['cost_zero'] ?? false) && blank($material['cost_zero_reason'] ?? null)) {
                    $validator->errors()->add(
                        "materials.{$index}.cost_zero_reason",
                        'El costo cero requiere un motivo.',
                    );
                }

                if ((int) ($material['failure_qty'] ?? 0) > 0 && blank($material['failure_description'] ?? null)) {
                    $validator->errors()->add(
                        "materials.{$index}.failure_description",
                        'La falla requiere una descripcion.',
                    );
                }

                $traceCode = (string) ($material['inventory_lot_trace_code'] ?? '');
                if ($traceCode === '') {
                    continue;
                }

                if (! ($this->user()?->can('trace.scan') ?? false)) {
                    $validator->errors()->add(
                        "materials.{$index}.inventory_lot_trace_code",
                        'No tienes permiso para escanear codigos.',
                    );

                    continue;
                }

                $scannedLotId = InventoryLot::query()->where('trace_code', $traceCode)->value('id');
                if ($scannedLotId === null) {
                    $validator->errors()->add(
                        "materials.{$index}.inventory_lot_trace_code",
                        'No se encontro el lote escaneado.',
                    );

                    continue;
                }

                $expectedLotId = $reservationLotIds->get((int) ($material['reservation_id'] ?? 0));
                if ($expectedLotId !== null && (int) $expectedLotId !== (int) $scannedLotId) {
                    $validator->errors()->add(
                        "materials.{$index}.inventory_lot_trace_code",
                        'El codigo escaneado no corresponde al lote reservado.',
                    );
                }
            }
        });
    }

    private function isTrue(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'on'], true);
    }
}
