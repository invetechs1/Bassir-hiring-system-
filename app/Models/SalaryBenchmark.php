<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryBenchmark extends Model
{
    protected $fillable = ['company_id', 'job_title', 'location', 'min_salary', 'max_salary', 'years_experience_min', 'years_experience_max', 'source'];
}
