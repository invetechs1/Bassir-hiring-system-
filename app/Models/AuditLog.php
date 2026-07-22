<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = ['company_id', 'actor_id', 'action', 'entity', 'entity_id', 'metadata', 'ip_address'];
    protected $casts = ['metadata' => 'array'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
