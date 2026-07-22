<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRecommendationFeedback extends Model
{
    protected $table = 'ai_recommendation_feedback';

    protected $fillable = [
        'company_id',
        'candidate_score_id',
        'candidate_id',
        'job_id',
        'feedback',
        'notes',
        'created_by',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function score(): BelongsTo { return $this->belongsTo(CandidateScore::class, 'candidate_score_id'); }
    public function candidate(): BelongsTo { return $this->belongsTo(Candidate::class); }
    public function job(): BelongsTo { return $this->belongsTo(Job::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
