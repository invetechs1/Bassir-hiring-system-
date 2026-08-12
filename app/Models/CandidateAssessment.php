<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CandidateAssessment extends Model
{
    protected $fillable = [
        'company_id', 'candidate_id', 'candidate_application_id', 'assessment_id',
        'status', 'score', 'max_score', 'notes', 'document_path',
        'reviewed_by', 'reviewed_at', 'submitted_at',
    ];
    protected $casts = ['reviewed_at' => 'datetime', 'submitted_at' => 'datetime'];

    public function candidate(): BelongsTo { return $this->belongsTo(Candidate::class); }
    public function application(): BelongsTo { return $this->belongsTo(CandidateApplication::class, 'candidate_application_id'); }
    public function assessment(): BelongsTo { return $this->belongsTo(Assessment::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function answers(): HasMany { return $this->hasMany(CandidateAssessmentAnswer::class); }
}
