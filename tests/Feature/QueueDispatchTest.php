<?php

namespace Tests\Feature;

use App\Jobs\RankJobCandidates;
use App\Jobs\RunSourcingSearch;
use App\Models\CandidateScore;
use App\Models\Job;
use App\Models\SourcingRun;
use App\Models\SourcingSearch;
use App\Models\User;
use App\Services\AutoSourcingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueDispatchTest extends TestCase
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

    private function jobPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Queued Role',
            'specialization' => 'BIM Engineers',
            'department' => 'Engineering',
            'company' => 'Bassir Client',
            'location' => 'Riyadh',
            'required_experience' => 5,
            'salary_budget_min' => 15000,
            'salary_budget_max' => 22000,
            'description' => 'Ranking should be dispatched to the queue.',
            'approval_status' => 'APPROVED',
            'hiring_manager' => 'Noura Salem',
            'vacancies' => 1,
            'required_skills' => 'Revit; Navisworks',
        ], $overrides);
    }

    public function test_creating_a_job_dispatches_ranking_to_the_queue(): void
    {
        Queue::fake();

        $this->actingAs($this->owner)->post('/jobs', $this->jobPayload())->assertRedirect();

        Queue::assertPushed(RankJobCandidates::class);
    }

    public function test_updating_a_job_dispatches_ranking_to_the_queue(): void
    {
        $job = Job::firstOrFail();
        Queue::fake();

        $this->actingAs($this->owner)->put("/jobs/{$job->id}", $this->jobPayload(['title' => 'Updated Role']))
            ->assertRedirect();

        Queue::assertPushed(RankJobCandidates::class, fn (RankJobCandidates $j) => $j->jobId === $job->id);
    }

    public function test_rank_job_candidates_handle_produces_scores(): void
    {
        $job = Job::firstOrFail();
        CandidateScore::where('job_id', $job->id)->delete();

        (new RankJobCandidates($job->id))->handle(app(\App\Services\AiCandidateRankingService::class));

        $this->assertTrue(CandidateScore::where('job_id', $job->id)->exists());
    }

    public function test_run_due_searches_dispatches_one_job_per_active_search(): void
    {
        SourcingSearch::create([
            'company_id' => $this->owner->company_id,
            'name' => 'Active A', 'specialization' => 'BIM Engineers',
            'frequency' => 'daily', 'is_active' => true, 'default_consent_status' => 'PENDING',
        ]);
        SourcingSearch::create([
            'company_id' => $this->owner->company_id,
            'name' => 'Manual B', 'specialization' => 'BIM Engineers',
            'frequency' => 'manual', 'is_active' => true, 'default_consent_status' => 'PENDING',
        ]);

        Queue::fake();
        $queued = app(AutoSourcingService::class)->runDueSearches();

        $this->assertSame(1, $queued); // only the active, non-manual search
        Queue::assertPushed(RunSourcingSearch::class, 1);
    }

    public function test_run_sourcing_search_handle_records_a_run(): void
    {
        $search = SourcingSearch::create([
            'company_id' => $this->owner->company_id,
            'name' => 'Handle Test', 'specialization' => 'BIM Engineers',
            'frequency' => 'daily', 'is_active' => true, 'default_consent_status' => 'PENDING',
        ]);

        (new RunSourcingSearch($search->id))->handle(app(AutoSourcingService::class));

        // No providers configured in tests → a completed run with zero results is logged.
        $this->assertDatabaseHas('sourcing_runs', ['sourcing_search_id' => $search->id, 'status' => 'SUCCESS']);
    }
}
