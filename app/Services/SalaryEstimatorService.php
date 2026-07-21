<?php

namespace App\Services;

class SalaryEstimatorService
{
    public function estimate(array $input): array
    {
        $base = isset($input['benchmark_min'], $input['benchmark_max'])
            ? (((float) $input['benchmark_min'] + (float) $input['benchmark_max']) / 2)
            : 9000;
        $seniority = min(2.2, 1 + ((float) ($input['years_experience'] ?? 0) * .06));
        $gcc = ! empty($input['gcc_experience']) ? 1.12 : 1;
        $skills = array_map('strtolower', $input['skills'] ?? []);
        $premium = count(array_intersect($skills, ['revit', 'primavera p6', 'openai api', 'react', 'bim 360', 'etabs']));
        $expected = (int) round($base * $seniority * $gcc * (1 + $premium * .035));

        return [
            'expected_monthly_salary' => $expected,
            'minimum_fair_salary' => (int) round($expected * .85),
            'maximum_fair_salary' => (int) round($expected * 1.18),
            'market_comparison' => $expected > $base ? 'Above benchmark due to seniority or scarce skills' : 'Within benchmark',
            'negotiation_recommendation' => $expected > $base * 1.25
                ? 'Negotiate using benefits, project scope, and phased increments.'
                : 'Offer near midpoint with performance review clause.',
        ];
    }
}
