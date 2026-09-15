<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSurgeryCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cases.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'institution_id' => ['required', 'integer', Rule::exists('institutions', 'id')->where('active', true)],
            'doctor_id' => ['required', 'integer', Rule::exists('doctors', 'id')->where('active', true)],
            'patient_name' => ['required', 'string', 'max:180'],
            'surgery_type_id' => ['required', 'integer', 'exists:surgery_types,id'],
            'scheduled_at' => ['required', 'date', 'date_format:Y-m-d\\TH:i'],
            'priority' => ['required', Rule::in(['normal', 'urgente', 'emergencia'])],
            'material_requested' => ['required', 'string', 'max:180'],
            'request_origin' => ['required', Rule::in(['whatsapp', 'correo', 'llamada', 'visita_comercial', 'otro'])],
            'commercial_condition' => ['nullable', 'string', 'max:120'],
            'notes' => ['required', 'string', 'max:2000'],
        ];
    }
}
