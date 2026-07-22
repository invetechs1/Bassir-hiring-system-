<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiInsightsService
{
    public function __construct(private readonly ApiCredentialService $credentials)
    {
    }

    public function candidateInsight(array $candidate, array $jobContext = []): array
    {
        $fallback = $this->fallbackInsight($candidate, $jobContext);
        $apiKey = $this->credentials->get('openai', 'OPENAI_API_KEY');
        if (empty($apiKey)) {
            return $fallback;
        }

        $prompt = $this->prompt($candidate, $jobContext);
        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('bassir.ai_timeout_seconds', 25))
                ->retry((int) config('bassir.ai_retry_attempts', 1), 300)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('bassir.openai_model', 'gpt-4o-mini'),
                    'input' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.2,
                ]);
        } catch (Throwable $e) {
            Log::warning('AI insight request failed', ['provider' => 'openai', 'error' => $e->getMessage()]);
            return $fallback;
        }

        if (! $response->ok()) {
            Log::warning('AI insight request returned non-OK response', ['provider' => 'openai', 'status' => $response->status()]);
            return $fallback;
        }

        $text = $response->json('output_text') ?: $this->extractOutputText($response->json('output', []));
        $decoded = json_decode((string) $text, true);
        if (! is_array($decoded)) {
            return $fallback;
        }

        return [
            'summary' => (string) ($decoded['summary'] ?? $fallback['summary']),
            'strength_points' => array_values(array_filter(array_map('strval', $decoded['strength_points'] ?? $fallback['strength_points']))),
            'weakness_points' => array_values(array_filter(array_map('strval', $decoded['weakness_points'] ?? $fallback['weakness_points']))),
            'best_roles' => array_values(array_filter(array_map('strval', $decoded['best_roles'] ?? $fallback['best_roles']))),
            'expected_salary' => (int) ($decoded['expected_salary'] ?? $fallback['expected_salary']),
            'hiring_recommendation' => (string) ($decoded['hiring_recommendation'] ?? $fallback['hiring_recommendation']),
            'interview_questions' => array_values(array_filter(array_map('strval', $decoded['interview_questions'] ?? $fallback['interview_questions']))),
            'risk_notes' => array_values(array_filter(array_map('strval', $decoded['risk_notes'] ?? $fallback['risk_notes']))),
            'missing_skills' => array_values(array_filter(array_map('strval', $decoded['missing_skills'] ?? $fallback['missing_skills']))),
            'matching_percentage' => max(0, min(100, (int) ($decoded['matching_percentage'] ?? $fallback['matching_percentage']))),
            'confidence' => max(0, min(100, (int) ($decoded['confidence'] ?? $fallback['confidence']))),
            'human_review_required' => true,
            'ai_disclaimer' => $fallback['ai_disclaimer'],
        ];
    }

    private function prompt(array $candidate, array $jobContext): string
    {
        $payload = [
            'candidate' => $candidate,
            'job_context' => $jobContext,
            'instruction' => 'Return strict JSON only.',
            'schema' => [
                'summary' => 'string',
                'strength_points' => ['string'],
                'weakness_points' => ['string'],
                'best_roles' => ['string'],
                'expected_salary' => 'integer',
                'hiring_recommendation' => 'Highly Recommended|Recommended|Maybe|Not Recommended',
                'interview_questions' => ['string'],
                'risk_notes' => ['string'],
                'missing_skills' => ['string'],
                'matching_percentage' => 'integer 0-100',
                'confidence' => 'integer 0-100 based on evidence completeness',
                'guardrail' => 'Do not use protected traits or discriminatory factors. Recommend human review.',
            ],
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function extractOutputText(array $output): string
    {
        $parts = [];
        foreach ($output as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && ! empty($content['text'])) {
                    $parts[] = $content['text'];
                }
            }
        }

        return implode("\n", $parts);
    }

    private function fallbackInsight(array $candidate, array $jobContext): array
    {
        $skills = array_values(array_filter(array_map('trim', $candidate['skills'] ?? [])));
        $required = array_map('strtolower', $jobContext['required_skills'] ?? []);
        $matched = [];
        $missing = [];
        foreach ($required as $skill) {
            $found = collect($skills)->contains(fn ($candidateSkill) => str_contains(strtolower($candidateSkill), $skill));
            if ($found) {
                $matched[] = $skill;
            } else {
                $missing[] = $skill;
            }
        }
        $percentage = empty($required) ? 72 : (int) round((count($matched) / max(1, count($required))) * 100);

        return [
            'summary' => trim(($candidate['full_name'] ?? 'Candidate').' appears suitable for '.($jobContext['title'] ?? 'the role').' based on current profile signals.'),
            'strength_points' => [
                'Relevant title and specialization alignment.',
                'Documented skills from imported profile/CV.',
            ],
            'weakness_points' => [
                empty($missing) ? 'No major skill gaps detected from available data.' : 'Some required skills are not explicitly proven in profile.',
            ],
            'best_roles' => [
                $jobContext['title'] ?? ($candidate['title'] ?? 'Related role'),
            ],
            'expected_salary' => (int) ($candidate['expected_salary'] ?? 0),
            'hiring_recommendation' => match (true) {
                $percentage >= 85 => 'Highly Recommended',
                $percentage >= 72 => 'Recommended',
                $percentage >= 55 => 'Maybe',
                default => 'Not Recommended',
            },
            'interview_questions' => [
                'Walk us through your most relevant recent project and your exact contribution.',
                'Which tools or standards did you personally use to deliver results?',
                'How quickly can you start, and what constraints might affect joining?',
            ],
            'risk_notes' => [
                'Profile may require direct validation of project claims and timeline.',
            ],
            'missing_skills' => $missing,
            'matching_percentage' => $percentage,
            'confidence' => empty($candidate['skills']) ? 55 : 72,
            'human_review_required' => true,
            'ai_disclaimer' => 'AI assists HR review only and must not be used as the sole hiring decision. Validate consent, facts, and legal compliance.',
        ];
    }
}
