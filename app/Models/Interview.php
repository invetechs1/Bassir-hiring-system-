<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Interview extends Model
{
    protected $fillable = [
        'company_id',
        'candidate_id',
        'job_id',
        'interview_type',
        'starts_at',
        'ends_at',
        'status',
        'channel',
        'meeting_link',
    ];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(InterviewFeedback::class);
    }
}
