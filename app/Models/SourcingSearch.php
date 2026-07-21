<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourcingSearch extends Model
{
    protected $fillable = [
        'company_id', 'name', 'job_title', 'specialization', 'country', 'city',
        'skills', 'software_skills', 'languages', 'quantity', 'providers',
        'download_cvs', 'auto_import', 'default_consent_status', 'frequency',
        'is_active', 'last_run_at', 'last_result_count', 'last_import_count', 'created_by',
    ];

    protected $casts = [
        'skills' => 'array',
        'software_skills' => 'array',
        'languages' => 'array',
        'providers' => 'array',
        'download_cvs' => 'boolean',
        'auto_import' => 'boolean',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SourcingRun::class)->latest();
    }

    /**
     * Build the filter array consumed by SearchProviderService / connectors.
     *
     * @return array<string, mixed>
     */
    public function toFilters(): array
    {
        return [
            'job_title' => $this->job_title,
            'specialization' => $this->specialization,
            'country' => $this->country,
            'city' => $this->city,
            'skills' => $this->skills ?? [],
            'software_skills' => $this->software_skills ?? [],
            'languages' => $this->languages ?? [],
            'quantity' => $this->quantity,
        ];
    }
}
