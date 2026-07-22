<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateExperience extends Model
{
    protected $table = 'candidate_experience';

    protected $fillable = [
        'candidate_id',
        'company',
        'title',
        'location',
        'start_date',
        'end_date',
        'is_current',
        'summary',
        'sort_order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];
}
