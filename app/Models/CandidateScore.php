<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateScore extends Model
{
    protected $fillable = [
        'candidate_id', 'job_id', 'overall', 'technical', 'experience', 'salary_fit',
        'education', 'location_fit', 'availability', 'notice_period_fit', 'risk',
        'matching_percentage', 'confidence', 'manual_override_score',
        'reviewed_by', 'reviewed_at', 'prompt_version', 'recommendation', 'ranking_band',
        'rationale', 'risk_indicators', 'interview_questions', 'recruiter_decision',
        'recruiter_decision_note', 'recruiter_feedback', 'recruiter_feedback_note',
        'feedback_by', 'feedback_at',
    ];

    protected $casts = [
        'rationale' => 'array',
        'risk_indicators' => 'array',
        'interview_questions' => 'array',
        'reviewed_at' => 'datetime',
        'feedback_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function feedbackBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feedback_by');
    }
}
