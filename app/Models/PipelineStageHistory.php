<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineStageHistory extends Model
{
    protected $fillable = [
        'company_id',
        'candidate_application_id',
        'candidate_id',
        'job_id',
        'from_stage',
        'to_stage',
        'updated_by',
        'note',
        'rejection_reason',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function application(): BelongsTo { return $this->belongsTo(CandidateApplication::class, 'candidate_application_id'); }
    public function candidate(): BelongsTo { return $this->belongsTo(Candidate::class); }
    public function job(): BelongsTo { return $this->belongsTo(Job::class); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
