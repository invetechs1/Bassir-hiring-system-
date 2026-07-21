<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = ['provider', 'encrypted_value', 'status', 'last_used_at'];
    protected $casts = ['last_used_at' => 'datetime'];
    protected $hidden = ['encrypted_value'];
}
