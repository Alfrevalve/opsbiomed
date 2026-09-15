<?php

namespace App\Http\Requests;

use App\Enums\FailureStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFailureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('failures.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'failure_type' => ['required', 'string', 'in:no_gira,vibracion,no_encaja,sobrecalentamiento,fractura,desgaste,falla_electrica,falla_mecanica,otro'],
            'occurrence_moment' => ['required', 'string', 'in:antes_cirugia,durante_cirugia,despues_cirugia,mantenimiento,inventario'],
            'severity' => ['required', 'string', 'in:baja,media,alta,critica'],
            'status' => ['required', 'string', Rule::in(FailureStatus::editableValues())],
            'description' => ['required', 'string', 'min:3', 'max:5000'],
            'action_taken' => ['nullable', 'string', 'max:2000'],
            'evidence_reference' => ['nullable', 'string', 'max:500'],
            'responsible_technical_id' => ['nullable', 'integer', 'exists:users,id'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'probable_cause' => ['nullable', 'string', 'max:5000'],
            'corrective_action' => ['nullable', 'string', 'max:5000'],
            'requires_supplier' => ['sometimes', 'boolean'],
            'requires_replacement' => ['sometimes', 'boolean'],
        ];
    }
}
