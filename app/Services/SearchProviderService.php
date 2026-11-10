<?php

namespace App\Services;

use App\Support\PersonName;
use Illuminate\Support\Facades\Http;
use Throwable;

class SearchProviderService
{
    /** Search engines bias results by country code; unknown countries just get no bias. */
    private const COUNTRY_CODES = [
        'saudi arabia' => 'sa', 'ksa' => 'sa', 'united arab emirates' => 'ae', 'uae' => 'ae', 'qatar' => 'qa',
        'kuwait' => 'kw', 'bahrain' => 'bh', 'oman' => 'om', 'egypt' => 'eg', 'jordan' => 'jo', 'pakistan' => 'pk',
        'india' => 'in', 'lebanon' => 'lb', 'philippines' => 'ph',
    ];

    private const COUNTRY_ALIASES = [
        'saudi arabia' => ['saudi arabia', 'saudi', 'ksa'],
        'ksa' => ['saudi arabia', 'saudi', 'ksa'],
        'united arab emirates' => ['united arab emirates', 'uae', 'emirates'],
        'uae' => ['united arab emirates', 'uae', 'emirates'],
    ];

    /** Job boards and recruiting sites (whole domain): a hit here is a posting, never a candidate's own CV. */
    private const JOB_BOARD_HOSTS = [
        'indeed.com', 'bayt.com', 'naukrigulf.com', 'gulftalent.com', 'monstergulf.com', 'akhtaboot.com',
        'tanqeeb.com', 'dubizzle.com', 'laimoon.com', 'glassdoor.com', 'wellfound.com', 'foundit.com',
        'careerjet.com', 'jooble.org', 'adzuna.com', 'ziprecruiter.com', 'simplyhired.com', 'jobrapido.com',
        'talent.com', 'neuvoo.com', 'jobsora.com', 'jobted.com', 'bebee.com', 'workable.com', 'lever.co',
        'greenhouse.io', 'smartrecruiters.com', 'jobvite.com', 'recruitee.com', 'ashbyhq.com', 'jobzella.com',
        'saudijobs', 'gulfjobs', 'monster.com', 'reed.co.uk',
    ];

    /** Social/reference sites: never a candidate's own CV file. */
    private const NON_CV_HOSTS = [
        'facebook.com', 'instagram.com', 'twitter.com', 'x.com', 'tiktok.com', 'youtube.com', 'pinterest',
        'reddit.com', 'quora.com', 'wikipedia.org', 'academia.edu', 'researchgate.net',
    ];

    /** Template / sample / how-to pages: they talk about CVs but are not a candidate's CV. */
    private const NOT_A_CV_STRONG = [
        '/\btemplates?\b/iu', '/\bsamples?\b/iu', '/\bexamples?\b/iu', '/\bhow\s+to\s+write\b/iu',
        '/\b(?:resume|cv)\s+(?:builder|maker|writing|tips|format)\b/iu', '/\bfree\s+download\b/iu',
        '/\b(?:job|career|interview|resume|cv)\s+guide\b/iu', '/\binterview\s+questions\b/iu',
    ];

    private const JOB_POSTING_STRONG = [
        '/\b(?:we are|we\'re|now|urgently|currently)\s+(?:hiring|recruiting|looking for|seeking)\b/iu',
        '/\bjob\s+(?:description|opening|openings|opportunit(?:y|ies)|alert|posting|listing|vacanc(?:y|ies))\b/iu',
        '/\bvacanc(?:y|ies)\b/iu',
        '/\bapply\s+(?:now|today|online|here|for this)\b/iu',
        '/\bhow\s+to\s+apply\b/iu',
        '/\bwalk-?in\s+interview\b/iu',
        '/\bimmediate\s+join(?:er|ers|ing)\b/iu',
        '/\bvisa\s+(?:provided|sponsorship)\b/iu',
        '/\b(?:multiple|open)\s+positions?\b/iu',
        '/\bpositions?\s+available\b/iu',
        '/\bthe\s+selected\s+candidate\b/iu',
        '/\b(?:send|submit|upload|email)\s+(?:your\s+)?(?:cv|resume)\b/iu',
        '/\burgently\s+required?\b/iu',
        '/\bjobs?\s+in\s+[a-z]/iu',
        '/\b\d+\s+(?:hours?|days?|weeks?|months?)\s+ago\b/iu',
        '/\bcareer\s+opportunit(?:y|ies)\b/iu',
        '/\bphone\s+inquiries\b/iu',
        '/\bcontract\s+duration\b/iu',
        '/\bsalary\s+(?:range|package|offered)\b/iu',
    ];

