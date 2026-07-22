<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Job;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateJobEditTest extends TestCase
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

    public function test_candidate_edit_page_loads_and_update_persists(): void
    {
        $candidate = Candidate::firstOrFail();

        $this->actingAs($this->owner)->get("/candidates/{$candidate->id}/edit")
            ->assertOk()
            ->assertSee($candidate->full_name, false);

        $response = $this->actingAs($this->owner)->put("/candidates/{$candidate->id}", [
            'full_name' => 'Aisha Al-Fahad Updated',
            'email' => $candidate->email,
            'title' => 'Lead BIM Engineer',
            'specialization' => 'BIM Engineers',
            'country' => 'Saudi Arabia',
            'city' => 'Jeddah',
            'years_experience' => 9,
            'consent_status' => 'CONSENTED',
            'status' => 'SHORTLISTED',
            'skills' => 'Revit; Navisworks; Synchro',
            'languages' => 'Arabic; English',
        ]);

        $response->assertRedirect(route('candidates.show', $candidate));
        $candidate->refresh();
        $this->assertSame('Aisha Al-Fahad Updated', $candidate->full_name);
        $this->assertSame('Lead BIM Engineer', $candidate->title);
        $this->assertSame('Jeddah', $candidate->city);
        // Skills were replaced, not duplicated.
        $skills = $candidate->skills()->pluck('name')->all();
        $this->assertContains('Synchro', $skills);
        $this->assertSame(count($skills), count(array_unique($skills)));
    }

    public function test_candidate_update_keeps_own_email_unique_rule(): void
    {
        $candidate = Candidate::firstOrFail();

        // Updating without changing the email must not trip the tenant-unique rule on itself.
        $this->actingAs($this->owner)->put("/candidates/{$candidate->id}", [
            'full_name' => $candidate->full_name,
            'email' => $candidate->email,
            'title' => $candidate->title,
            'specialization' => $candidate->specialization,
            'consent_status' => $candidate->consent_status,
        ])->assertRedirect(route('candidates.show', $candidate));
    }

    public function test_job_edit_page_loads_and_update_persists(): void
    {
        $job = Job::firstOrFail();

        $this->actingAs($this->owner)->get("/jobs/{$job->id}/edit")
            ->assertOk()
            ->assertSee($job->title, false);

        $response = $this->actingAs($this->owner)->put("/jobs/{$job->id}", [
            'title' => 'Lead BIM Engineer',
            'specialization' => 'BIM Engineers',
            'department' => 'Engineering',
            'company' => 'Bassir Client',
            'location' => 'Riyadh',
            'required_experience' => 8,
            'salary_budget_min' => 20000,
            'salary_budget_max' => 27000,
            'description' => 'Lead BIM coordination for a giga-project.',
            'requirements' => 'ISO 19650, Revit, Navisworks.',
            'approval_status' => 'APPROVED',
            'hiring_manager' => 'Noura Salem',
            'vacancies' => 3,
            'required_skills' => 'Revit; Navisworks; ISO 19650',
        ]);

        $response->assertRedirect(route('jobs.show', $job));
        $job->refresh();
        $this->assertSame('Lead BIM Engineer', $job->title);
        $this->assertSame(3, (int) $job->vacancies);
        $this->assertContains('ISO 19650', $job->requiredSkills()->pluck('name')->all());
    }

    public function test_viewer_role_cannot_edit(): void
    {
        $viewer = User::where('username', 'hr.manager')->first();
        // Demote to a read-only viewer role for this check.
        $viewerRole = \App\Models\Role::where('name', 'VIEWER')->firstOrFail();
        $viewer->forceFill(['role_id' => $viewerRole->id, 'must_change_password' => false])->save();

        $candidate = Candidate::firstOrFail();
        $this->actingAs($viewer)->get("/candidates/{$candidate->id}/edit")->assertForbidden();
    }
}
