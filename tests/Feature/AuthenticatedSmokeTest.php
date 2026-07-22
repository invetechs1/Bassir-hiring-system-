<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Job;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * End-to-end authenticated smoke coverage for go-live readiness.
 *
 * Seeds the production role/permission matrix and demo data, then drives every
 * major authenticated page and the core write workflows as a SUPER_ADMIN to
 * ensure no route returns a 4xx/5xx error before launch.
 */
class AuthenticatedSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->owner = User::where('username', 'yahya')->firstOrFail();
        // Clear the forced-password-change gate so protected pages are reachable.
        $this->owner->forceFill(['must_change_password' => false])->save();
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function authenticatedPages(): array
    {
        return [
            ['/dashboard'],
            ['/candidates'],
            ['/candidates/create'],
            ['/candidate-comparison'],
            ['/search-assistant'],
            ['/applications'],
            ['/jobs'],
            ['/jobs/create'],
            ['/talent-pools'],
            ['/interviews'],
            ['/interviews/create'],
            ['/specializations'],
            ['/specializations/create'],
            ['/salary-benchmarks'],
            ['/ai-search'],
            ['/upload-cv'],
            ['/integrations'],
            ['/users'],
            ['/audit-logs'],
            ['/settings/profile'],
            ['/reports'],
        ];
    }

    #[DataProvider('authenticatedPages')]
    public function test_authenticated_pages_load_without_error(string $path): void
    {
        $this->actingAs($this->owner)
            ->get($path)
            ->assertOk();
    }

    public function test_record_scoped_pages_load(): void
    {
        $candidate = Candidate::firstOrFail();
        $job = Job::firstOrFail();

        $this->actingAs($this->owner)->get("/candidates/{$candidate->id}")->assertOk();
        $this->actingAs($this->owner)->get("/candidates/{$candidate->id}/job-matches")->assertOk();
        $this->actingAs($this->owner)->get("/jobs/{$job->id}")->assertOk();
        $this->actingAs($this->owner)->get("/jobs/{$job->id}/ranking")->assertOk();
    }

    public function test_report_exports_stream_csv(): void
    {
        $exports = [
            '/reports/candidates.csv',
            '/reports/sources.csv',
            '/reports/interviews.csv',
            '/reports/salary-benchmarks.csv',
            '/reports/ai-search-success.csv',
        ];

        foreach ($exports as $export) {
            $this->actingAs($this->owner)->get($export)->assertOk();
        }
    }

    public function test_owner_can_create_candidate(): void
    {
        $response = $this->actingAs($this->owner)->post('/candidates', [
            'full_name' => 'Test Candidate',
            'email' => 'test.candidate@example.com',
            'phone' => '+966500009999',
            'title' => 'Software Developer',
            'specialization' => 'Software Developers',
            'country' => 'Saudi Arabia',
            'city' => 'Riyadh',
            'years_experience' => 5,
            'consent_status' => 'CONSENTED',
            'status' => 'NEW',
            'skills' => 'PHP, Laravel',
            'languages' => 'Arabic, English',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('candidates', ['email' => 'test.candidate@example.com']);
    }

    public function test_owner_can_create_job(): void
    {
        $response = $this->actingAs($this->owner)->post('/jobs', [
            'title' => 'Backend Engineer',
            'specialization' => 'Software Developers',
            'department' => 'Engineering',
            'company' => 'Bassir Client',
            'location' => 'Riyadh',
            'required_experience' => 4,
            'salary_budget_min' => 15000,
            'salary_budget_max' => 22000,
            'description' => 'Build and maintain backend services.',
            'requirements' => 'PHP, Laravel, MySQL.',
            'approval_status' => 'APPROVED',
            'hiring_manager' => 'Noura Salem',
            'vacancies' => 1,
            'required_skills' => 'PHP, Laravel, MySQL',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('jobs', ['title' => 'Backend Engineer']);
    }

    public function test_owner_can_create_specialization(): void
    {
        $response = $this->actingAs($this->owner)->post('/specializations', [
            'name' => 'DevOps Engineers',
            'category' => 'Technology',
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('specializations', ['name' => 'DevOps Engineers']);
    }

    public function test_health_endpoint_reports_ok(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertJson(['app' => 'ok', 'database' => 'ok']);
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        // Unauthenticated users are sent to the login route, which is served at '/'.
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
