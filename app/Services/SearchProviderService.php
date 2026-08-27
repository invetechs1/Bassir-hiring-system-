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

        $ranked = array_values($unique);
        usort($ranked, fn ($a, $b) => $this->relevanceRank($a['match_quality']) <=> $this->relevanceRank($b['match_quality']));

        return array_slice($ranked, 0, $quantity);
    }

    /** Lower rank sorts first: real candidates before uncertain results before non-candidate noise. */
    private function relevanceRank(string $matchQuality): int
    {
        return match ($matchQuality) {
            'likely_candidate' => 0,
            'uncertain' => 1,
            default => 2,
        };
    }

    /** Job boards and recruiting sites (whole domain): a hit here is a posting, never a candidate's own CV. */
    private const JOB_BOARD_HOSTS = [
        'indeed.com', 'bayt.com', 'naukrigulf.com', 'gulftalent.com', 'monstergulf.com', 'akhtaboot.com',
        'tanqeeb.com', 'dubizzle.com', 'laimoon.com', 'glassdoor.com', 'wellfound.com', 'foundit.com',
        'careerjet.com',
    ];

    /**
     * Heuristic pass over the URL/title/snippet to separate real candidate CVs from
     * job postings, recruiting ads, academic papers, and social media posts —
     * search APIs return all of these for the same keywords, only the first is
     * an actual candidate. Domain checks run first since a job board URL is a much
     * more reliable signal than any keyword in the snippet text.
     */
    private function classifyRelevance(string $title, string $url, string $snippet): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        // linkedin.com/in/... is a personal profile page — a strong candidate signal,
        // not a job posting. linkedin.com/jobs/... is the opposite. Check path, not just host.
        if (str_contains($host, 'linkedin.com')) {
            if (str_starts_with($path, '/jobs')) {
                return 'likely_job_posting';
            }
            if (str_starts_with($path, '/in/')) {
                return 'likely_candidate';
            }
        }
        foreach (self::JOB_BOARD_HOSTS as $jobBoard) {
            if ($host !== '' && str_contains($host, $jobBoard)) {
                return 'likely_job_posting';
            }
        }
        // University/academic-institution domains host faculty bios, not job-seeker CVs.
        if ($host !== '' && (str_ends_with($host, '.edu') || str_contains($host, '.edu.') || str_contains($host, '.ac.'))) {
            return 'likely_other';
        }

        $text = strtolower($title.' '.$snippet);

        $jobPostingSignals = [
            'hiring', 'vacancy', 'vacancies', 'apply now', 'job description', 'job opening', 'job openings',
            'job opportunity', 'we are looking for', "we're hiring", 'urgently required', 'urgently require',
            'days ago', 'send cv to', 'send your cv', 'submit your cv', 'phone inquiries', 'the selected candidate',
            'career opportunity', 'required', 'openings', 'open position', 'open positions', 'visa provided',
            'immediate joiner', 'immediate joining', 'walk-in interview', 'multiple positions',
            'positions available', 'contract duration', 'jobs in saudi arabia', 'upload your cv', 'careerjet',
        ];
        // Academic/faculty content ("X is a civil engineer and Professor of...") reads a lot like a real
        // CV summary but isn't a job-seeking candidate — check this before the candidate signals below.
        $otherSignals = [
            'case study', 'journal', 'conference proceedings', 'research paper', 'funded research',
            'peer-reviewed', 'thesis', 'dissertation', 'reel by', 'read caption for more', 'university of',
            'professor', 'instructor of', 'program report',
        ];
        $candidateSignals = [
            'resume of', 'cv of', 'curriculum vitae for', 'this document contains a resume for',
            'this document is a cv for', 'resume overview', 'is a jordanian civil engineer',
            'is an egyptian civil engineer', 'is a sudanese civil engineer', 'years of experience in',
            'currently working in', 'open to work', 'open to opportunities',
        ];

        foreach ($jobPostingSignals as $signal) {
            if (str_contains($text, $signal)) {
                return 'likely_job_posting';
            }
        }
        foreach ($otherSignals as $signal) {
            if (str_contains($text, $signal)) {
                return 'likely_other';
            }
        }
        foreach ($candidateSignals as $signal) {
            if (str_contains($text, $signal)) {
                return 'likely_candidate';
            }
        }

        return 'uncertain';
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
            'match_quality' => $this->classifyRelevance($title, $url, $snippet),
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
