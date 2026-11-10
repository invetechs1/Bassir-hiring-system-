<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Natural-language front end to CandidateSearchService ("civil engineer with Primavera 8 years
 * in Riyadh"). It used to recognise only a hard-coded list of 6 cities, 12 job titles and 9 skills
 * and, when none of them appeared in the request, silently returned every candidate in the
 * database ranked by quality score — so an unrecognised search looked like a successful one.
 * Now anything not understood simply isn't matched, and every row says why it was returned.
 */
class TalentSearchAssistantService
{
    public function __construct(private readonly CandidateSearchService $search)
    {
    }

    public function search(string $prompt, ?User $user, int $limit = 50, string $mode = 'balanced'): Collection
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return collect();
        }

        // "low salary / budget / cheap" is a preference about ordering, not something to look for in a CV.
        $lowSalary = (bool) preg_match('/\b(low salary|low budget|budget|cheap|cheapest|lowest salary)\b/i', $prompt);
        $query = trim(preg_replace('/\b(low salary|low budget|budget|cheap|cheapest|lowest salary)\b/i', ' ', $prompt) ?? $prompt);

        $results = $this->search->search($query, $user, ['mode' => $mode, 'limit' => max(200, $limit * 4)]);

        if ($lowSalary) {
            // Relevance still decides the tier; within a tier the cheaper candidate comes first (unknown salary last).
            $results = $results->sort(function (Candidate $a, Candidate $b) {
                return [$b->search_matched_count, $a->expected_salary === null ? 1 : 0, (float) $a->expected_salary]
                    <=> [$a->search_matched_count, $b->expected_salary === null ? 1 : 0, (float) $b->expected_salary];
            })->values();
        }

        return $results->take($limit)->map(function (Candidate $candidate) {
            $candidate->assistant_reason = $this->reason($candidate);

            return $candidate;
        })->values();
    }

    private function reason(Candidate $candidate): string
    {
        $parts = [];
        if (! empty($candidate->search_matches)) {
            $parts[] = 'Matched '.$candidate->search_coverage.': '.implode('; ', $candidate->search_matches);
        }
        if (! empty($candidate->search_missing)) {
            $parts[] = 'Not found: '.implode(', ', $candidate->search_missing);
        }

        return implode(' · ', $parts);
    }
}