    private const JOB_POSTING_WEAK = [
        '/\bhiring\b/iu',
        '/\brequired\s+(?:for|immediately)\b/iu',
        '/\bopenings?\b/iu',
        '/\bjob\s+type\b/iu',
        '/\bresponsibilities\b/iu',
    ];

    private const NOT_A_CV = [
        '/\bcase\s+study\b/iu', '/\bjournal\b/iu', '/\bconference\s+proceedings\b/iu', '/\bresearch\s+paper\b/iu',
        '/\bfunded\s+research\b/iu', '/\bpeer-?reviewed\b/iu', '/\bthesis\b/iu', '/\bdissertation\b/iu',
        '/\breel\s+by\b/iu', '/\bread\s+caption\s+for\s+more\b/iu', '/\bprofessor\b/iu', '/\binstructor\s+of\b/iu',
        '/\bprogram\s+report\b/iu', '/\bannual\s+report\b/iu', '/\bbrochure\b/iu', '/\bcatalog(?:ue)?\b/iu',
        '/\bwhite\s*paper\b/iu', '/\bdata\s*sheet\b/iu', '/\btender\b/iu', '/\brequest\s+for\s+proposal\b/iu',
        '/\bcompany\s+profile\b/iu', '/\bcourse\s+(?:outline|syllabus)\b/iu',
    ];

    private const CANDIDATE_STRONG = [
        '/\b(?:resume|cv|curriculum\s+vitae)\s+(?:of|for)\b/iu',
        '/\b(?:my|his|her)\s+(?:cv|resume)\b/iu',
        '/^\W*(?:curriculum\s+vitae|resume|cv)\b/iu',
        '/\b(?:resume|cv|curriculum\s+vitae)\s*[:\-–—|]/iu',
        '/\bopen\s+to\s+(?:work|opportunities)\b/iu',
        '/\bthis\s+document\s+(?:contains|is)\s+(?:a\s+)?(?:resume|cv)\b/iu',
        '/\bresume\s+overview\b/iu',
    ];

    private const CANDIDATE_WEAK = [
        '/\b\d+\+?\s+years?\s+(?:of\s+)?(?:professional\s+)?experience\b/iu',
        '/\b(?:career\s+objective|professional\s+summary|personal\s+(?:details|information|profile)|date\s+of\s+birth|nationality\s*:)/iu',
        '/\breferences\s+(?:available|upon)\b/iu',
        '/\bcurrently\s+working\b/iu',
        '/\bseeking\s+(?:a\s+)?(?:position|role|opportunity)\b/iu',
    ];

    public function __construct(
        private readonly ApiCredentialService $credentials,
        private readonly SkillCatalog $skills
    ) {
    }

    /**
     * Which search sources have credentials configured. With none, every search returns zero
     * results — the UI must say so instead of looking like "no candidates exist".
     *
     * @return array<int, string>
     */
    public function configuredProviders(): array
    {
        $configured = [];
        if ($this->credentials->get('google_cse_key', 'GOOGLE_CUSTOM_SEARCH_API_KEY') && $this->credentials->get('google_cse_id', 'GOOGLE_CUSTOM_SEARCH_ENGINE_ID')) {
            $configured[] = 'Google Custom Search';
        }
        if ($this->credentials->get('bing_search', 'BING_SEARCH_API_KEY')) {
            $configured[] = 'Bing Search';
        }
        if ($this->credentials->get('serpapi', 'SERPAPI_API_KEY')) {
            $configured[] = 'SerpAPI';
        }
        if ($this->credentials->get('agency_feed_url', 'AGENCY_FEED_URL')) {
            $configured[] = 'Agency feed';
        }

        return $configured;
    }

