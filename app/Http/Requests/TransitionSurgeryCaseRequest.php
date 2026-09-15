<?php

namespace App\Http\Requests;

use App\Models\SurgeryCase;
use App\Services\Operations\SurgeryCaseTransitionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionSurgeryCaseRequest extends FormRequest
{
    public function prepareForValidation(): void
    {
        $observation = $this->input('observation');

        $this->merge([
            'override' => $this->boolean('override'),
            'observation' => is_string($observation) && trim($observation) !== ''
                ? trim($observation)
                : null,
        ]);
    }

    public function authorize(): bool
    {
        $case = $this->route('case');

        return $case instanceof SurgeryCase
            && ($this->user()?->can('transition', $case) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'target_status' => [
                'required',
                'string',
                Rule::in(SurgeryCaseTransitionService::selectableStatusValues()),
            ],
            'observation' => ['nullable', 'string', 'max:2000'],
            'override' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'target_status.required' => 'Seleccione el siguiente estado operativo.',
            'target_status.in' => 'El estado operativo seleccionado no es valido.',
            'observation.max' => 'La observacion no puede exceder 2000 caracteres.',
        ];
    }
}
