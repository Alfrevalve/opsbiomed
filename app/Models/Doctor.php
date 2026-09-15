<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    protected $fillable = [
        'name',
        'cmp',
        'specialty',
        'institution_id',
        'commercial_owner_id',
        'phone',
        'email',
        'commercial_profile',
        'potential',
        'active',
        'observations',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function commercialOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_owner_id');
    }

    public function surgeryCases(): HasMany
    {
        return $this->hasMany(SurgeryCase::class);
    }
}