    // ------------------------------------------------------------------ queries

    /**
     * Build the search queries, from most precise to broadest. The engines are searched in this
     * order and we stop as soon as enough real candidates are found.
     *
     * The old version AND-ed every filter (title, specialization, every skill, both languages, city
     * and country) as exact phrases, so a perfectly ordinary request matched nothing and the three
     * "different" queries differed only by file type. Now: a role is a small OR-group of its natural
     * spellings, only the few most important skills are required, languages are used for scoring
     * rather than as search words, and job-posting words are excluded at the engine.
     *
     * @return array<int, string>
     */
    public function buildQueries(array $filters): array
    {
        $roles = $this->roleGroup($filters);
        $skills = $this->skillTerms($filters);
        $city = trim((string) ($filters['city'] ?? ''));
        $country = trim((string) ($filters['country'] ?? ''));
        $cvWords = '(CV OR resume OR "curriculum vitae")';
        $exclude = '-jobs -hiring -vacancy -vacancies -"job description"';

        $join = fn (array $parts) => trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts))) ?? '');
        $quote = fn (string $value) => $value === '' ? '' : '"'.$value.'"';

        return [
            $join(['filetype:pdf', $roles, ...array_map($quote, array_slice($skills, 0, 3)), $quote($city ?: $country), $cvWords, $exclude]),
            $join([$roles, ...array_map($quote, array_slice($skills, 0, 2)), $quote($country ?: $city), $cvWords, $exclude]),
            $join(['filetype:docx', $roles, $quote($country ?: $city), $cvWords, $exclude]),
            $join(['site:linkedin.com/in', $roles, ...array_map($quote, array_slice($skills, 0, 1)), $quote($city ?: $country)]),
        ];
    }

    /** ("Senior BIM Engineer" OR "BIM Engineer") — the title as typed, without seniority, and the specialty. */
    private function roleGroup(array $filters): string
    {
        $title = trim((string) ($filters['job_title'] ?? ''));
        $specialization = $this->singular(trim((string) ($filters['specialization'] ?? '')));
        $core = trim(preg_replace('/\b(senior|sr\.?|junior|jr\.?|lead|principal|chief|head of|associate|assistant|staff)\b/iu', ' ', $title) ?? $title);
        $core = trim(preg_replace('/\s+/', ' ', $core) ?? $core);

        $roles = [];
        foreach ([$title, $core, $specialization] as $role) {
            if ($role !== '' && ! in_array(mb_strtolower($role), array_map('mb_strtolower', $roles), true)) {
                $roles[] = $role;
            }
        }
        if ($roles === []) {
            return '';
        }

        $quoted = array_map(fn ($role) => '"'.$role.'"', array_slice($roles, 0, 3));

        return count($quoted) === 1 ? $quoted[0] : '('.implode(' OR ', $quoted).')';
    }

    /** Software first (the most discriminating), then general skills, de-duplicated by canonical name. */
    private function skillTerms(array $filters): array
    {
        $terms = [];
        foreach (array_merge($filters['software_skills'] ?? [], $filters['skills'] ?? []) as $skill) {
            $skill = trim((string) $skill);
            if ($skill === '') {
                continue;
            }
            $terms[mb_strtolower($this->skills->canonical($skill) ?? $skill)] ??= $this->skills->canonical($skill) ?? $skill;
        }

        return array_values($terms);
    }

    private function singular(string $phrase): string
    {
        return preg_replace('/(?<=[a-rt-z])s$/iu', '', $phrase) ?? $phrase;
    }

    // ------------------------------------------------------------------ running

    public function cvSourcing(array $filters): array
    {
        $queries = $this->buildQueries($filters);
        $quantity = min(max((int) ($filters['quantity'] ?? 25), 1), 100);
        // A page is at most 10 results; cap paging so one search can't burn the whole API quota.
        $maxPages = max(1, min(3, (int) ceil($quantity / 10)));
        $rows = [];

        foreach ($queries as $query) {
            foreach (['googleSearch', 'bingSearch', 'serpApiSearch'] as $provider) {
                for ($page = 0; $page < $maxPages; $page++) {
                    $batch = $this->{$provider}($query, 10, $page * 10, $filters);
                    foreach ($batch as $row) {
                        $this->addRow($rows, $row);
                    }
                    if (count($batch) < 10) {
                        break;
                    }
                    if ($this->enough($rows, $quantity)) {
                        break 3;
                    }
                }
            }
            if ($this->enough($rows, $quantity)) {
                break;
            }
        }

        foreach ($this->agencyFeedSearch(implode(' ', array_filter([$filters['job_title'] ?? null, $filters['specialization'] ?? null])), $filters, min(25, $quantity)) as $row) {
            $this->addRow($rows, $row);
        }

        $ranked = array_map(fn (array $row) => $this->withRelevance($row, $filters), array_values($rows));
        usort($ranked, function (array $a, array $b) {
            $noise = fn (array $row) => in_array($row['match_quality'], ['likely_job_posting', 'likely_other'], true) ? 1 : 0;

            return [$noise($a), -$a['relevance_score']] <=> [$noise($b), -$b['relevance_score']];
        });

        return array_slice($ranked, 0, $quantity);
    }

    /** @param array<string, array<string, mixed>> $rows */
    private function addRow(array &$rows, array $row): void
    {
        if (empty($row['url'])) {
            return;
        }
        $key = $this->urlKey($row['url']);
        $rows[$key] ??= $row;
    }

    /** @param array<string, array<string, mixed>> $rows */
    private function enough(array $rows, int $quantity): bool
    {
        $real = 0;
        foreach ($rows as $row) {
            if (! in_array($row['match_quality'], ['likely_job_posting', 'likely_other'], true)) {
                $real++;
            }
        }

        // Ask for 50% more than needed: some are dropped as duplicates or low relevance once scored.
        return $real >= (int) ceil($quantity * 1.5);
    }

    // ------------------------------------------------------------------ scoring

    /**
     * Score one result against what the recruiter asked for (0-100), and say what was and wasn't seen.
     * Only the search snippet/title/URL are visible here, so a skill missing from the snippet is
     * reported as "not seen" rather than "absent" — the full CV is checked after import.
     */
    private function withRelevance(array $row, array $filters): array
    {
        $text = mb_strtolower(rawurldecode($row['title'].' '.$row['snippet'].' '.preg_replace('/[\/_\-.]+/', ' ', (string) parse_url($row['url'], PHP_URL_PATH))));
        $matched = [];
        $missing = [];
        $earned = 0.0;
        $possible = 10.0; // file-type / candidate-signal bonus is always possible

        // Role (40): the exact phrase, or most of its words.
        $possible += 40;
        $roleHit = false;
        $phrases = array_filter([
            trim((string) ($filters['job_title'] ?? '')),
            trim(preg_replace('/\b(senior|sr\.?|junior|jr\.?|lead|principal|chief|associate)\b/iu', ' ', (string) ($filters['job_title'] ?? '')) ?? ''),
            $this->singular(trim((string) ($filters['specialization'] ?? ''))),
        ]);
        foreach ($phrases as $phrase) {
            if ($this->containsWords($text, $phrase)) {
                $roleHit = true;
                $earned += 40;
                $matched[] = $phrase;
                break;
            }
        }
        if (! $roleHit && $phrases !== []) {
            $words = array_values(array_filter(preg_split('/[\s\/]+/u', mb_strtolower(trim(preg_replace('/\b(senior|sr\.?|junior|jr\.?|lead|principal)\b/iu', ' ', (string) (($filters['job_title'] ?? '') ?: ($filters['specialization'] ?? ''))) ?? ''))) ?: [], fn ($w) => mb_strlen($w) >= 3));
            if ($words !== []) {
                $hits = count(array_filter($words, fn ($w) => $this->containsWords($text, $w)));
                $earned += 30 * ($hits / count($words));
                if ($hits > 0) {
                    $matched[] = 'role (partial)';
                }
            }
        }

        // Skills (30).
        $skills = $this->skillTerms($filters);
        if ($skills !== []) {
            $possible += 30;
            $hits = 0;
            foreach ($skills as $skill) {
                $found = false;
                foreach ($this->skills->synonyms($skill) as $variant) {
                    if ($this->containsWords($text, $variant)) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $hits++;
                    $matched[] = $skill;
                } else {
                    $missing[] = $skill;
                }
            }
            $earned += 30 * ($hits / count($skills));
        }

        // Location (15): city 10 + country 5, only when the engine actually shows a place.
        $city = mb_strtolower(trim((string) ($filters['city'] ?? '')));
        $country = mb_strtolower(trim((string) ($filters['country'] ?? '')));
        if ($city !== '' || $country !== '') {
            $possible += 15;
            if ($city !== '') {
                if ($this->containsWords($text, $city)) {
                    $earned += 10;
                    $matched[] = $filters['city'];
                } else {
                    $missing[] = $filters['city'];
                }
            } else {
                $earned += 10; // no city requested: don't penalise
            }
            if ($country !== '') {
                foreach (self::COUNTRY_ALIASES[$country] ?? [$country] as $alias) {
                    if ($this->containsWords($text, $alias)) {
                        $earned += 5;
                        break;
                    }
                }
            } else {
                $earned += 5;
            }
        }

        // Languages (5).
        $languages = array_filter(array_map('trim', $filters['languages'] ?? []));
        if ($languages !== []) {
            $possible += 5;
            $hits = count(array_filter($languages, fn ($l) => $this->containsWords($text, mb_strtolower($l))));
            $earned += 5 * ($hits / count($languages));
        }

        // Bonus (10): a real CV file or a personal profile, and a confident candidate classification.
        if (in_array($row['file_type'], ['pdf', 'doc', 'docx'], true)) {
            $earned += 6;
        } elseif (str_contains(mb_strtolower($row['url']), 'linkedin.com/in/')) {
            $earned += 4;
        }
        if ($row['match_quality'] === 'likely_candidate') {
            $earned += 4;
        }

        $score = (int) round(100 * $earned / $possible);
        if (in_array($row['match_quality'], ['likely_job_posting', 'likely_other'], true)) {
            $score = (int) round($score * 0.4);
        }

        $row['relevance_score'] = max(0, min(100, $score));
        $row['matched_terms'] = array_values(array_unique(array_map('strval', $matched)));
        $row['missing_terms'] = array_values(array_unique(array_map('strval', $missing)));

        return $row;
    }

    private function containsWords(string $haystack, string $needle): bool
    {
        $needle = trim(mb_strtolower($needle));
        if ($needle === '') {
            return false;
        }

        // Optional trailing "s" so "engineer" finds "engineers".
        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'s?(?![\p{L}\p{N}])/u', $haystack) === 1;
    }

    // ----------------------------------------------------------- classification

    /**
     * Separate real candidate CVs from job postings, company/academic pages and social posts —
     * search engines return all of these for the same keywords and only the first is a candidate.
     *
     * Evidence is counted on both sides instead of "first keyword wins": a CV that merely mentions
     * "hiring" or "required" is still a CV, and "University of ..." appears in every CV's education
     * section, so none of those single words decide the outcome any more.
     */
    private function classifyRelevance(string $title, string $url, string $snippet, string $fileType): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower(rawurldecode((string) parse_url($url, PHP_URL_PATH)));

        // linkedin.com/in/... is a personal profile page; linkedin.com/jobs/... is the opposite. Check path, not just host.
        if (str_contains($host, 'linkedin.com')) {
            if (str_starts_with($path, '/jobs') || str_starts_with($path, '/company')) {
                return 'likely_job_posting';
            }
            if (str_starts_with($path, '/in/')) {
                return 'likely_candidate';
            }
        }
        if ($host !== '' && preg_match('/(^|\.)(jobs|careers)\./', $host)) {
            return 'likely_job_posting';
        }
        foreach (self::JOB_BOARD_HOSTS as $jobBoard) {
            if ($this->hostMatches($host, $jobBoard)) {
                return 'likely_job_posting';
            }
        }
        foreach (self::NON_CV_HOSTS as $other) {
            if ($this->hostMatches($host, $other)) {
                return 'likely_other';
            }
        }
        // University/academic-institution domains host faculty bios, not job-seeker CVs.
        if ($host !== '' && (str_ends_with($host, '.edu') || str_contains($host, '.edu.') || str_contains($host, '.ac.'))) {
            return 'likely_other';
        }

        // A page whose title ends in "- Careers" is an employer's recruiting page, not a person.
        if (preg_match('/(?:^|[-|:–—]\s*)careers?\s*$/iu', trim($title))) {
            return 'likely_job_posting';
        }

        $text = $title.' '.$snippet;
        if ($this->countMatches(self::NOT_A_CV_STRONG, $text) > 0) {
            return 'likely_other';
        }
        $job = $this->countMatches(self::JOB_POSTING_STRONG, $text) * 2 + $this->countMatches(self::JOB_POSTING_WEAK, $text);
        $other = $this->countMatches(self::NOT_A_CV, $text);
        $candidate = $this->countMatches(self::CANDIDATE_STRONG, $title) * 2
            + $this->countMatches(self::CANDIDATE_STRONG, $snippet) * 2
            + $this->countMatches(self::CANDIDATE_WEAK, $snippet);
        // A CV-looking file name or folder is strong evidence for a downloadable document.
        if (preg_match('/(?:^|[\/_\-. ])(?:cv|resume|resumes|curriculum|vitae)(?:[\/_\-. ]|$)/i', $path)) {
            $candidate += 2;
        }
        if (in_array($fileType, ['pdf', 'doc', 'docx'], true)) {
            $candidate += 1;
        }

        if ($job >= 2 && $job > $candidate) {
            return 'likely_job_posting';
        }
        if ($other >= 1 && $candidate < 2) {
            return 'likely_other';
        }
        if ($candidate >= 2) {
            return 'likely_candidate';
        }
        if ($job >= 1) {
            return 'likely_job_posting';
        }

        return 'uncertain';
    }

    /** "x.com" must match x.com and its subdomains but not dropbox.com; names without a dot match anywhere in the host. */
    private function hostMatches(string $host, string $domain): bool
    {
        if ($host === '') {
            return false;
        }

        return str_contains($domain, '.')
            ? ($host === $domain || str_ends_with($host, '.'.$domain))
            : str_contains($host, $domain);
    }

    /** @param array<int, string> $patterns */
    private function countMatches(array $patterns, string $text): int
    {
        $count = 0;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $count++;
            }
        }

        return $count;
    }

    // ---------------------------------------------------------------- providers

    private function googleSearch(string $query, int $limit, int $offset = 0, array $filters = []): array
    {
        $key = $this->credentials->get('google_cse_key', 'GOOGLE_CUSTOM_SEARCH_API_KEY');
        $cx = $this->credentials->get('google_cse_id', 'GOOGLE_CUSTOM_SEARCH_ENGINE_ID');
        if (empty($key) || empty($cx)) {
            return [];
        }

        $params = ['key' => $key, 'cx' => $cx, 'q' => $query, 'num' => min($limit, 10), 'start' => $offset + 1];
        if ($code = $this->countryCode($filters)) {
            $params['gl'] = $code;
        }

        try {
            $response = Http::timeout(20)->get('https://www.googleapis.com/customsearch/v1', $params);
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

    private function bingSearch(string $query, int $limit, int $offset = 0, array $filters = []): array
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
                    'offset' => $offset,
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

    private function serpApiSearch(string $query, int $limit, int $offset = 0, array $filters = []): array
    {
        $key = $this->credentials->get('serpapi', 'SERPAPI_API_KEY');
        if (empty($key)) {
            return [];
        }

        $params = ['engine' => 'google', 'q' => $query, 'api_key' => $key, 'num' => min($limit, 20), 'start' => $offset];
        if ($code = $this->countryCode($filters)) {
            $params['gl'] = $code;
        }
        if ($place = trim(implode(', ', array_filter([$filters['city'] ?? null, $filters['country'] ?? null])))) {
            $params['location'] = $place;
        }

        try {
            $response = Http::timeout(25)->get('https://serpapi.com/search.json', $params);
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

    private function countryCode(array $filters): ?string
    {
        return self::COUNTRY_CODES[mb_strtolower(trim((string) ($filters['country'] ?? '')))] ?? null;
    }

    private function normalize(string $source, string $title, string $url, string $snippet): array
    {
        $isLinkedIn = str_contains(strtolower($url), 'linkedin.com');
        $fileType = $this->fileType($url);

        return [
            'source' => $source,
            'title' => $title,
            'url' => $url,
            'snippet' => $snippet,
            'file_type' => $fileType,
            'compliance_status' => $isLinkedIn ? 'manual_only' : 'allowed',
            'compliance_note' => $isLinkedIn
                ? 'LinkedIn is official/manual import only. Do not scrape protected profiles.'
                : 'Legal API/public result. Verify source terms, robots policy, and candidate consent before outreach.',
            'match_quality' => $this->classifyRelevance($title, $url, $snippet, $fileType),
            'candidate_name' => $this->candidateName($title, $snippet, $url),
        ];
    }

    /**
     * The candidate's real name when the result gives it away: a LinkedIn headline ("Name - Role - Company"),
     * a "resume of Name" / "Name is a ..." snippet, or a CV file called Name_Surname_CV.pdf.
     */
    public function candidateName(string $title, string $snippet, string $url = ''): ?string
    {
        $namePattern = "\p{Lu}[\p{L}'\-]+(?:\s+\p{Lu}[\p{L}'\-]+){1,3}";
        $patterns = [
            // Only the keyword is case-insensitive; the name itself must still be capitalised.
            "/\b(?i:resume|cv|curriculum vitae)\s+(?i:of|for)\s+({$namePattern})/u",
            "/\b(?i:resume|cv)\s*[:\-–]\s*({$namePattern})/u",
            "/\b(?i:profile):\s*({$namePattern})\s*\./u",
            "/({$namePattern})\s+is\s+(?:a|an)\s/u",
        ];
        foreach ([$title, $snippet] as $source) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $source, $m) && PersonName::looksLikeName($m[1])) {
                    return PersonName::tidy($m[1]);
                }
            }
        }

        if (str_contains(strtolower($url), 'linkedin.com/in/') && ($first = preg_split('/\s+[-–—|]\s+/u', $title)[0] ?? '') && PersonName::looksLikeName($first)) {
            return PersonName::tidy($first);
        }

        $leading = preg_split('/\s+[-–—|:]\s+/u', $title)[0] ?? '';
        if ($leading !== '' && PersonName::looksLikeName($leading)) {
            return PersonName::tidy($leading);
        }

        return $url !== '' ? PersonName::fromFileName((string) parse_url($url, PHP_URL_PATH)) : null;
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

        $host = preg_replace('/^www\./', '', strtolower($parts['host'] ?? ''));
        $path = rtrim(strtolower($parts['path'] ?? ''), '/');

        return $host.$path;
    }
}
