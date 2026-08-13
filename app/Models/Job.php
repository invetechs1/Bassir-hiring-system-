<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Job extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'recruiter_id', 'title', 'public_slug', 'specialization', 'department', 'company', 'project',
        'location', 'employment_type', 'required_experience',
        'salary_budget_min', 'salary_budget_max', 'description', 'approval_status',
        'requirements', 'internal_notes', 'hiring_manager', 'vacancies',
    ];

    public function tenantCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }

    public function requiredSkills(): HasMany
    {
        return $this->hasMany(JobRequiredSkill::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(CandidateScore::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CandidateApplication::class);
    }

    public function aiRecommendationFeedback(): HasMany
    {
        return $this->hasMany(AiRecommendationFeedback::class);
    }
}
