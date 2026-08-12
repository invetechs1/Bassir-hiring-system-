<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateAssessmentAnswer extends Model
{
    protected $fillable = ['candidate_assessment_id', 'assessment_question_id', 'answer_text', 'is_correct', 'points_awarded'];
    protected $casts = ['is_correct' => 'boolean'];

    public function candidateAssessment(): BelongsTo { return $this->belongsTo(CandidateAssessment::class); }
    public function question(): BelongsTo { return $this->belongsTo(AssessmentQuestion::class, 'assessment_question_id'); }
}
