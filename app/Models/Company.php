<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
        'default_locale',
        'default_currency',
        'subscription_status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function talentPools(): HasMany
    {
        return $this->hasMany(TalentPool::class);
    }
}
