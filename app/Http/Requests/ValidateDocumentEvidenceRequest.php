<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateDocumentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.validate') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['validado', 'observado', 'rechazado'])],
            'validation_observations' => ['nullable', 'string', 'max:5000', 'required_if:status,observado,rechazado'],
        ];
    }
}
