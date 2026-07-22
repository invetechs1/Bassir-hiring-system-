<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TalentPool extends Model
{
    protected $fillable = ['company_id', 'name', 'category', 'description', 'created_by'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(Candidate::class, 'talent_pool_candidates')
            ->withPivot(['added_by', 'notes'])
            ->withTimestamps();
    }
}
