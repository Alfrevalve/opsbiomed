<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.import') ?? false;
    }

    public function rules(): array
    {
        return [
            'catalog' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:20480',
            ],
        ];
    }
}
