<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Job;

class CandidateScoringService
{
    public function score(Candidate $candidate, Job $job): array
    {
        $required = $this->jobRelationItems($job, 'requiredSkills')->pluck('name')->map(fn ($v) => strtolower(trim((string) $v)))->filter()->values();
        $candidateSkills = $this->relationItems($candidate, 'skills')->pluck('name')->map(fn ($v) => strtolower(trim((string) $v)))->filter()->values();
        $matchedSkills = $required->filter(fn ($skill) => $candidateSkills->contains($skill))->values()->all();
        $missing = $required->filter(fn ($skill) => ! $candidateSkills->contains($skill))->values()->all();

        $technical = $required->count() ? (int) round((count($matchedSkills) / $required->count()) * 100) : 70;
        $experience = $this->experienceScore((int) $candidate->years_experience, (int) $job->required_experience);
        $education = $this->educationScore($candidate, $job);
        $midpoint = (((float) $job->salary_budget_min + (float) $job->salary_budget_max) / 2) ?: 1;
        $salaryDiff = abs(((float) $candidate->expected_salary ?: $midpoint) - $midpoint) / $midpoint;
        $salaryFit = max(20, (int) round(100 - ($salaryDiff * 100)));
        $availability = $this->availabilityScore($candidate->availability ?: $candidate->notice_period);
        $noticePeriodFit = $this->availabilityScore($candidate->notice_period ?: $candidate->availability);
        $locationFit = $this->locationScore($candidate, $job);
        $riskIndicators = $this->redFlags($candidate, $job, $missing, $salaryFit, $locationFit, $experience);
        $risk = min(100, max(5, 18 + (count($riskIndicators) * 10) + (int) round((100 - $technical) * .18)));
        $overall = (int) round(
            $technical * .28
            + $experience * .22
            + $education * .10
            + $salaryFit * .14
            + $locationFit * .10
            + $availability * .08
            + $noticePeriodFit * .08
        );
        $profileCompleteness = collect([
            $candidate->full_name,
            $candidate->title,
            $candidate->specialization,
            $candidate->years_experience,
            $candidate->expected_salary,
            $candidate->email ?: $candidate->phone,
            $candidate->city ?: $candidate->country,
            $this->relationItems($candidate, 'education')->count() > 0 ? 'education' : null,
            $candidateSkills->count() > 0 ? 'skills' : null,
        ])->filter()->count();
        $confidence = (int) min(95, max(40, round(($profileCompleteness / 9) * 100)));
        $questions = $this->interviewQuestions($candidate, $job, $missing, $riskIndicators);

        return [
            'overall' => $overall,
            'technical' => $technical,
            'experience' => $experience,
            'education' => $education,
            'salary_fit' => $salaryFit,
            'location_fit' => $locationFit,
            'availability' => $availability,
            'notice_period_fit' => $noticePeriodFit,
            'risk' => $risk,
            'matching_percentage' => $overall,
            'confidence' => $confidence,
            'prompt_version' => 'rules-v2',
            'recommendation' => $this->recommendation($overall),
            'ranking_band' => $this->rankingBand($overall),
            'risk_indicators' => $riskIndicators,
            'interview_questions' => $questions,
            'rationale' => [
                'matched_required_skills' => count($matchedSkills),
                'total_required_skills' => $required->count(),
                'matched_skills' => $matchedSkills,
                'missing_skills' => $missing,
                'missing_requirements' => $missing,
                'skills_match' => $technical,
                'experience_match' => $experience,
                'education_match' => $education,
                'salary_expectation_match' => $salaryFit,
                'location_fit' => $locationFit,
                'notice_period_match' => $noticePeriodFit,
                'availability_match' => $availability,
                'risk_indicators' => $riskIndicators,
                'ranking_band' => $this->rankingBand($overall),
                'confidence' => $confidence,
                'human_review_required' => true,
                'reason_for_ranking' => $this->rankingReason($overall, $technical, $experience, $salaryFit, $missing),
                'ai_disclaimer' => 'AI scoring is a decision-support signal only. HR must validate experience, consent, fit, and legal compliance before any hiring decision.',
            ],
        ];
    }

    private function recommendation(int $score): string
    {
        return match (true) {
            $score >= 85 => 'Highly Recommended',
            $score >= 72 => 'Recommended',
            $score >= 55 => 'Maybe',
            default => 'Not Recommended',
        };
    }

    private function rankingBand(int $score): string
    {
        return match (true) {
            $score >= 80 => '80_PLUS',
            $score >= 60 => '60_79',
            default => 'WEAK',
        };
    }

    private function experienceScore(int $candidateYears, int $requiredYears): int
    {
        if ($requiredYears <= 0) {
            return min(100, 65 + ($candidateYears * 3));
        }

        $ratio = $candidateYears / max(1, $requiredYears);
        return (int) min(100, max(20, round($ratio * 100)));
    }

