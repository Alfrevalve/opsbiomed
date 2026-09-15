<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveCostZeroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approvals.approve') ?? false;
    }

    public function rules(): array
    {
        return [
            'evidence' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
