<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateEducation extends Model
{
    protected $table = 'candidate_education';

    protected $fillable = [
        'candidate_id',
        'institution',
        'degree',
        'field_of_study',
        'level',
        'start_year',
        'end_year',
        'grade',
    ];
}
