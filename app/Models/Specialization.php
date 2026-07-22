<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Specialization extends Model
{
    protected $fillable = ['name', 'category', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
