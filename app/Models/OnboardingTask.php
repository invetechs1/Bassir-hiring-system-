<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTask extends Model
{
    protected $fillable = ['onboarding_record_id', 'title', 'description', 'type', 'status', 'document_path', 'completed_at', 'sort_order'];
    protected $casts = ['completed_at' => 'datetime'];

    public function onboardingRecord(): BelongsTo
    {
        return $this->belongsTo(OnboardingRecord::class);
    }
}
