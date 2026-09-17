<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateLanguage;
use App\Models\CandidateSkill;
use App\Models\SourcingAgent;
use App\Models\User;
use App\Services\SourcingAgentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourcingAgentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = User::where('username', 'yahya')->firstOrFail();
        $this->owner->forceFill(['must_change_password' => false])->save();
    }

    public function test_index_page_loads_when_empty(): void
    {
        $this->actingAs($this->owner)->get('/sourcing-agents')->assertOk()->assertSee('Sourcing Agents');
    }

    public function test_can_hire_an_agent_via_form_post(): void
    {
        $this->actingAs($this->owner)
            ->post('/sourcing-agents', [
                'name' => 'Sarah — Civil Engineering Recruiter',
                'persona' => 'senior_technical',
                'avatar_emoji' => '🧑‍💼',
                'specialty_slug' => 'civil-engineer',
                'countries_csv' => 'Saudi Arabia',
                'cities_csv' => 'Riyadh, Jeddah',
                'must_csv' => 'AutoCAD, Revit',
                'nice_csv' => 'Primavera P6',
                'languages_csv' => 'Arabic, English',
                'min_years' => 3,
                'max_years' => 15,
                'min_score' => 50,
                'quantity_per_run' => 20,
                'frequency' => 'daily',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sourcing_agents', [
            'name' => 'Sarah — Civil Engineering Recruiter',
            'specialty_slug' => 'civil-engineer',
            'specialty_name' => 'Civil Engineer',
        ]);
    }

    public function test_scoring_rewards_matching_skills_years_and_location(): void
    {
        $service = app(SourcingAgentService::class);
        $candidate = Candidate::query()->first();
        $candidate->update([
            'city' => 'Riyadh',
            'country' => 'Saudi Arabia',
            'years_experience' => 7,
        ]);
        $candidate->skills()->delete();
        $candidate->languages()->delete();
        foreach (['AutoCAD', 'Revit', 'Primavera P6'] as $skill) {
            CandidateSkill::create(['candidate_id' => $candidate->id, 'name' => $skill]);
        }
        CandidateLanguage::create(['candidate_id' => $candidate->id, 'name' => 'Arabic']);

        $agent = SourcingAgent::create([
            'company_id' => $candidate->company_id,
            'name' => 'Test Agent',
            'persona' => 'senior_technical',
            'avatar_emoji' => '🧑‍💼',
            'specialty_slug' => 'civil-engineer',
            'specialty_name' => 'Civil Engineer',
            'countries' => ['Saudi Arabia'],
            'cities' => ['Riyadh'],
            'must_have_skills' => ['AutoCAD', 'Revit'],
            'nice_to_have_skills' => ['Primavera P6'],
            'languages' => ['Arabic'],
            'min_years' => 3,
            'max_years' => 15,
            'min_score' => 50,
            'quantity_per_run' => 5,
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $score = $service->scoreCandidate($candidate->fresh('skills', 'languages'), $agent);
        $this->assertGreaterThanOrEqual(90, $score['total']);
    }

    public function test_scoring_penalises_missing_must_haves_and_wrong_location(): void
    {
        $service = app(SourcingAgentService::class);
        $candidate = Candidate::query()->first();
        $candidate->update([
            'city' => 'Cairo',
            'country' => 'Egypt',
            'years_experience' => 1,
        ]);
        $candidate->skills()->delete();

        $agent = SourcingAgent::create([
            'company_id' => $candidate->company_id,
            'name' => 'Strict Agent',
            'persona' => 'executive_headhunter',
            'avatar_emoji' => '🎯',
            'specialty_slug' => 'civil-engineer',
            'specialty_name' => 'Civil Engineer',
            'countries' => ['Saudi Arabia'],
            'cities' => ['Riyadh'],
            'must_have_skills' => ['AutoCAD', 'Revit', 'ETABS'],
            'nice_to_have_skills' => [],
            'languages' => ['Arabic'],
            'min_years' => 5,
            'max_years' => 15,
            'min_score' => 70,
            'quantity_per_run' => 5,
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $score = $service->scoreCandidate($candidate->fresh('skills', 'languages'), $agent);
        // Zero must-haves, zero languages, wrong country, years below min → should stay far under threshold.
        $this->assertLessThan(50, $score['total']);
    }

    public function test_is_due_respects_frequency_and_next_run_at(): void
    {
        $agent = SourcingAgent::create([
            'company_id' => 1,
            'name' => 'Scheduled Agent',
            'persona' => 'volume_recruiter',
            'avatar_emoji' => '📥',
            'specialty_slug' => 'software-engineer',
            'specialty_name' => 'Software Engineer',
            'must_have_skills' => [],
            'min_years' => 0, 'max_years' => 30, 'min_score' => 40,
            'quantity_per_run' => 10, 'frequency' => 'manual', 'is_active' => true,
        ]);
        $this->assertFalse($agent->isDue(), 'manual frequency is never due automatically');

        $agent->update(['frequency' => 'daily', 'next_run_at' => now()->subHour()]);
        $this->assertTrue($agent->fresh()->isDue());

        $agent->update(['next_run_at' => now()->addDay()]);
        $this->assertFalse($agent->fresh()->isDue());
    }
}
