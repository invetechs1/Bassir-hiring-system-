<?php

namespace Tests\Unit;

use App\Models\Candidate;
use App\Models\CandidateSkill;
use App\Models\Job;
use App\Models\JobRequiredSkill;
use App\Services\CandidateScoringService;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class CandidateScoringServiceTest extends TestCase
{
    public function test_candidate_score_contains_required_decision_support_fields(): void
    {
        $candidate = new Candidate([
            'full_name' => 'Aisha Al-Fahad',
            'title' => 'Senior BIM Engineer',
            'specialization' => 'BIM Engineers',
            'city' => 'Riyadh',
            'country' => 'Saudi Arabia',
            'years_experience' => 8,
            'expected_salary' => 21000,
            'availability' => '30 days',
        ]);
        $candidate->setRelation('skills', new Collection([
            new CandidateSkill(['name' => 'Revit']),
            new CandidateSkill(['name' => 'Navisworks']),
        ]));
        $candidate->setRelation('education', new Collection());
        $candidate->setRelation('certifications', new Collection());
        $candidate->setRelation('experience', new Collection());

        $job = new Job([
            'title' => 'Senior BIM Engineer',
            'location' => 'Riyadh',
            'required_experience' => 7,
            'salary_budget_min' => 18000,
            'salary_budget_max' => 24000,
        ]);
        $job->setRelation('requiredSkills', new Collection([
            new JobRequiredSkill(['name' => 'Revit']),
            new JobRequiredSkill(['name' => 'Navisworks']),
            new JobRequiredSkill(['name' => 'BIM 360']),
        ]));

        $score = (new CandidateScoringService())->score($candidate, $job);

        $this->assertArrayHasKey('overall', $score);
        $this->assertArrayHasKey('confidence', $score);
        $this->assertArrayHasKey('rationale', $score);
        $this->assertArrayHasKey('education', $score);
        $this->assertArrayHasKey('location_fit', $score);
        $this->assertArrayHasKey('notice_period_fit', $score);
        $this->assertArrayHasKey('risk_indicators', $score);
        $this->assertArrayHasKey('interview_questions', $score);
        $this->assertSame('rules-v2', $score['prompt_version']);
        $this->assertSame('80_PLUS', $score['ranking_band']);
        $this->assertContains('bim 360', $score['rationale']['missing_skills']);
        $this->assertTrue($score['rationale']['human_review_required']);
    }
}
