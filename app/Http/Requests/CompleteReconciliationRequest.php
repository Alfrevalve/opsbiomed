<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cases.close')
            || $this->user()?->can('billing.view')
            || $this->user()?->can('billing.update');
    }

    public function rules(): array
    {
        return [
            'observations' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
