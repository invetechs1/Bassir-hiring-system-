<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSearchResult extends Model
{
    protected $fillable = ['ai_search_job_id', 'candidate_id', 'source', 'source_url', 'raw_payload', 'score'];
    protected $casts = ['raw_payload' => 'array'];

    public function job(): BelongsTo
    {
        return $this->belongsTo(AiSearchJob::class, 'ai_search_job_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
