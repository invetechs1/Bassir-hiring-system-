<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CandidateApplication extends Model
{
    protected $fillable = [
        'company_id',
        'candidate_id',
        'job_id',
        'source',
        'current_stage',
        'status',
        'applied_at',
        'reviewed_by',
        'ai_shortlisted_at',
        'ai_shortlisted_by',
        'notes',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'ai_shortlisted_at' => 'datetime',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function candidate(): BelongsTo { return $this->belongsTo(Candidate::class); }
    public function job(): BelongsTo { return $this->belongsTo(Job::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function aiShortlister(): BelongsTo { return $this->belongsTo(User::class, 'ai_shortlisted_by'); }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(PipelineStageHistory::class);
    }
}
