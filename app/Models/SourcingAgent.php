<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourcingAgent extends Model
{
    protected $fillable = [
        'company_id', 'name', 'persona', 'avatar_emoji', 'bio',
        'specialty_slug', 'specialty_name',
        'countries', 'cities', 'must_have_skills', 'nice_to_have_skills', 'languages',
        'min_years', 'max_years', 'min_score', 'quantity_per_run', 'providers',
        'frequency', 'is_active',
        'last_run_at', 'next_run_at', 'runs_count', 'candidates_added', 'avg_score',
        'created_by',
    ];

    protected $casts = [
        'countries' => 'array',
        'cities' => 'array',
        'must_have_skills' => 'array',
        'nice_to_have_skills' => 'array',
        'languages' => 'array',
        'providers' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public const PERSONAS = [
        'senior_technical' => ['label' => 'Senior Technical Recruiter', 'label_ar' => 'أخصائي توظيف تقني كبير', 'emoji' => '🧑‍💼'],
        'campus_recruiter' => ['label' => 'Campus Recruiter', 'label_ar' => 'أخصائي توظيف خريجين', 'emoji' => '🎓'],
        'executive_headhunter' => ['label' => 'Executive Headhunter', 'label_ar' => 'صائد كفاءات تنفيذية', 'emoji' => '🎯'],
        'volume_recruiter' => ['label' => 'Volume Recruiter', 'label_ar' => 'أخصائي توظيف بالجملة', 'emoji' => '📥'],
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SourcingAgentRun::class)->latest();
    }

    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(Candidate::class, 'sourcing_agent_candidates')
            ->withPivot(['score', 'score_reasons', 'sourcing_agent_run_id'])
            ->withTimestamps();
    }

    public function personaLabel(): string
    {
        return self::PERSONAS[$this->persona]['label'] ?? $this->persona;
    }

    public function personaLabelAr(): string
    {
        return self::PERSONAS[$this->persona]['label_ar'] ?? $this->persona;
    }

    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->frequency === 'manual') {
            return false;
        }
        if ($this->next_run_at === null) {
            return true;
        }

        return $this->next_run_at->isPast();
    }

    public function scheduleNext(): void
    {
        $this->next_run_at = match ($this->frequency) {
            'hourly' => now()->addHour(),
            'weekly' => now()->addWeek(),
            'manual' => null,
            default => now()->addDay(),
        };
        $this->save();
    }
}
