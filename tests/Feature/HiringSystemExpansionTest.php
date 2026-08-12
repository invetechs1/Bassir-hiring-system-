<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Candidate;
use App\Models\CandidateAccount;
use App\Models\CandidateApplication;
use App\Models\Interview;
use App\Models\Job;
use App\Models\Offer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HiringSystemExpansionTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->staff = User::where('username', 'yahya')->firstOrFail();
        $this->staff->forceFill(['must_change_password' => false])->save();
        $this->job = Job::where('title', 'Senior BIM Engineer')->firstOrFail();
        Mail::fake();
    }

    public function test_candidate_can_register_browse_and_apply_via_the_portal(): void
    {
        $company = $this->job->tenantCompany;

        $this->get("/careers/{$company->slug}")->assertOk()->assertSee($this->job->title);

        $this->post("/careers/{$company->slug}/register", [
            'full_name' => 'Portal Candidate',
            'email' => 'portal.candidate@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertDatabaseHas('candidate_accounts', ['email' => 'portal.candidate@example.com', 'company_id' => $company->id]);
        $account = CandidateAccount::where('email', 'portal.candidate@example.com')->firstOrFail();

        $this->actingAs($account, 'candidate')
            ->post("/careers/{$company->slug}/jobs/{$this->job->public_slug}/apply")
            ->assertRedirect();

        $application = CandidateApplication::where('candidate_id', $account->candidate_id)->firstOrFail();
        $this->assertSame('APPLIED', $application->current_stage);

        $this->actingAs($account, 'candidate')
            ->get('/portal/dashboard')
            ->assertOk()
            ->assertSee($this->job->title);
    }

    public function test_unauthenticated_candidate_route_redirects_to_company_login_not_staff_login(): void
    {
        $account = $this->makeCandidateAccount();

        $response = $this->get('/portal/dashboard');
        // No session context yet — falls back to home, not the staff /login page's guard.
        $response->assertRedirect();

        $this->withSession(['portal_company_slug' => $account->company->slug])
            ->get('/portal/dashboard')
            ->assertRedirect(route('portal.login', $account->company->slug));
    }

    public function test_application_stage_change_to_rejected_sends_notification_and_logs_communication(): void
    {
        $account = $this->makeCandidateAccount();
        $application = $this->makeApplication($account->candidate);

        $this->actingAs($this->staff)->patch("/applications/{$application->id}/stage", [
            'current_stage' => 'REJECTED',
            'rejection_reason' => 'Not a fit',
        ])->assertRedirect();

        $this->assertDatabaseHas('communications', [
            'candidate_id' => $account->candidate_id,
            'template' => 'rejection',
        ]);
    }

    public function test_interview_creation_sends_invite_and_logs_communication(): void
    {
        $account = $this->makeCandidateAccount();

        $this->actingAs($this->staff)->post('/interviews', [
            'candidate_id' => $account->candidate_id,
            'job_id' => $this->job->id,
            'starts_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'channel' => 'Zoom',
            'status' => 'SCHEDULED',
        ])->assertRedirect();

        $this->assertDatabaseHas('communications', [
            'candidate_id' => $account->candidate_id,
            'template' => 'interview_invite',
        ]);
        $this->assertDatabaseHas('interviews', ['candidate_id' => $account->candidate_id, 'job_id' => $this->job->id]);
    }

    public function test_assessment_lifecycle_assign_take_autoscore(): void
    {
        $account = $this->makeCandidateAccount();
        $application = $this->makeApplication($account->candidate);

        $this->actingAs($this->staff)->post('/assessments', [
            'type' => 'TEST',
            'title' => 'Quick Quiz',
            'questions' => [
                ['question_text' => '2+2?', 'question_type' => 'MCQ', 'options' => '3,4,5', 'correct_answer' => '4', 'points' => 1],
            ],
        ])->assertRedirect();

        $assessment = Assessment::where('title', 'Quick Quiz')->firstOrFail();

        $this->actingAs($this->staff)->post("/assessments/{$assessment->id}/assign", [
            'candidate_application_id' => $application->id,
        ])->assertRedirect();

        $candidateAssessment = $assessment->candidateAssessments()->firstOrFail();
        $this->assertSame('PENDING', $candidateAssessment->status);

        $question = $assessment->questions()->firstOrFail();
        $this->actingAs($account, 'candidate')
            ->post("/portal/assessments/{$candidateAssessment->id}/submit", [
                'answers' => [$question->id => '4'],
            ])->assertRedirect();

        $candidateAssessment->refresh();
        $this->assertSame('SCORED', $candidateAssessment->status);
        $this->assertSame(1, $candidateAssessment->score);
    }

    public function test_offer_approval_send_accept_creates_onboarding_with_tasks(): void
    {
        $account = $this->makeCandidateAccount();
        $application = $this->makeApplication($account->candidate);

        $this->actingAs($this->staff)->post("/applications/{$application->id}/offer", [
            'salary_amount' => 18000,
            'currency' => 'SAR',
            'start_date' => now()->addMonth()->toDateString(),
            'employment_type' => 'Full-time',
            'terms' => 'Standard terms',
        ])->assertRedirect();

        $offer = Offer::where('candidate_application_id', $application->id)->firstOrFail();
        $this->assertSame('PENDING_APPROVAL', $offer->status);

        $this->actingAs($this->staff)->post("/offers/{$offer->id}/approve")->assertRedirect();
        $this->assertSame('APPROVED', $offer->refresh()->status);

        $this->actingAs($this->staff)->post("/offers/{$offer->id}/send")->assertRedirect();
        $offer->refresh();
        $this->assertSame('SENT', $offer->status);
        $this->assertSame('OFFER_SENT', $application->refresh()->current_stage);

        $this->actingAs($account, 'candidate')->post("/portal/offers/{$offer->id}/accept")->assertRedirect();

        $offer->refresh();
        $this->assertSame('ACCEPTED', $offer->status);
        $this->assertSame('HIRED', $application->refresh()->current_stage);

        $this->assertDatabaseHas('onboarding_records', ['offer_id' => $offer->id, 'status' => 'IN_PROGRESS']);
        $onboarding = $offer->onboardingRecord;
        $this->assertSame(4, $onboarding->tasks()->count());

        $task = $onboarding->tasks()->firstOrFail();
        $this->actingAs($this->staff)
            ->post("/onboarding/{$onboarding->id}/tasks/{$task->id}/complete")
            ->assertRedirect();
        $this->assertSame('COMPLETED', $task->refresh()->status);
    }

    public function test_bias_monitoring_suppresses_small_groups(): void
    {
        $this->makeApplication($this->makeCandidateAccount()->candidate);

        $response = $this->actingAs($this->staff)->get('/bias-monitoring');

        $response->assertOk();
        $response->assertSee('Other (small groups)');
    }

    public function test_offer_cannot_be_sent_before_approval(): void
    {
        $account = $this->makeCandidateAccount();
        $application = $this->makeApplication($account->candidate);

        $this->actingAs($this->staff)->post("/applications/{$application->id}/offer", [
            'salary_amount' => 15000,
        ])->assertRedirect();
        $offer = Offer::where('candidate_application_id', $application->id)->firstOrFail();

        $this->actingAs($this->staff)->post("/offers/{$offer->id}/send")->assertSessionHasErrors('offer');
        $this->assertSame('PENDING_APPROVAL', $offer->refresh()->status);
    }

    private function makeCandidateAccount(): CandidateAccount
    {
        $candidate = Candidate::create([
            'company_id' => $this->staff->company_id,
            'full_name' => 'Test Candidate '.uniqid(),
            'email' => 'candidate'.uniqid().'@example.com',
            'title' => 'Candidate',
            'specialization' => 'General',
            'consent_status' => 'CONSENTED',
            'contact_allowed' => true,
        ]);

        return CandidateAccount::create([
            'company_id' => $this->staff->company_id,
            'candidate_id' => $candidate->id,
            'email' => $candidate->email,
            'password' => bcrypt('Password123'),
        ]);
    }

    private function makeApplication(Candidate $candidate): CandidateApplication
    {
        return CandidateApplication::create([
            'company_id' => $this->staff->company_id,
            'candidate_id' => $candidate->id,
            'job_id' => $this->job->id,
            'source' => 'Test',
            'current_stage' => 'APPLIED',
            'status' => 'ACTIVE',
        ]);
    }
}
