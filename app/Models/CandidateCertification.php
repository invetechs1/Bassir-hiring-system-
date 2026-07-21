<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateCertification extends Model
{
    protected $table = 'candidate_certifications';

    protected $fillable = [
        'candidate_id',
        'name',
        'issuer',
        'issue_date',
        'expiry_date',
        'credential_id',
        'url',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
    ];
}
