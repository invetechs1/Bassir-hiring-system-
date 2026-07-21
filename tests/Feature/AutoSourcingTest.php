<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\SourcingRun;
use App\Models\SourcingSearch;
use App\Models\User;
use App\Services\AutoSourcingService;
use App\Services\Connectors\PlatformConnectorRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutoSourcingTest extends TestCase
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

    private function makeSearch(array $overrides = []): SourcingSearch
    {
        return SourcingSearch::create(array_merge([
            'company_id' => $this->owner->company_id,
            'name' => 'BIM Engineers Riyadh',
            'job_title' => 'Senior BIM Engineer',
            'specialization' => 'BIM Engineers',
            'country' => 'Saudi Arabia',
            'city' => 'Riyadh',
            'quantity' => 25,
            'download_cvs' => false,
            'auto_import' => true,
            'default_consent_status' => 'PENDING',
            'frequency' => 'daily',
            'is_active' => true,
            'created_by' => $this->owner->id,
        ], $overrides));
    }

    private function makeRun(SourcingSearch $search): SourcingRun
    {
        return SourcingRun::create([
            'company_id' => $search->company_id,
            'sourcing_search_id' => $search->id,
            'status' => 'RUNNING',
            'started_at' => now(),
        ]);
    }

    public function test_page_loads_and_search_can_be_saved(): void
    {
        $this->actingAs($this->owner)->get('/auto-sourcing')->assertOk()->assertSee('Automated Web Sourcing');

        $this->actingAs($this->owner)->post('/auto-sourcing', [
            'name' => 'Backend Devs',
            'job_title' => 'Backend Developer',
            'specialization' => 'Software Developers',
            'city' => 'Riyadh',
            'skills' => 'PHP, Laravel',
            'quantity' => 20,
            'frequency' => 'daily',
            'default_consent_status' => 'PENDING',
            'download_cvs' => 1,
            'auto_import' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('sourcing_searches', ['name' => 'Backend Devs', 'company_id' => $this->owner->company_id]);
    }

    public function test_allowed_result_is_imported_as_pending_lead_and_linkedin_is_flagged_not_scraped(): void
    {
        $search = $this->makeSearch();
        $run = $this->makeRun($search);
        $engine = app(AutoSourcingService::class);

        $results = [
            ['source' => 'Google Custom Search API', 'title' => 'Ahmed Ali — Senior BIM Engineer | CV',
             'url' => 'https://example.com/resumes/ahmed-ali.html', 'snippet' => 'BIM engineer, Riyadh',
             'file_type' => 'profile', 'compliance_status' => 'allowed'],
            ['source' => 'Bing Search API', 'title' => 'Sara Q — Profile',
             'url' => 'https://www.linkedin.com/in/sara-q', 'snippet' => 'BIM coordinator',
             'file_type' => 'profile', 'compliance_status' => 'manual_only'],
        ];

        $engine->importResults($search, $results, $run, $this->owner);
        $run->refresh();

        // Allowed web result becomes a consent-pending lead that cannot be contacted.
        $ahmed = Candidate::where('company_id', $search->company_id)->where('full_name', 'Ahmed Ali')->first();
        $this->assertNotNull($ahmed);
        $this->assertSame('PENDING', $ahmed->consent_status);
        $this->assertNotSame(true, (bool) $ahmed->contact_allowed);
        $this->assertSame(1, $run->candidates_created);

        // LinkedIn public-web result is flagged for manual review, never auto-imported.
        $this->assertSame(1, $run->flagged_manual);
        $this->assertDatabaseMissing('candidates', ['full_name' => 'Sara Q']);

        // Both raw results are still recorded for manual review in AI Search history.
        $this->assertDatabaseCount('ai_search_results', 2);
    }

    public function test_duplicate_results_link_instead_of_creating_new_candidates(): void
    {
        $search = $this->makeSearch();
        $engine = app(AutoSourcingService::class);

        $result = [['source' => 'SerpAPI', 'title' => 'Mona Zaid — Structural Engineer',
            'url' => 'https://example.com/cv/mona.html', 'snippet' => 'x', 'file_type' => 'profile', 'compliance_status' => 'allowed']];

        $run1 = $this->makeRun($search);
        $engine->importResults($search, $result, $run1, $this->owner);
        $run2 = $this->makeRun($search);
        $engine->importResults($search, $result, $run2, $this->owner);

        $this->assertSame(1, Candidate::where('full_name', 'Mona Zaid')->count());
        $this->assertSame(1, $run1->refresh()->candidates_created);
        $this->assertSame(1, $run2->refresh()->candidates_linked);
        $this->assertSame(0, $run2->candidates_created);
    }

    public function test_public_cv_file_is_downloaded_and_parsed(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://files.example.com/*' => Http::response(
                "Khalid Omar\nkhalid.omar@example.com\n+966500001234\nSenior BIM Engineer\nSkills: Revit BIM 360\nRiyadh, Saudi Arabia",
                200,
                ['Content-Type' => 'application/pdf']
            ),
        ]);

        $search = $this->makeSearch(['download_cvs' => true]);
        $run = $this->makeRun($search);
        $engine = app(AutoSourcingService::class);

        $results = [['source' => 'Google Custom Search API', 'title' => 'Khalid Omar CV',
            'url' => 'https://files.example.com/cv/khalid.pdf', 'snippet' => 'BIM',
            'file_type' => 'pdf', 'compliance_status' => 'allowed']];

        $engine->importResults($search, $results, $run, $this->owner);
        $run->refresh();

        $candidate = Candidate::where('full_name', 'Khalid Omar')->first();
        $this->assertNotNull($candidate);
        $this->assertSame('khalid.omar@example.com', $candidate->email);
        $this->assertSame(1, $run->cvs_downloaded);
        $this->assertDatabaseHas('candidate_documents', ['candidate_id' => $candidate->id, 'scan_status' => 'AUTO_SOURCED']);
    }

    public function test_platform_connectors_report_unconfigured_by_default(): void
    {
        $statuses = app(PlatformConnectorRegistry::class)->statuses();
        $keys = array_column($statuses, 'key');

        $this->assertContains('linkedin_talent', $keys);
        $this->assertContains('indeed_partner', $keys);
        foreach ($statuses as $status) {
            $this->assertFalse($status['configured']); // no API tokens set in the test env
        }
    }

    public function test_saving_connector_keys_via_integrations_ui_activates_the_connector(): void
    {
        $linkedin = fn () => collect(app(PlatformConnectorRegistry::class)->statuses())
            ->firstWhere('key', 'linkedin_talent')['configured'];

        $this->assertFalse($linkedin());

        // Save the token + endpoint through the encrypted Integrations admin page.
        $this->actingAs($this->owner)->post('/integrations', [
            'provider' => 'linkedin_talent', 'value' => 'partner-oauth-token', 'status' => 'ACTIVE',
        ])->assertRedirect();
        $this->actingAs($this->owner)->post('/integrations', [
            'provider' => 'linkedin_talent_endpoint', 'value' => 'https://api.linkedin.example/talent/search', 'status' => 'ACTIVE',
        ])->assertRedirect();

        // The connector now reports connected — no code change, purely UI-configured.
        $this->assertTrue($linkedin());
    }
}
