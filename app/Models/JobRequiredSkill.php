<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobRequiredSkill extends Model
{
    public $timestamps = false;
    protected $fillable = ['job_id', 'name'];
}
