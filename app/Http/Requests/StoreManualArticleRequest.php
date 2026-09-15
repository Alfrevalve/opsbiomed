<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreManualArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manual.manage')
            && $this->user()->hasAnyRole(['Administrador', 'Direccion Tecnica']);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', 'alpha_dash', Rule::unique('manual_articles', 'slug')],
            'summary' => ['required', 'string', 'max:2000'],
            'category_id' => ['required', 'integer', 'exists:manual_categories,id'],
            'level' => ['required', Rule::in(['basico', 'operativo', 'supervision', 'administracion'])],
            'body' => ['nullable', 'string', 'max:20000'],
            'steps' => ['nullable', 'string', 'max:10000'],
            'prerequisites' => ['nullable', 'string', 'max:5000'],
            'required_fields' => ['nullable', 'string', 'max:5000'],
            'common_errors' => ['nullable', 'string', 'max:5000'],
            'expected_result' => ['nullable', 'string', 'max:3000'],
            'blocked_action' => ['nullable', 'string', 'max:3000'],
            'escalation_role' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', 'string', 'max:120'],
            'permission' => ['nullable', 'string', 'max:120'],
            'route_name' => ['nullable', 'string', 'max:160'],
            'estimated_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'keywords' => ['nullable', 'string', 'max:3000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'version' => ['required', 'string', 'max:30'],
            'status' => ['required', Rule::in(['published', 'disabled'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('title'))),
            'roles' => array_values(array_filter((array) $this->input('roles', []), 'is_string')),
        ]);
    }
}
