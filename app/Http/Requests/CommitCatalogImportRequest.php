<?php

namespace App\Http\Requests;

use App\Models\CatalogImport;
use Illuminate\Foundation\Http\FormRequest;

class CommitCatalogImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.commit') ?? false;
    }

    public function rules(): array
    {
        $import = $this->route('import');

        if (! $import instanceof CatalogImport || (int) $import->inconsistencies === 0) {
            return [];
        }

        return [
            'confirm_negative_inconsistencies' => [
                'required',
                'accepted',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_negative_inconsistencies.required' => 'Debes confirmar que las cantidades negativas se importaran como inconsistencias y no como stock disponible.',
            'confirm_negative_inconsistencies.accepted' => 'Debes confirmar que las cantidades negativas se importaran como inconsistencias y no como stock disponible.',
        ];
    }
}
