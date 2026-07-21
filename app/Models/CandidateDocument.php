<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateDocument extends Model
{
    protected $fillable = [
        'candidate_id',
        'file_name',
        'mime_type',
        'storage_path',
        'checksum',
        'scan_status',
        'download_count',
        'last_downloaded_at',
        'malware_scan_status',
    ];

    protected $casts = [
        'last_downloaded_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
