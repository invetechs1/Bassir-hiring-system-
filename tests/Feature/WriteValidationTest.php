<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the extracted Form Request validation for the write controllers.
 */
class WriteValidationTest extends TestCase
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

    public function test_candidate_create_requires_core_fields(): void
    {
        $before = Candidate::count();

        $this->actingAs($this->owner)->from('/candidates/create')
            ->post('/candidates', ['full_name' => '']) // missing full_name, title, specialization, consent
            ->assertRedirect('/candidates/create')
            ->assertSessionHasErrors(['full_name', 'title', 'specialization', 'consent_status']);

        $this->assertSame($before, Candidate::count());
    }

    public function test_candidate_create_rejects_duplicate_email_in_same_company(): void
    {
        $existing = Candidate::whereNotNull('email')->firstOrFail();

        $this->actingAs($this->owner)->from('/candidates/create')->post('/candidates', [
            'full_name' => 'Different Person',
            'email' => $existing->email,
            'title' => 'Engineer',
            'specialization' => 'BIM Engineers',
            'consent_status' => 'PENDING',
        ])->assertSessionHasErrors('email');
    }

    public function test_job_create_requires_core_fields(): void
    {
        $this->actingAs($this->owner)->from('/jobs/create')
            ->post('/jobs', ['title' => ''])
            ->assertRedirect('/jobs/create')
            ->assertSessionHasErrors(['title', 'department', 'company', 'location', 'description', 'hiring_manager', 'vacancies']);
    }

    public function test_valid_candidate_payload_still_passes_through_form_request(): void
    {
        $this->actingAs($this->owner)->post('/candidates', [
            'full_name' => 'Form Request Candidate',
            'email' => 'form.request@example.com',
            'title' => 'QA Engineer',
            'specialization' => 'QA/QC Engineers',
            'consent_status' => 'CONSENTED',
            'skills' => 'Testing; Automation',
        ])->assertRedirect();

        $this->assertDatabaseHas('candidates', ['email' => 'form.request@example.com']);
    }
}
