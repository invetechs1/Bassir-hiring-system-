<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SearchProviderService
{
    public function __construct(private readonly ApiCredentialService $credentials)
    {
    }

    public function buildQueries(array $filters): array
    {
        $skills = implode(' ', array_filter(array_merge(
            $filters['skills'] ?? [],
            $filters['software_skills'] ?? [],
            $filters['languages'] ?? []
        )));
        $location = trim(($filters['city'] ?? '').' '.($filters['country'] ?? ''));
        $title = trim((string) ($filters['job_title'] ?? ''));
        $specialization = trim((string) ($filters['specialization'] ?? ''));

        return [
            trim(sprintf('filetype:pdf "%s" "%s" %s "%s" CV resume', $title, $specialization, $skills, $location)),
            trim(sprintf('filetype:docx "%s" "%s" %s "%s" CV resume', $title, $specialization, $skills, $location)),
            trim(sprintf('"%s" "%s" %s "%s" "curriculum vitae" OR resume', $title, $specialization, $skills, $location)),
        ];
    }

    public function cvSourcing(array $filters): array
    {
        $queries = $this->buildQueries($filters);
        $query = $queries[0];
        $quantity = min(max((int) ($filters['quantity'] ?? 25), 1), 100);
        $results = [];

        foreach ($queries as $currentQuery) {
            foreach ($this->googleSearch($currentQuery, min(10, $quantity)) as $item) {
                $results[] = $item;
            }
            foreach ($this->bingSearch($currentQuery, min(25, $quantity)) as $item) {
                $results[] = $item;
            }
            foreach ($this->serpApiSearch($currentQuery, min(20, $quantity)) as $item) {
                $results[] = $item;
            }
        }

        foreach ($this->agencyFeedSearch($query, $filters, min(25, $quantity)) as $item) {
            $results[] = $item;
        }

        $unique = [];
        foreach ($results as $row) {
            if (empty($row['url'])) {
                continue;
            }
            $key = $this->urlKey($row['url']);
            if (! isset($unique[$key])) {
                $unique[$key] = $row;
            }
        }

        return array_slice(array_values($unique), 0, $quantity);
    }

    private function googleSearch(string $query, int $limit): array
    {
        $key = $this->credentials->get('google_cse_key', 'GOOGLE_CUSTOM_SEARCH_API_KEY');
        $cx = $this->credentials->get('google_cse_id', 'GOOGLE_CUSTOM_SEARCH_ENGINE_ID');
        if (empty($key) || empty($cx)) {
            return [];
        }

        try {
            $response = Http::timeout(20)->get('https://www.googleapis.com/customsearch/v1', [
                'key' => $key,
                'cx' => $cx,
                'q' => $query,
                'num' => min($limit, 10),
            ]);
        } catch (Throwable) {
            return [];
        }
        if (! $response->ok()) {
            return [];
        }

        $results = [];
        foreach ($response->json('items', []) as $item) {
            $results[] = $this->normalize('Google Custom Search API', $item['title'] ?? '', $item['link'] ?? '', $item['snippet'] ?? '');
        }

        return $results;
    }

    private function bingSearch(string $query, int $limit): array
    {
        $key = $this->credentials->get('bing_search', 'BING_SEARCH_API_KEY');
        if (empty($key)) {
            return [];
        }

        try {
            $response = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $key])
                ->timeout(20)
                ->get('https://api.bing.microsoft.com/v7.0/search', [
                    'q' => $query,
                    'count' => min($limit, 50),
                ]);
        } catch (Throwable) {
            return [];
        }
        if (! $response->ok()) {
            return [];
        }

        $results = [];
        foreach ($response->json('webPages.value', []) as $item) {
            $results[] = $this->normalize('Bing Search API', $item['name'] ?? '', $item['url'] ?? '', $item['snippet'] ?? '');
        }

        return $results;
    }

    private function serpApiSearch(string $query, int $limit): array
    {
        $key = $this->credentials->get('serpapi', 'SERPAPI_API_KEY');
        if (empty($key)) {
            return [];
        }

        try {
            $response = Http::timeout(20)->get('https://serpapi.com/search.json', [
                'engine' => 'google',
                'q' => $query,
                'api_key' => $key,
                'num' => min($limit, 20),
            ]);
        } catch (Throwable) {
            return [];
        }
        if (! $response->ok()) {
            return [];
        }

        $results = [];
        foreach ($response->json('organic_results', []) as $item) {
            $results[] = $this->normalize('SerpAPI', $item['title'] ?? '', $item['link'] ?? '', $item['snippet'] ?? '');
        }

        return $results;
    }

    private function agencyFeedSearch(string $query, array $filters, int $limit): array
    {
        $url = $this->credentials->get('agency_feed_url', 'AGENCY_FEED_URL');
        if (empty($url)) {
            return [];
        }

        $token = $this->credentials->get('agency_feed_token', 'AGENCY_FEED_TOKEN');
        $client = Http::timeout(20);
        if (! empty($token)) {
            $client = $client->withToken($token);
        }

        try {
            $response = $client->get($url, [
                'q' => $query,
                'limit' => $limit,
                'country' => $filters['country'] ?? null,
                'city' => $filters['city'] ?? null,
                'specialization' => $filters['specialization'] ?? null,
            ]);
        } catch (Throwable) {
            return [];
        }
        if (! $response->ok()) {
            return [];
        }

        $rows = $response->json('results') ?? $response->json('candidates') ?? $response->json() ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $results = [];
        foreach ($rows as $item) {
            if (! is_array($item)) {
                continue;
            }
            $results[] = $this->normalize(
                'Agency Feed API',
                $item['title'] ?? $item['headline'] ?? $item['name'] ?? 'Agency candidate',
                $item['url'] ?? $item['profile_url'] ?? '',
                $item['snippet'] ?? $item['summary'] ?? ''
            );
        }

        return $results;
    }

    private function normalize(string $source, string $title, string $url, string $snippet): array
    {
        $isLinkedIn = str_contains(strtolower($url), 'linkedin.com');
        return [
            'source' => $source,
            'title' => $title,
            'url' => $url,
            'snippet' => $snippet,
            'file_type' => $this->fileType($url),
            'compliance_status' => $isLinkedIn ? 'manual_only' : 'allowed',
            'compliance_note' => $isLinkedIn
                ? 'LinkedIn is official/manual import only. Do not scrape protected profiles.'
                : 'Legal API/public result. Verify source terms, robots policy, and candidate consent before outreach.',
        ];
    }

    private function fileType(string $url): string
    {
        return match (true) {
            preg_match('/\.pdf($|[?#])/i', $url) === 1 => 'pdf',
            preg_match('/\.docx($|[?#])/i', $url) === 1 => 'docx',
            preg_match('/\.doc($|[?#])/i', $url) === 1 => 'doc',
            default => 'profile',
        };
    }

    private function urlKey(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return strtolower(trim($url));
        }

        $host = strtolower($parts['host'] ?? '');
        $path = strtolower($parts['path'] ?? '');

        return $host.$path;
    }
}
