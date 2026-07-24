<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'full_name', 'email', 'phone', 'linkedin_url', 'title', 'current_company',
        'specialization', 'industry', 'country', 'city',
        'nationality', 'years_experience', 'expected_salary', 'current_salary', 'availability', 'notice_period',
        'ai_summary', 'consent_status', 'consent_captured_at', 'consent_captured_by',
        'consent_evidence', 'contact_allowed', 'quality_score', 'cv_completeness_score',
        'recruiter_rating', 'quality_factors', 'parsed_profile', 'last_quality_calculated_at',
        'status', 'duplicate_hash',
    ];

    protected $casts = [
        'expected_salary' => 'decimal:2',
        'current_salary' => 'decimal:2',
        'consent_captured_at' => 'date',
        'contact_allowed' => 'boolean',
        'quality_factors' => 'array',
        'parsed_profile' => 'array',
        'last_quality_calculated_at' => 'datetime',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function consentCapturer(): BelongsTo { return $this->belongsTo(User::class, 'consent_captured_by'); }
    public function skills(): HasMany { return $this->hasMany(CandidateSkill::class); }
    public function languages(): HasMany { return $this->hasMany(CandidateLanguage::class); }
    public function documents(): HasMany { return $this->hasMany(CandidateDocument::class); }
    public function sources(): HasMany { return $this->hasMany(CandidateSource::class); }
    public function scores(): HasMany { return $this->hasMany(CandidateScore::class); }
    public function experience(): HasMany { return $this->hasMany(CandidateExperience::class)->orderBy('sort_order')->orderByDesc('start_date'); }
    public function education(): HasMany { return $this->hasMany(CandidateEducation::class)->latest('end_year'); }
    public function certifications(): HasMany { return $this->hasMany(CandidateCertification::class)->latest('issue_date'); }
    public function notes(): HasMany { return $this->hasMany(Note::class); }
    public function communications(): HasMany { return $this->hasMany(Communication::class); }
    public function interviews(): HasMany { return $this->hasMany(Interview::class); }
    public function applications(): HasMany { return $this->hasMany(CandidateApplication::class); }
    public function aiRecommendationFeedback(): HasMany { return $this->hasMany(AiRecommendationFeedback::class); }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function talentPools(): BelongsToMany
    {
        return $this->belongsToMany(TalentPool::class, 'talent_pool_candidates')
            ->withPivot(['added_by', 'notes'])
            ->withTimestamps();
    }
}
