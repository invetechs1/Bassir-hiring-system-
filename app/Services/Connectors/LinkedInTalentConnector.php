<?php

namespace App\Services\Connectors;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Official LinkedIn Talent Solutions / partner API connector.
 *
 * This is the ONLY compliant way to pull LinkedIn candidate data: an authorized
 * partner access token, not scraping. It activates automatically once a
 * `linkedin_talent` API key (partner OAuth token) is configured; otherwise it
 * stays dormant and surfaces a "connect your LinkedIn partner account" status.
 */
class LinkedInTalentConnector extends AbstractPlatformConnector
{
    public function key(): string
    {
        return 'linkedin_talent';
    }

    public function label(): string
    {
        return 'LinkedIn Talent Solutions (official API)';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->credentials->get('linkedin_talent', 'LINKEDIN_TALENT_API_TOKEN'));
    }

    public function search(array $filters): array
    {
        $token = $this->credentials->get('linkedin_talent', 'LINKEDIN_TALENT_API_TOKEN');
        $endpoint = $this->credentials->get('linkedin_talent_endpoint', 'LINKEDIN_TALENT_API_ENDPOINT');
        if (empty($token) || empty($endpoint)) {
            return [];
        }

        try {
            $response = Http::withToken($token)->timeout(20)->get($endpoint, [
                'keywords' => trim(($filters['job_title'] ?? '').' '.implode(' ', $filters['skills'] ?? [])),
                'location' => trim(($filters['city'] ?? '').' '.($filters['country'] ?? '')),
                'count' => min((int) ($filters['quantity'] ?? 25), 50),
            ]);
        } catch (Throwable) {
            return [];
        }
        if (! $response->ok()) {
            return [];
        }

        $rows = $response->json('elements') ?? $response->json('results') ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = $this->normalize(
                'LinkedIn Talent API',
                (string) ($row['title'] ?? $row['headline'] ?? $row['name'] ?? 'LinkedIn candidate'),
                (string) ($row['profileUrl'] ?? $row['url'] ?? ''),
                (string) ($row['summary'] ?? $row['snippet'] ?? '')
            );
        }

        return $out;
    }
}
