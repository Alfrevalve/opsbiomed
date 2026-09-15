<?php

namespace App\Http\Requests;

use App\Support\MasterOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('masters.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'ruc' => ['nullable', 'string', 'max:20', 'unique:institutions,ruc'],
            'institution_type' => ['required', Rule::in(MasterOptions::INSTITUTION_TYPES)],
            'address' => ['nullable', 'string', 'max:255'],
            'sop_contact' => ['nullable', 'string', 'max:180'],
            'pharmacy_contact' => ['nullable', 'string', 'max:180'],
            'billing_contact' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:180'],
            'billing_policy' => ['required', Rule::in(MasterOptions::COMMERCIAL_CONDITIONS)],
            'debt_status' => ['required', Rule::in(MasterOptions::DEBT_STATUSES)],
            'active' => ['required', 'boolean'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