    private function educationScore(Candidate $candidate, Job $job): int
    {
        $educationItems = $this->relationItems($candidate, 'education');
        $educationText = strtolower($educationItems->pluck('degree')->implode(' ').' '.$educationItems->pluck('institution')->implode(' '));
        $requirements = strtolower((string) $job->requirements.' '.(string) $job->description);

        if ($educationText === '') {
            return str_contains($requirements, 'degree') || str_contains($requirements, 'bachelor') ? 45 : 65;
        }
        if (str_contains($educationText, 'phd') || str_contains($educationText, 'doctor')) {
            return 95;
        }
        if (str_contains($educationText, 'master')) {
            return 90;
        }
        if (str_contains($educationText, 'bachelor') || str_contains($educationText, 'degree')) {
            return 82;
        }
        if (str_contains($educationText, 'diploma')) {
            return 70;
        }

        return 62;
    }

    private function availabilityScore(?string $value): int
    {
        $value = strtolower(trim((string) $value));
        return match (true) {
            $value === '' => 60,
            str_contains($value, 'immediate') || str_contains($value, 'now') => 100,
            str_contains($value, '15') => 90,
            str_contains($value, '30') || str_contains($value, 'one month') => 78,
            str_contains($value, '45') => 66,
            str_contains($value, '60') || str_contains($value, 'two month') => 55,
            str_contains($value, '90') => 38,
            default => 65,
        };
    }

    private function locationScore(Candidate $candidate, Job $job): int
    {
        $candidateCity = strtolower(trim((string) $candidate->city));
        $jobLocation = strtolower(trim((string) $job->location));

        if ($candidateCity !== '' && $jobLocation !== '' && str_contains($jobLocation, $candidateCity)) {
            return 100;
        }
        if (strtolower((string) $candidate->country) === 'saudi arabia') {
            return 82;
        }

        return 58;
    }

    private function redFlags(Candidate $candidate, Job $job, array $missing, int $salaryFit, int $locationFit, int $experience): array
    {
        $flags = [];
        if ($candidate->email === null && $candidate->phone === null) {
            $flags[] = 'Missing contact information';
        }
        if (count($missing) > 0) {
            $flags[] = 'Missing required skills: '.implode(', ', array_slice($missing, 0, 4));
        }
        if ($salaryFit < 55) {
            $flags[] = 'Salary mismatch';
        }
        if ($locationFit < 70) {
            $flags[] = 'Location mismatch';
        }
        if ($experience < 55) {
            $flags[] = 'Underqualified experience level';
        }
        if ((int) $candidate->years_experience > max(3, (int) $job->required_experience * 2 + 4)) {
            $flags[] = 'Possibly overqualified';
        }
        if ($this->relationItems($candidate, 'experience')->count() >= 7) {
            $flags[] = 'Many job changes require timeline validation';
        }

        $requirements = strtolower((string) $job->requirements.' '.(string) $job->description);
        $candidateCerts = strtolower($this->relationItems($candidate, 'certifications')->pluck('name')->implode(' '));
        foreach (['pmp', 'nebosh', 'leed'] as $certification) {
            if (str_contains($requirements, $certification) && ! str_contains($candidateCerts, $certification)) {
                $flags[] = 'Missing required certification: '.strtoupper($certification);
            }
        }

        return array_values(array_unique($flags));
    }

    private function interviewQuestions(Candidate $candidate, Job $job, array $missing, array $riskIndicators): array
    {
        return [
            'technical' => [
                'Which tools and standards did you personally use in your most relevant recent project?',
                'Describe a complex problem related to '.$job->title.' and how you solved it.',
            ],
            'behavioral' => [
                'Tell us about a time you had to coordinate with a difficult stakeholder.',
                'How do you prioritize when multiple urgent tasks compete for attention?',
            ],
            'experience_verification' => [
                'Walk us through your role at '.($candidate->current_company ?: 'your current or latest company').'.',
                'Which achievements can be verified by references or project documents?',
            ],
            'salary_availability' => [
                'What is your expected salary range and how flexible is it?',
                'What is your confirmed notice period and earliest joining date?',
            ],
            'risk_validation' => array_values(array_map(
                fn ($flag) => 'Please clarify: '.$flag.'.',
                array_slice($riskIndicators ?: ['Validate the timeline, claims, and fit for this role'], 0, 4)
            )),
            'missing_skills' => array_values(array_map(
                fn ($skill) => 'How would you rate your hands-on experience with '.$skill.'?',
                array_slice($missing, 0, 4)
            )),
        ];
    }

    private function rankingReason(int $overall, int $technical, int $experience, int $salaryFit, array $missing): string
    {
        if ($overall >= 80) {
            return 'Strong overall fit with solid skills, experience, salary, and availability signals.';
        }
        if ($overall >= 60) {
            return 'Potential match, but recruiter should validate gaps before shortlisting.';
        }
        if ($technical < 50) {
            return 'Weak skills match; missing '.count($missing).' required requirement(s).';
        }
        if ($experience < 50) {
            return 'Experience level is below the job requirement.';
        }
        if ($salaryFit < 50) {
            return 'Salary expectation appears outside the job budget.';
        }

        return 'Insufficient evidence to recommend without additional recruiter review.';
    }

    private function relationItems(Candidate $candidate, string $relation)
    {
        return $candidate->relationLoaded($relation) ? $candidate->getRelation($relation) : collect();
    }

    private function jobRelationItems(Job $job, string $relation)
    {
        return $job->relationLoaded($relation) ? $job->getRelation($relation) : collect();
    }
}
