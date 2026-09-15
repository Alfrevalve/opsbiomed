<?php

namespace App\Http\Requests;

use App\Models\SurgeryCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSurgeryCaseInstrumentistRequest extends FormRequest
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
            'assigned_instrumentist_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_instrumentist_id.integer' => 'El instrumentista seleccionado no es valido.',
            'assigned_instrumentist_id.exists' => 'El instrumentista seleccionado ya no existe.',
        ];
    }
}
