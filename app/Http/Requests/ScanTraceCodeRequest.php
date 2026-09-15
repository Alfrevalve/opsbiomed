<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ScanTraceCodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('trace.scan') ?? false;
    }

    public function prepareForValidation(): void
    {
        $traceCode = preg_replace('/\s+/', '', Str::upper(trim((string) $this->input('trace_code'))));

        $this->merge(['trace_code' => $traceCode]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'trace_code' => ['required', 'string', 'max:160'],
        ];
    }

    public function messages(): array
    {
        return [
            'trace_code.required' => 'Ingresa o escanea un codigo trazable.',
            'trace_code.max' => 'El codigo escaneado es demasiado largo.',
        ];
    }
}
