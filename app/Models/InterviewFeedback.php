<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterviewFeedback extends Model
{
    protected $table = 'interview_feedback';
    protected $fillable = ['interview_id', 'evaluator_id', 'technical_score', 'hr_score', 'recommendation', 'comments'];
}
