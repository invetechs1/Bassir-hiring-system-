<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Support\Collection;

class TalentSearchAssistantService
{
    public function __construct(private readonly TenantService $tenant)
    {
    }

    public function search(string $prompt, ?User $user, int $limit = 50): Collection
    {
        $prompt = trim($prompt);
        $normalized = strtolower($prompt);
        $filters = $this->filters($normalized);

        return $this->tenant->scope(Candidate::with(['skills', 'languages', 'education', 'scores']), $user)
            ->when($filters['city'], fn ($query, $city) => $query->where('city', 'like', "%{$city}%"))
            ->when($filters['specialization'], fn ($query, $specialization) => $query->where(function ($inner) use ($specialization) {
                $inner->where('specialization', 'like', "%{$specialization}%")
                    ->orWhere('title', 'like', "%{$specialization}%");
            }))
            ->when($filters['years_experience'], fn ($query, $years) => $query->where('years_experience', '>=', $years))
            ->when($filters['availability'], fn ($query, $availability) => $query->where(function ($inner) use ($availability) {
                $inner->where('availability', 'like', "%{$availability}%")
                    ->orWhere('notice_period', 'like', "%{$availability}%");
            }))
            ->when($filters['low_salary'], fn ($query) => $query->orderByRaw('expected_salary is null, expected_salary asc'))
            ->when($filters['skills'], fn ($query, $skills) => $query->whereHas('skills', function ($inner) use ($skills) {
                $inner->where(function ($skillQuery) use ($skills) {
                    foreach ($skills as $skill) {
                        $skillQuery->orWhere('name', 'like', "%{$skill}%");
                    }
                });
            }))
            ->orderByDesc('quality_score')
            ->orderByDesc('years_experience')
            ->take($limit)
            ->get()
            ->map(function (Candidate $candidate) use ($filters, $prompt) {
                $candidate->assistant_reason = $this->reason($candidate, $filters, $prompt);
                return $candidate;
            });
    }

    private function filters(string $prompt): array
    {
        $skills = [];
        foreach (['pmp', 'primavera', 'revit', 'autocad', 'excel', 'python', 'react', 'nebosh', 'qa/qc'] as $skill) {
            if (str_contains($prompt, $skill)) {
                $skills[] = $skill;
            }
        }

        preg_match('/(\d+)\s*(\+)?\s*(years|year|yrs|سنوات|سنة)/i', $prompt, $years);

        return [
            'city' => $this->firstMatch($prompt, ['riyadh', 'jeddah', 'dammam', 'khobar', 'mecca', 'medina']),
            'specialization' => $this->firstMatch($prompt, [
                'civil engineer',
                'project manager',
                'accountant',
                'architect',
                'mechanical',
                'electrical',
                'developer',
                'designer',
                'driver',
                'safety',
                'hse',
                'procurement',
            ]),
            'years_experience' => isset($years[1]) ? (int) $years[1] : null,
            'availability' => str_contains($prompt, 'immediate') ? 'Immediate' : null,
            'low_salary' => str_contains($prompt, 'low salary') || str_contains($prompt, 'budget') || str_contains($prompt, 'cheap'),
            'skills' => array_values(array_unique($skills)),
        ];
    }

    private function firstMatch(string $prompt, array $terms): ?string
    {
        foreach ($terms as $term) {
            if (str_contains($prompt, $term)) {
                return $term;
            }
        }

        return null;
    }

    private function reason(Candidate $candidate, array $filters, string $prompt): string
    {
        $reasons = [];
        if ($filters['specialization'] && str_contains(strtolower($candidate->title.' '.$candidate->specialization), $filters['specialization'])) {
            $reasons[] = 'title/specialization match';
        }
        if ($filters['city'] && str_contains(strtolower((string) $candidate->city), $filters['city'])) {
            $reasons[] = 'city match';
        }
        if ($filters['years_experience'] && (int) $candidate->years_experience >= (int) $filters['years_experience']) {
            $reasons[] = 'experience threshold met';
        }
        if ($filters['skills']) {
            $candidateSkills = strtolower($candidate->skills->pluck('name')->implode(' '));
            $matched = array_filter($filters['skills'], fn ($skill) => str_contains($candidateSkills, $skill));
            if ($matched) {
                $reasons[] = 'matched skills: '.implode(', ', $matched);
            }
        }

        return $reasons ? implode('; ', $reasons) : 'Relevant profile signals found for: '.$prompt;
    }
}
