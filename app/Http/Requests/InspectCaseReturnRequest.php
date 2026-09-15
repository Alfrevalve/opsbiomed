<?php

namespace App\Http\Requests;

use App\Models\CaseReturn;
use App\Models\InventoryLot;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class InspectCaseReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('returns.inspect') ?? false;
    }

    public function prepareForValidation(): void
    {
        $traceCode = preg_replace('/\s+/', '', Str::upper(trim((string) $this->input('inventory_lot_trace_code'))));

        $this->merge(['inventory_lot_trace_code' => $traceCode === '' ? null : $traceCode]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inspection_result' => [
                'required',
                'string',
                'in:apto_para_retorno,requiere_limpieza,requiere_revision_tecnica,falla_detectada,no_reutilizable,baja',
            ],
            'inspection_observations' => ['required', 'string', 'min:3', 'max:5000'],
            'inspection_evidence_reference' => ['nullable', 'string', 'max:500'],
            'inspection_responsible_id' => ['required', 'integer', 'exists:users,id'],
            'inspection_date' => ['required', 'date'],
            'inventory_lot_trace_code' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $traceCode = (string) $this->input('inventory_lot_trace_code');
            $caseReturn = $this->route('return');

            if ($traceCode === '' || ! $caseReturn instanceof CaseReturn) {
                return;
            }

            if (! ($this->user()?->can('trace.scan') ?? false)) {
                $validator->errors()->add('inventory_lot_trace_code', 'No tienes permiso para escanear codigos.');

                return;
            }

            $scannedLotId = InventoryLot::query()->where('trace_code', $traceCode)->value('id');
            if ($scannedLotId === null) {
                $validator->errors()->add('inventory_lot_trace_code', 'No se encontro el lote escaneado.');

                return;
            }

            if ((int) $caseReturn->inventory_lot_id !== (int) $scannedLotId) {
                $validator->errors()->add('inventory_lot_trace_code', 'El codigo escaneado no corresponde al lote devuelto.');
            }
        });
    }
}
