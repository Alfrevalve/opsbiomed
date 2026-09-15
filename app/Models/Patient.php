<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = ['code', 'full_name', 'document_number', 'phone', 'notes'];

    protected function casts(): array
    {
        return [
            'document_number' => 'encrypted',
            'phone' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    public function surgeryCases(): HasMany
    {
        return $this->hasMany(SurgeryCase::class);
    }
}
