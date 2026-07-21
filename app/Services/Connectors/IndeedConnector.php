<?php

namespace App\Services\Connectors;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Official Indeed partner API connector (activates when an `indeed_partner`
 * publisher/partner key is configured).
 */
class IndeedConnector extends AbstractPlatformConnector
{
    public function key(): string
    {
        return 'indeed_partner';
    }

    public function label(): string
    {
        return 'Indeed Partner API (official)';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->credentials->get('indeed_partner', 'INDEED_PARTNER_API_KEY'));
    }

    public function search(array $filters): array
    {
        $key = $this->credentials->get('indeed_partner', 'INDEED_PARTNER_API_KEY');
        $endpoint = $this->credentials->get('indeed_endpoint', 'INDEED_PARTNER_API_ENDPOINT');
        if (empty($key) || empty($endpoint)) {
            return [];
        }

        try {
            $response = Http::timeout(20)->get($endpoint, [
                'api_key' => $key,
                'q' => trim(($filters['job_title'] ?? '').' '.($filters['specialization'] ?? '')),
                'l' => trim(($filters['city'] ?? '').' '.($filters['country'] ?? '')),
                'limit' => min((int) ($filters['quantity'] ?? 25), 50),
            ]);
        } catch (Throwable) {
            return [];
        }
        if (! $response->ok()) {
            return [];
        }

        $rows = $response->json('results') ?? $response->json('candidates') ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = $this->normalize(
                'Indeed Partner API',
                (string) ($row['title'] ?? $row['name'] ?? 'Indeed candidate'),
                (string) ($row['url'] ?? $row['resume_url'] ?? ''),
                (string) ($row['snippet'] ?? $row['summary'] ?? '')
            );
        }

        return $out;
    }
}
