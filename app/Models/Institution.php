<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    protected $fillable = [
        'name',
        'ruc',
        'institution_type',
        'billing_policy',
        'debt_status',
        'agreement_type',
        'address',
        'sop_contact',
        'pharmacy_contact',
        'billing_contact',
        'phone',
        'email',
        'active',
        'observations',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function surgeryCases(): HasMany
    {
        return $this->hasMany(SurgeryCase::class);
    }
}
