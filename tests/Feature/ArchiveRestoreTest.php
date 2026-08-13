<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Job;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveRestoreTest extends TestCase
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

    public function test_candidate_can_be_archived_and_restored(): void
    {
        $candidate = Candidate::firstOrFail();

        $this->actingAs($this->owner)->delete("/candidates/{$candidate->id}")
            ->assertRedirect(route('candidates.index'));
        $this->assertSoftDeleted('candidates', ['id' => $candidate->id]);

        // Archived candidate is hidden from the default list but shown under ?archived=1.
        $this->actingAs($this->owner)->get('/candidates')->assertDontSee($candidate->full_name, false);
        $this->actingAs($this->owner)->get('/candidates?archived=1')->assertSee($candidate->full_name, false);

        $this->actingAs($this->owner)->post("/candidates/{$candidate->id}/restore")
            ->assertRedirect(route('candidates.show', $candidate));
        $this->assertDatabaseHas('candidates', ['id' => $candidate->id, 'deleted_at' => null]);
    }

    public function test_archived_candidate_is_excluded_from_default_queries(): void
    {
        $candidate = Candidate::firstOrFail();
        $candidate->delete();

        $this->assertNull(Candidate::find($candidate->id));
        $this->assertNotNull(Candidate::withTrashed()->find($candidate->id));
    }

    public function test_job_can_be_archived_and_restored(): void
    {
        $job = Job::firstOrFail();

        $this->actingAs($this->owner)->delete("/jobs/{$job->id}")
            ->assertRedirect(route('jobs.index'));
        $this->assertSoftDeleted('jobs', ['id' => $job->id]);

        $this->actingAs($this->owner)->post("/jobs/{$job->id}/restore")
            ->assertRedirect(route('jobs.show', $job));
        $this->assertDatabaseHas('jobs', ['id' => $job->id, 'deleted_at' => null]);
    }

    public function test_read_only_role_cannot_archive(): void
    {
        $viewer = User::where('username', 'hr.manager')->firstOrFail();
        $viewer->forceFill([
            'role_id' => Role::where('name', 'VIEWER')->value('id'),
            'must_change_password' => false,
        ])->save();

        $candidate = Candidate::firstOrFail();
        $this->actingAs($viewer)->delete("/candidates/{$candidate->id}")->assertForbidden();
        $this->assertDatabaseHas('candidates', ['id' => $candidate->id, 'deleted_at' => null]);
    }
}
