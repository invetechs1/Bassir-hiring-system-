<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateSource extends Model
{
    protected $fillable = [
        'candidate_id',
        'source_type',
        'source_url',
        'consent_note',
        'consent_captured_at',
        'consent_captured_by',
        'consent_evidence',
        'contact_allowed',
    ];

    protected $casts = [
        'consent_captured_at' => 'datetime',
        'contact_allowed' => 'boolean',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
