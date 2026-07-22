<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Communication extends Model
{
    protected $fillable = ['candidate_id', 'channel', 'direction', 'subject', 'body', 'sent_at'];
    protected $casts = ['sent_at' => 'datetime'];
}
