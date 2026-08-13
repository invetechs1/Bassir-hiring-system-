<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingRecord extends Model
{
    protected $fillable = ['company_id', 'candidate_id', 'candidate_application_id', 'offer_id', 'status', 'started_at', 'completed_at'];
    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function candidate(): BelongsTo { return $this->belongsTo(Candidate::class); }
    public function application(): BelongsTo { return $this->belongsTo(CandidateApplication::class, 'candidate_application_id'); }
    public function offer(): BelongsTo { return $this->belongsTo(Offer::class); }
    public function tasks(): HasMany { return $this->hasMany(OnboardingTask::class)->orderBy('sort_order'); }
}
