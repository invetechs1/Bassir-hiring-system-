<?php

namespace App\Services;

use App\Models\Candidate;

class CandidateQualityService
{
    public function calculate(Candidate $candidate): array
    {
        $candidate->loadMissing(['skills', 'languages', 'education', 'certifications', 'experience', 'documents', 'interviews.feedback', 'scores']);

        $completenessSignals = collect([
            $candidate->full_name,
            $candidate->email ?: $candidate->phone,
            $candidate->title,
            $candidate->specialization,
            $candidate->city ?: $candidate->country,
            $candidate->years_experience,
            $candidate->expected_salary,
            $candidate->skills->count() > 0 ? 'skills' : null,
            $candidate->education->count() > 0 ? 'education' : null,
            $candidate->documents->count() > 0 ? 'cv' : null,
        ])->filter()->count();

        $cvCompleteness = (int) min(100, round(($completenessSignals / 10) * 100));
        $skillsStrength = min(100, $candidate->skills->count() * 12);
        $experienceStrength = min(100, ((int) $candidate->years_experience) * 10);
        $educationStrength = min(100, $candidate->education->count() * 35);
        $certificationStrength = min(100, $candidate->certifications->count() * 20);
        $languageStrength = min(100, $candidate->languages->count() * 30);
        $interviewAvg = (float) $candidate->interviews
            ->flatMap(fn ($interview) => $interview->feedback)
            ->avg(fn ($feedback) => (($feedback->technical_score ?? 0) + ($feedback->hr_score ?? 0)) / 2);
        $interviewStrength = $interviewAvg > 0 ? (int) round($interviewAvg) : 60;
        $recruiterRating = $candidate->recruiter_rating ?: 60;
        $hiringOutcome = match ($candidate->status) {
            'HIRED' => 100,
            'OFFER' => 88,
            'SHORTLISTED', 'INTERVIEW' => 76,
            'REJECTED', 'BLACKLISTED' => 35,
            default => 60,
        };

        $quality = (int) round(
            $cvCompleteness * .16
            + $skillsStrength * .16
            + $experienceStrength * .17
            + $educationStrength * .10
            + $certificationStrength * .10
            + $languageStrength * .08
            + $interviewStrength * .10
            + $recruiterRating * .06
            + $hiringOutcome * .07
        );

        return [
            'quality_score' => max(0, min(100, $quality)),
            'cv_completeness_score' => $cvCompleteness,
            'quality_factors' => [
                'cv_completeness' => $cvCompleteness,
                'skills_strength' => $skillsStrength,
                'experience_strength' => $experienceStrength,
                'education_strength' => $educationStrength,
                'certification_strength' => $certificationStrength,
                'language_strength' => $languageStrength,
                'interview_strength' => $interviewStrength,
                'recruiter_rating' => $recruiterRating,
                'hiring_outcome' => $hiringOutcome,
            ],
        ];
    }

    public function update(Candidate $candidate): array
    {
        $quality = $this->calculate($candidate);
        $candidate->forceFill($quality + ['last_quality_calculated_at' => now()])->save();

        return $quality;
    }
}
