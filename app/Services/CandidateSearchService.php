<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Relevance-ranked search over the candidate database and CV Bank.
 *
 * A query is split into "units" (a role word, a skill such as Primavera P6, a city, a quoted
 * phrase, a minimum-years requirement). Every unit is looked for across the whole profile — name,
 * title, specialty, skills, past roles, certifications, location and the full CV text — and each
 * candidate gets a relevance score plus the exact reasons ("matched: civil (job title), revit
 * (skill) · missing: 8+ years"). Candidates that satisfy more of the request always rank above
 * those that satisfy less, so the top of the list is the best fit instead of "whoever matched one word".
 */
class CandidateSearchService
{
    /** Words that carry no search meaning in a natural-language request. */
    private const STOP_WORDS = [
        'and', 'or', 'with', 'in', 'at', 'of', 'for', 'the', 'a', 'an', 'to', 'who', 'whom', 'has', 'have', 'having',
        'is', 'are', 'from', 'near', 'around', 'find', 'search', 'show', 'me', 'looking', 'look', 'need', 'needs',
        'want', 'candidate', 'candidates', 'cv', 'cvs', 'resume', 'resumes', 'profile', 'profiles', 'experience',
        'experienced', 'years', 'year', 'yrs', 'yr', 'minimum', 'min', 'least', 'over', 'more', 'than', 'plus',
        'good', 'strong', 'expert', 'skilled', 'knowledge', 'able', 'who', 'based', 'located', 'available',
        'في', 'من', 'مع', 'و', 'عن', 'ذو', 'ابحث', 'مرشح', 'مرشحين', 'سيرة', 'ذاتية', 'خبرة', 'عن',
    ];

    /** Extra spellings so a recruiter's shorthand or an Arabic city name still lands on the right CVs. */
    private const SYNONYMS = [
        'qs' => ['qs', 'quantity surveyor', 'quantity surveying'],
        'dev' => ['dev', 'developer'],
        'developer' => ['developer', 'software engineer', 'programmer'],
        'planner' => ['planner', 'planning engineer'],
        'riyadh' => ['riyadh', 'الرياض'],
        'jeddah' => ['jeddah', 'jiddah', 'جدة'],
        'dammam' => ['dammam', 'الدمام'],
        'khobar' => ['khobar', 'الخبر'],
        'mecca' => ['mecca', 'makkah', 'مكة'],
        'medina' => ['medina', 'madinah', 'المدينة'],
        'saudi' => ['saudi', 'ksa', 'السعودية'],
        'uae' => ['uae', 'united arab emirates', 'emirates'],
        'immediate' => ['immediate', 'available now', 'immediately'],
    ];

    /** Columns searched directly on the candidates table: column => [weight, label]. */
    private const COLUMNS = [
        'full_name' => [24, 'name'],
        'title' => [22, 'job title'],
        'specialization' => [18, 'specialty'],
        'city' => [14, 'city'],
        'country' => [8, 'country'],
        'current_company' => [8, 'company'],
        'industry' => [6, 'industry'],
        'nationality' => [6, 'nationality'],
        'availability' => [8, 'availability'],
        'notice_period' => [8, 'notice period'],
        'email' => [18, 'email'],
        'phone' => [18, 'phone'],
        'ai_summary' => [5, 'summary'],
        'cv_text' => [8, 'CV text'],
    ];

    /** Related tables searched through EXISTS: table => [columns, weight, label]. */
    private const RELATIONS = [
        'candidate_skills' => [['name'], 20, 'skill'],
        'candidate_experience' => [['title', 'company'], 12, 'past role'],
        'candidate_certifications' => [['name'], 14, 'certification'],
        'candidate_education' => [['degree', 'field_of_study'], 6, 'education'],
    ];

    private const MAX_UNITS = 8;

    private const MAX_VARIANTS = 6;

    public function __construct(
        private readonly TenantService $tenant,
        private readonly SkillCatalog $skills
    ) {
    }

    /**
     * Break a free-text request into searchable units.
     *
     * @return array{units: array<int, array<string, mixed>>, min_years: ?int}
     */
    public function parse(string $query): array
    {
        $text = mb_strtolower(trim($query));
        $units = [];

        // "quoted phrases" are kept whole.
        if (preg_match_all('/"([^"]{2,})"/u', $text, $quoted)) {
            foreach ($quoted[1] as $phrase) {
                $units[] = $this->unit(trim($phrase), [trim($phrase)], 'phrase');
            }
            $text = preg_replace('/"[^"]*"/u', ' ', $text) ?? $text;
        }

        // "5 years", "8+ yrs", "خبرة 5 سنوات" -> a minimum-experience requirement.
        $minYears = null;
        if (preg_match('/(\d{1,2})\s*\+?\s*(?:years?|yrs?|سنوات|سنة|عام)/u', $text, $years)) {
            $minYears = (int) $years[1];
            $text = str_replace($years[0], ' ', $text);
        }

        $tokens = [];
        foreach (preg_split('/[\s,;|]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $token = preg_replace('/[.,;:!?)\]}]+$/u', '', preg_replace('/^[(\[{]+/u', '', $token) ?? $token) ?? $token;
            if ($token !== '' && ! in_array($token, self::STOP_WORDS, true)) {
                $tokens[] = $token;
            }
        }

        // Greedy longest-first: "primavera p6" and "ms project" are one skill, not two words.
        for ($i = 0; $i < count($tokens); $i++) {
            $matched = false;
            for ($n = min(3, count($tokens) - $i); $n >= 2; $n--) {
                $joined = implode(' ', array_slice($tokens, $i, $n));
                if ($this->skills->canonical($joined) !== null) {
                    $units[] = $this->unit($this->skills->canonical($joined), $this->skills->synonyms($joined), 'skill');
                    $i += $n - 1;
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                continue;
            }

            $token = $tokens[$i];
            if (($canonical = $this->skills->canonical($token)) !== null && ! isset(self::SYNONYMS[$token])) {
                $units[] = $this->unit($canonical, $this->skills->synonyms($token), 'skill');
            } else {
                $units[] = $this->unit($token, self::SYNONYMS[$token] ?? [$this->stem($token)], 'term');
            }
        }

        if ($units === [] && $minYears === null && trim($query) !== '') {
            $units[] = $this->unit(trim($query), [trim($query)], 'term');
        }
        if ($minYears !== null) {
            $units[] = ['label' => $minYears.'+ years', 'variants' => [], 'kind' => 'years', 'years' => $minYears];
        }

        return ['units' => array_slice($units, 0, self::MAX_UNITS), 'min_years' => $minYears];
    }

    /**
     * @param  array{mode?:string, limit?:int, status?:?string, archived?:bool, specialization?:?string}  $options
     *                                                                                                              mode: balanced (default) | all (every term must match) | any (one term is enough)
     * @return Collection<int, Candidate> ranked best-first; each carries search_score, search_matches,
     *                                    search_missing, search_snippet, search_coverage, search_terms
     */
    public function search(string $query, ?User $user, array $options = []): Collection
    {
        $parsed = $this->parse($query);
        $units = $parsed['units'];
        if ($units === []) {
            return collect();
        }

        $required = $this->requiredUnits(count($units), $options['mode'] ?? 'balanced');

        [$sumSql, $sumBindings] = $this->matchedUnitsSql($units);
        $builder = $this->tenant->scope(Candidate::query(), $user)
            ->with(['skills', 'experience', 'certifications', 'education', 'documents' => fn ($q) => $q->latest(), 'scores'])
            ->when(! empty($options['archived']), fn ($q) => $q->onlyTrashed())
            ->when(! empty($options['status']), fn ($q) => $q->where('status', $options['status']))
            ->when(! empty($options['specialization']), fn ($q) => $q->where('specialization', $options['specialization']))
            ->whereRaw($sumSql.' >= ?', [...$sumBindings, $required])
            ->orderByRaw($sumSql.' desc', $sumBindings)
            ->orderByDesc('quality_score')
            ->take(max(1, min(1000, (int) ($options['limit'] ?? 400))));

        $terms = $this->highlightTerms($units);
        $ranked = $builder->get()
            ->map(fn (Candidate $candidate) => $this->score($candidate, $units, $terms))
            // The SQL pre-filter is substring based; the PHP pass is word-aware, so re-apply the threshold.
            ->filter(fn (Candidate $candidate) => $candidate->search_matched_count >= $required)
            ->sort(function (Candidate $a, Candidate $b) {
                return [$b->search_matched_count, $b->search_score, (int) $b->quality_score, $b->id]
                    <=> [$a->search_matched_count, $a->search_score, (int) $a->quality_score, $a->id];
            })
            ->values();

        return $ranked;
    }

    /**
     * Words to highlight on a candidate's profile when a recruiter arrives from a search.
     *
     * @return array<int, string>
     */
    public function termsFor(string $query): array
    {
        return $this->highlightTerms($this->parse($query)['units']);
    }

    /** Wrap every occurrence of a search term in <mark>, escaping everything else. */
    public function highlight(?string $text, array $terms): HtmlString
    {
        $text = (string) $text;
        $terms = array_values(array_filter(array_unique($terms), fn ($t) => mb_strlen((string) $t) >= 2));
        if ($text === '' || $terms === []) {
            return new HtmlString(e($text));
        }
        usort($terms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $pattern = '/('.implode('|', array_map(fn ($t) => preg_quote($t, '/'), $terms)).')/iu';

        $out = '';
        foreach (preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $index => $piece) {
            $out .= $index % 2 === 1 ? '<mark>'.e($piece).'</mark>' : e($piece);
        }

        return new HtmlString($out);
    }

    // ------------------------------------------------------------------ scoring

    private function score(Candidate $candidate, array $units, array $terms): Candidate
    {
        $columns = [];
        foreach (self::COLUMNS as $column => [$weight, $label]) {
            $columns[$column] = mb_strtolower((string) $candidate->{$column});
        }
        $related = [
            'candidate_skills' => $candidate->skills->pluck('name')->map(fn ($v) => mb_strtolower((string) $v))->all(),
            'candidate_experience' => $candidate->experience->map(fn ($e) => mb_strtolower($e->title.' '.$e->company))->all(),
            'candidate_certifications' => $candidate->certifications->pluck('name')->map(fn ($v) => mb_strtolower((string) $v))->all(),
            'candidate_education' => $candidate->education->map(fn ($e) => mb_strtolower($e->degree.' '.$e->field_of_study))->all(),
        ];

        $total = 0.0;
        $matched = [];
        $missing = [];
        $snippet = null;
        $snippetPosition = PHP_INT_MAX;

        foreach ($units as $unit) {
            if ($unit['kind'] === 'years') {
                if ((int) $candidate->years_experience >= $unit['years']) {
                    $total += 14;
                    $matched[] = $unit['label'].' (experience)';
                } else {
                    $missing[] = $unit['label'];
                }

                continue;
            }

            $hits = [];
            foreach (self::COLUMNS as $column => [$weight, $label]) {
                if ($columns[$column] === '') {
                    continue;
                }
                $count = $this->occurrences($columns[$column], $unit['variants'], $unit['whole_word']);
                if ($count > 0) {
                    $hits[$label] = $weight + ($column === 'cv_text' ? min(6, $count - 1) : 0);
                    if (in_array($column, ['cv_text', 'ai_summary'], true) && ($position = $this->firstPosition($columns[$column], $unit['variants'])) !== null && $position < $snippetPosition) {
                        $snippetPosition = $position;
                        $snippet = $this->excerpt((string) $candidate->{$column}, $position);
                    }
                }
            }
            foreach (self::RELATIONS as $table => [$relationColumns, $weight, $label]) {
                foreach ($related[$table] as $value) {
                    if ($this->occurrences($value, $unit['variants'], $unit['whole_word']) > 0) {
                        $hits[$label] = $weight;
                        break;
                    }
                }
            }

            if ($hits === []) {
                $missing[] = $unit['label'];

                continue;
            }
            arsort($hits);
            $best = array_key_first($hits);
            // Best field counts in full; each additional field that also matches adds a little confidence.
            $total += $hits[$best] + min(10, 2 * (count($hits) - 1));
            $matched[] = $unit['label'].' ('.implode(', ', array_slice(array_keys($hits), 0, 2)).')';
        }

        $relevance = (int) min(100, round(($total / (count($units) * 30)) * 100));

        $candidate->setAttribute('search_score', $relevance);
        $candidate->setAttribute('search_matched_count', count($matched));
        $candidate->setAttribute('search_coverage', count($matched).'/'.count($units));
        $candidate->setAttribute('search_matches', $matched);
        $candidate->setAttribute('search_missing', $missing);
        $candidate->setAttribute('search_snippet', $snippet);
        $candidate->setAttribute('search_terms', $terms);

        return $candidate;
    }

    /** Number of times any variant occurs in the (already lower-cased) haystack, whole-word for short terms. */
    private function occurrences(string $haystack, array $variants, bool $wholeWord = false): int
    {
        $count = 0;
        foreach ($variants as $variant) {
            $variant = mb_strtolower($variant);
            if ($variant === '') {
                continue;
            }
            // 2-3 letter terms ("hr", "qs", "p6") would otherwise match inside unrelated words.
            if ($wholeWord || mb_strlen($variant) <= 3) {
                $count += (int) preg_match_all('/(?<![\p{L}\p{N}])'.preg_quote($variant, '/').'(?![\p{L}\p{N}])/u', $haystack);
            } else {
                $count += mb_substr_count($haystack, $variant);
            }
        }

        return $count;
    }

    private function firstPosition(string $haystack, array $variants): ?int
    {
        $best = null;
        foreach ($variants as $variant) {
            $position = $variant === '' ? false : mb_stripos($haystack, $variant);
            if ($position !== false && ($best === null || $position < $best)) {
                $best = $position;
            }
        }

        return $best;
    }

    private function excerpt(string $text, int $position): string
    {
        $start = max(0, $position - 70);
        $piece = mb_substr($text, $start, 190);
        $piece = trim(preg_replace('/\s+/u', ' ', $piece) ?? $piece);

        return ($start > 0 ? '… ' : '').$piece.(mb_strlen($text) > $start + 190 ? ' …' : '');
    }

    private function requiredUnits(int $count, string $mode): int
    {
        return match ($mode) {
            'all' => $count,
            'any' => 1,
            default => $count <= 2 ? $count : (int) ceil($count * 0.6),
        };
    }

    // ---------------------------------------------------------------------- SQL

    /**
     * SQL expression counting how many search units a candidate satisfies, so the database can
     * both filter and order by it and only the genuinely relevant rows reach PHP for fine scoring.
     *
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function matchedUnitsSql(array $units): array
    {
        $parts = [];
        $bindings = [];
        foreach ($units as $unit) {
            if ($unit['kind'] === 'years') {
                $parts[] = '(CASE WHEN candidates.years_experience >= ? THEN 1 ELSE 0 END)';
                $bindings[] = $unit['years'];

                continue;
            }

            $patterns = array_map(fn ($variant) => '%'.$this->escapeLike(mb_strtolower($variant)).'%', $unit['variants']);
            $conditions = [];
            foreach (array_keys(self::COLUMNS) as $column) {
                foreach ($patterns as $pattern) {
                    $conditions[] = "LOWER(candidates.{$column}) LIKE ? ESCAPE '!'";
                    $bindings[] = $pattern;
                }
            }
            foreach (self::RELATIONS as $table => [$relationColumns]) {
                $inner = [];
                foreach ($relationColumns as $column) {
                    foreach ($patterns as $pattern) {
                        $inner[] = "LOWER({$table}.{$column}) LIKE ? ESCAPE '!'";
                        $bindings[] = $pattern;
                    }
                }
                $conditions[] = "EXISTS (SELECT 1 FROM {$table} WHERE {$table}.candidate_id = candidates.id AND (".implode(' OR ', $inner).'))';
            }
            $parts[] = '(CASE WHEN ('.implode(' OR ', $conditions).') THEN 1 ELSE 0 END)';
        }

        return ['('.implode(' + ', $parts).')', $bindings];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    // ------------------------------------------------------------------ helpers

    private function unit(string $label, array $variants, string $kind): array
    {
        $unique = [];
        foreach ($variants as $variant) {
            $variant = trim((string) $variant);
            if ($variant !== '') {
                $unique[mb_strtolower($variant)] ??= $variant;
            }
        }

        return [
            'label' => $label,
            'variants' => array_slice(array_values($unique), 0, self::MAX_VARIANTS),
            'kind' => $kind,
            // "Java" must not match "JavaScript"; role words ("engineer") should still match "engineers".
            'whole_word' => $kind === 'skill',
        ];
    }

    /** "engineers" -> "engineer" so plural/singular spellings find each other. */
    private function stem(string $token): string
    {
        if (mb_strlen($token) > 4 && str_ends_with($token, 's') && ! str_ends_with($token, 'ss')) {
            return mb_substr($token, 0, -1);
        }

        return $token;
    }

    /** @return array<int, string> */
    private function highlightTerms(array $units): array
    {
        $terms = [];
        foreach ($units as $unit) {
            foreach ($unit['variants'] as $variant) {
                $terms[] = $variant;
            }
        }

        return array_values(array_unique($terms));
    }
}
