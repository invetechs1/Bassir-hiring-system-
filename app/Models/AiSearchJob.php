<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiSearchJob extends Model
{
    protected $fillable = ['company_id', 'created_by', 'filters', 'queries', 'status', 'completed_at'];
    protected $casts = ['filters' => 'array', 'queries' => 'array', 'completed_at' => 'datetime'];

    public function results(): HasMany
    {
        return $this->hasMany(AiSearchResult::class);
    }
}
