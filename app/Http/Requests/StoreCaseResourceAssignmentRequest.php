<?php

namespace App\Http\Requests;

use App\Models\SurgeryCase;
use App\Services\Operations\SurgeryScheduleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseResourceAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('case');

        return $case instanceof SurgeryCase
            && ($this->user()?->can('schedule', $case) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'resource_type' => [
                'required',
                'string',
                Rule::in(SurgeryScheduleService::RESOURCE_TYPES),
            ],
            'inventory_lot_id' => [
                'nullable',
                'integer',
                Rule::exists('inventory_lots', 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resource_type.required' => 'Seleccione el tipo de recurso.',
            'resource_type.in' => 'El tipo de recurso seleccionado no es valido.',
            'inventory_lot_id.integer' => 'El recurso seleccionado no es valido.',
            'inventory_lot_id.exists' => 'El recurso seleccionado ya no existe.',
        ];
    }
}
