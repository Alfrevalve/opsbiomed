<?php

namespace App\Http\Requests;

use App\Support\MasterOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('masters.manage') ?? false;
    }

    public function rules(): array
    {
        $doctor = $this->route('doctor');

        return [
            'name' => ['required', 'string', 'max:180'],
            'cmp' => ['nullable', 'string', 'max:30', Rule::unique('doctors', 'cmp')->ignore($doctor)],
            'specialty' => ['required', Rule::in(MasterOptions::SPECIALTIES)],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:180'],
            'commercial_owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'commercial_profile' => ['required', Rule::in(MasterOptions::COMMERCIAL_PROFILES)],
            'potential' => ['required', Rule::in(MasterOptions::POTENTIALS)],
            'active' => ['required', 'boolean'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
