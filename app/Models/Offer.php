<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Offer extends Model
{
    protected $fillable = [
        'company_id', 'candidate_application_id', 'candidate_id', 'job_id',
        'salary_amount', 'currency', 'start_date', 'employment_type', 'terms',
        'status', 'created_by', 'approved_by', 'approved_at', 'sent_at', 'responded_at', 'pdf_path',
    ];
    protected $casts = [
        'salary_amount' => 'decimal:2',
        'start_date' => 'date',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function candidate(): BelongsTo { return $this->belongsTo(Candidate::class); }
    public function application(): BelongsTo { return $this->belongsTo(CandidateApplication::class, 'candidate_application_id'); }
    public function job(): BelongsTo { return $this->belongsTo(Job::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function onboardingRecord(): HasOne { return $this->hasOne(OnboardingRecord::class); }
}
