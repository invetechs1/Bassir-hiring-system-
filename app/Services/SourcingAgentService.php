<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\SourcingAgent;
use App\Models\SourcingAgentRun;
use App\Models\SourcingRun;
use App\Models\SourcingSearch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs a "senior HR employee" agent: builds a compliant sourcing search from
 * the agent's requirements, delegates the actual internet search + CV
 * download to the existing AutoSourcingService (which uses only the official
 * Google/Bing/SerpAPI + partner APIs, never scraping), then scores every new
 * candidate against the agent's must/nice/years/location profile and keeps
 * only those above the agent's minimum score as the agent's picks.
 */
class SourcingAgentService
{
    public function __construct(
        private readonly AutoSourcingService $autoSourcing,
        private readonly SpecialtyClassifierService $classifier,
        private readonly AuditService $audit,
    ) {
    }

    public function runAgent(SourcingAgent $agent, ?User $actor = null): SourcingAgentRun
    {
        $run = SourcingAgentRun::create([
            'company_id' => $agent->company_id,
            'sourcing_agent_id' => $agent->id,
            'status' => 'RUNNING',
            'ran_by' => $actor?->id,
            'started_at' => now(),
        ]);

        try {
            $search = $this->materializeSearch($agent, $actor);
            $beforeIds = Candidate::query()
                ->where('company_id', $agent->company_id)
                ->pluck('id')
                ->all();

            $sourcingRun = $this->autoSourcing->runSearch($search, $actor);

            // Everything created for this company during this run window and matching the specialty.
            $newCandidates = Candidate::query()
                ->where('company_id', $agent->company_id)
                ->when(count($beforeIds) > 0, fn ($q) => $q->whereNotIn('id', $beforeIds))
                ->where('specialization', $agent->specialty_name)
                ->get();

            $scannedTotal = $sourcingRun->results_found;
            $added = 0;
            $skippedLow = 0;
            $skippedSpecialty = 0;
            $duplicates = 0;
            $scoreSum = 0;

            // Wrong-specialty candidates the auto-sourcer created — they still exist,
            // just not attached to this agent (recruiter may see them elsewhere).
            $wrongSpecialtyCount = Candidate::query()
                ->where('company_id', $agent->company_id)
                ->when(count($beforeIds) > 0, fn ($q) => $q->whereNotIn('id', $beforeIds))
                ->where('specialization', '!=', $agent->specialty_name)
                ->count();
            $skippedSpecialty += $wrongSpecialtyCount;

            foreach ($newCandidates as $candidate) {
                $score = $this->scoreCandidate($candidate, $agent);
                if ($score['total'] < $agent->min_score) {
                    $skippedLow++;

                    continue;
                }

                // Attach as an agent pick; unique index prevents double-counting.
                try {
                    DB::table('sourcing_agent_candidates')->insert([
                        'sourcing_agent_id' => $agent->id,
                        'sourcing_agent_run_id' => $run->id,
                        'candidate_id' => $candidate->id,
                        'score' => $score['total'],
                        'score_reasons' => json_encode($score['reasons']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $added++;
                    $scoreSum += $score['total'];
                } catch (Throwable) {
                    $duplicates++;
                }
            }

            $run->forceFill([
                'status' => 'SUCCESS',
                'results_scanned' => $scannedTotal,
                'candidates_added' => $added,
                'candidates_skipped_low_score' => $skippedLow,
                'candidates_skipped_wrong_specialty' => $skippedSpecialty,
                'candidates_duplicate' => $duplicates,
                'avg_score' => $added > 0 ? (int) round($scoreSum / $added) : 0,
                'finished_at' => now(),
            ])->save();

            $totalRuns = $agent->runs_count + 1;
            $prevAvg = $agent->avg_score ?? 0;
            $agent->forceFill([
                'runs_count' => $totalRuns,
                'candidates_added' => $agent->candidates_added + $added,
                'last_run_at' => now(),
                // Weighted rolling average of run avg_score.
                'avg_score' => $added > 0
                    ? (int) round((($prevAvg * ($totalRuns - 1)) + $run->avg_score) / $totalRuns)
                    : $prevAvg,
            ])->save();
            $agent->scheduleNext();
        } catch (Throwable $e) {
            $run->forceFill([
                'status' => 'FAILED',
                'message' => Str::limit($e->getMessage(), 480),
                'finished_at' => now(),
            ])->save();
        }

        $this->audit->log($actor?->id, 'SOURCING_AGENT_RUN', 'sourcing_agents', (string) $agent->id, [
            'run_id' => $run->id,
            'status' => $run->status,
            'added' => $run->candidates_added,
            'skipped_low' => $run->candidates_skipped_low_score,
            'skipped_specialty' => $run->candidates_skipped_wrong_specialty,
        ]);

        return $run;
    }

    /**
     * Score a candidate against the agent's requirements. 0-100.
     *
     * @return array{total:int, reasons:array<string,mixed>}
     */
    public function scoreCandidate(Candidate $candidate, SourcingAgent $agent): array
    {
        $must = array_map('strtolower', (array) ($agent->must_have_skills ?? []));
        $nice = array_map('strtolower', (array) ($agent->nice_to_have_skills ?? []));
        $wantLangs = array_map('strtolower', (array) ($agent->languages ?? []));
        $wantCountries = array_map('strtolower', (array) ($agent->countries ?? []));
        $wantCities = array_map('strtolower', (array) ($agent->cities ?? []));

        $candSkills = array_map('strtolower', $candidate->skills->pluck('name')->all() ?: []);
        $candLangs = array_map('strtolower', $candidate->languages->pluck('name')->all() ?: []);

        // Must-have skills — 40 pts.
        $mustHits = count($must) > 0
            ? count(array_intersect($must, $candSkills))
            : 0;
        $mustScore = count($must) > 0 ? (int) round(($mustHits / count($must)) * 40) : 40;

        // Nice-to-have — 20 pts.
        $niceHits = count($nice) > 0
            ? count(array_intersect($nice, $candSkills))
            : 0;
        $niceScore = count($nice) > 0 ? (int) round(($niceHits / count($nice)) * 20) : 10;

        // Years — 20 pts (full if within [min, max], graded penalty otherwise).
        $years = (int) ($candidate->years_experience ?? 0);
        if ($years >= $agent->min_years && $years <= $agent->max_years) {
            $yearsScore = 20;
        } elseif ($years < $agent->min_years) {
            $gap = max(1, $agent->min_years - $years);
            $yearsScore = max(0, 20 - ($gap * 4));
        } else {
            $gap = max(1, $years - $agent->max_years);
            $yearsScore = max(0, 20 - ($gap * 2));
        }

        // Location — 10 pts (city > country > any).
        $city = strtolower((string) ($candidate->city ?? ''));
        $country = strtolower((string) ($candidate->country ?? ''));
        $locScore = 0;
        if (count($wantCities) === 0 && count($wantCountries) === 0) {
            $locScore = 10;
        } elseif ($city !== '' && in_array($city, $wantCities, true)) {
            $locScore = 10;
        } elseif ($country !== '' && in_array($country, $wantCountries, true)) {
            $locScore = 7;
        }

        // Languages — 10 pts.
        $langScore = count($wantLangs) === 0
            ? 10
            : (int) round((count(array_intersect($wantLangs, $candLangs)) / max(1, count($wantLangs))) * 10);

        $total = min(100, $mustScore + $niceScore + $yearsScore + $locScore + $langScore);

        return [
            'total' => $total,
            'reasons' => [
                'must_have' => ['hit' => $mustHits, 'of' => count($must), 'score' => $mustScore],
                'nice_to_have' => ['hit' => $niceHits, 'of' => count($nice), 'score' => $niceScore],
                'years' => ['years' => $years, 'min' => $agent->min_years, 'max' => $agent->max_years, 'score' => $yearsScore],
                'location' => ['city' => $candidate->city, 'country' => $candidate->country, 'score' => $locScore],
                'languages' => ['score' => $langScore],
            ],
        ];
    }

    /**
     * Build (or reuse) the SourcingSearch that represents this agent's brief
     * for the compliant sourcing engine.
     */
    private function materializeSearch(SourcingAgent $agent, ?User $actor): SourcingSearch
    {
        $search = SourcingSearch::firstOrNew([
            'company_id' => $agent->company_id,
            'name' => 'Agent · '.$agent->name,
        ]);

        $countries = (array) ($agent->countries ?? []);
        $cities = (array) ($agent->cities ?? []);

        $search->fill([
            'job_title' => $agent->specialty_name,
            'specialization' => $agent->specialty_name,
            'country' => $countries[0] ?? null,
            'city' => $cities[0] ?? null,
            'skills' => array_values(array_unique(array_merge(
                (array) ($agent->must_have_skills ?? []),
                (array) ($agent->nice_to_have_skills ?? []),
            ))),
            'software_skills' => (array) ($agent->nice_to_have_skills ?? []),
            'languages' => (array) ($agent->languages ?? []),
            'quantity' => (int) $agent->quantity_per_run,
            'providers' => $agent->providers,
            'download_cvs' => true,
            'auto_import' => true,
            'default_consent_status' => 'PENDING',
            'frequency' => 'manual', // driven by the agent scheduler
            'is_active' => true,
            'created_by' => $actor?->id ?? $agent->created_by,
        ])->save();

        return $search;
    }
}
