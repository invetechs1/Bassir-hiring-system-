<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    protected $fillable = ['company_id', 'job_id', 'type', 'title', 'description', 'passing_score', 'created_by'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function job(): BelongsTo { return $this->belongsTo(Job::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function questions(): HasMany { return $this->hasMany(AssessmentQuestion::class)->orderBy('sort_order'); }
    public function candidateAssessments(): HasMany { return $this->hasMany(CandidateAssessment::class); }
}
