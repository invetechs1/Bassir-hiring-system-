<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateScore;
use App\Models\Job;
use Illuminate\Support\Collection;

class AiCandidateRankingService
{
    public function __construct(
        private readonly CandidateScoringService $scoring,
        private readonly CandidateQualityService $quality
    ) {
    }

    public function rankJob(Job $job, int $limit = 500): Collection
    {
        $job->loadMissing('requiredSkills');
        $candidates = Candidate::with(['skills', 'languages', 'education', 'certifications', 'experience', 'documents', 'scores'])
            ->when($job->company_id, fn ($query) => $query->where('company_id', $job->company_id))
            ->whereNotIn('status', ['BLACKLISTED'])
            ->take($limit)
            ->get();

        foreach ($candidates as $candidate) {
            $this->quality->update($candidate);
            CandidateScore::updateOrCreate([
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
            ], $this->scoring->score($candidate, $job));
        }

        return $this->scoresForJob($job);
    }

    public function scoresForJob(Job $job)
    {
        return CandidateScore::with(['candidate.skills', 'candidate.languages', 'candidate.education', 'candidate.documents'])
            ->where('job_id', $job->id)
            ->when($job->company_id, fn ($query) => $query->whereHas('candidate', fn ($candidate) => $candidate->where('company_id', $job->company_id)))
            ->orderByDesc('overall')
            ->orderByDesc('confidence')
            ->get();
    }

    public function suggestJobsForCandidate(Candidate $candidate, int $limit = 100): Collection
    {
        $candidate->loadMissing(['skills', 'languages', 'education', 'certifications', 'experience']);
        $this->quality->update($candidate);

        $jobs = Job::with('requiredSkills')
            ->when($candidate->company_id, fn ($query) => $query->where('company_id', $candidate->company_id))
            ->whereIn('approval_status', ['PENDING', 'APPROVED'])
            ->latest()
            ->take($limit)
            ->get();

        foreach ($jobs as $job) {
            CandidateScore::updateOrCreate([
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
            ], $this->scoring->score($candidate, $job));
        }

        return CandidateScore::with('job.requiredSkills')
            ->where('candidate_id', $candidate->id)
            ->whereNotNull('job_id')
            ->orderByDesc('overall')
            ->take(25)
            ->get();
    }

    public function grouped(Collection $scores): array
    {
        return [
            '80_plus' => $scores->where('overall', '>=', 80)->values(),
            '60_79' => $scores->filter(fn ($score) => $score->overall >= 60 && $score->overall < 80)->values(),
            'weak' => $scores->where('overall', '<', 60)->values(),
        ];
    }
}
