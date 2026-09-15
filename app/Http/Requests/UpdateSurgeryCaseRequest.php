<?php

namespace App\Http\Requests;

use App\Models\SurgeryCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurgeryCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cases.update') ?? false;
    }

    public function rules(): array
    {
        $case = $this->route('case');

        if (! $case instanceof SurgeryCase) {
            return [];
        }

        $editableFields = $case->status->editableFields();
        $rules = [];

        if (in_array('institution_id', $editableFields, true)) {
            $rules['institution_id'] = ['required', 'integer', Rule::exists('institutions', 'id')->where('active', true)];
            $rules['doctor_id'] = ['required', 'integer', Rule::exists('doctors', 'id')->where('active', true)];
            $rules['patient_name'] = ['required', 'string', 'max:180'];
            $rules['surgery_type_id'] = ['required', 'integer', 'exists:surgery_types,id'];
            $rules['request_origin'] = ['required', Rule::in(['whatsapp', 'correo', 'llamada', 'visita_comercial', 'otro'])];
        }

        if (in_array('scheduled_at', $editableFields, true)) {
            $rules['scheduled_at'] = ['required', 'date', 'date_format:Y-m-d\\TH:i'];
        }

        if (in_array('material_requested', $editableFields, true)) {
            $rules['material_requested'] = ['required', 'string', 'max:180'];
        }

        if (in_array('notes', $editableFields, true)) {
            $rules['notes'] = ['required', 'string', 'max:2000'];
        }

        return $rules;
    }
}
