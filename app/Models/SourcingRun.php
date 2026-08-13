<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourcingRun extends Model
{
    protected $fillable = [
        'company_id', 'sourcing_search_id', 'status', 'results_found',
        'candidates_created', 'candidates_linked', 'cvs_downloaded', 'flagged_manual',
        'message', 'ran_by', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** In-memory defaults so counters are never null before the row is refreshed. */
    protected $attributes = [
        'status' => 'RUNNING',
        'results_found' => 0,
        'candidates_created' => 0,
        'candidates_linked' => 0,
        'cvs_downloaded' => 0,
        'flagged_manual' => 0,
    ];

    public function search(): BelongsTo
    {
        return $this->belongsTo(SourcingSearch::class, 'sourcing_search_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ran_by');
    }
}
