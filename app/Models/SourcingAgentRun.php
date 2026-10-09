<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourcingAgentRun extends Model
{
    protected $fillable = [
        'company_id', 'sourcing_agent_id', 'status',
        'results_scanned', 'candidates_added',
        'candidates_skipped_low_score', 'candidates_skipped_wrong_specialty',
        'candidates_duplicate', 'avg_score', 'message', 'ran_by',
        'started_at', 'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(SourcingAgent::class, 'sourcing_agent_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
