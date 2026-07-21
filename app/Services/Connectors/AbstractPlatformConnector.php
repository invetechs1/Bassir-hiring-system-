<?php

namespace App\Services\Connectors;

use App\Services\ApiCredentialService;

abstract class AbstractPlatformConnector implements PlatformConnector
{
    public function __construct(protected readonly ApiCredentialService $credentials)
    {
    }

    /**
     * Normalize a platform row into the shape the import pipeline expects,
     * mirroring SearchProviderService::normalize().
     *
     * @return array<string, mixed>
     */
    protected function normalize(string $source, string $title, string $url, string $snippet, string $compliance = 'allowed'): array
    {
        return [
            'source' => $source,
            'title' => $title,
            'url' => $url,
            'snippet' => $snippet,
            'file_type' => 'profile',
            'compliance_status' => $compliance,
            'compliance_note' => 'Official partner API result. Licensed data — confirm candidate consent before outreach.',
        ];
    }
}
