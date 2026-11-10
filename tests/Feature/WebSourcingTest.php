<?php

namespace Tests\Feature;

use App\Models\AiSearchJob;
use App\Models\Candidate;
use App\Models\SourcingRun;
use App\Models\SourcingSearch;
use App\Models\User;
use App\Services\ApiCredentialService;
use App\Services\AutoSourcingService;
use App\Services\SearchProviderService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebSourcingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private array $filters = [
        'job_title' => 'Senior BIM Engineer',
        'specialization' => 'BIM Engineers',
        'country' => 'Saudi Arabia',
        'city' => 'Riyadh',
        'quantity' => 25,
        'skills' => ['BIM coordination'],
        'software_skills' => ['Revit', 'Navisworks'],
        'languages' => ['Arabic', 'English'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = User::where('username', 'yahya')->firstOrFail();
        $this->owner->forceFill(['must_change_password' => false])->save();
        $this->useSearchProvider(true);
    }

    /**
     * Pin API credentials so the tests behave the same on a laptop whose .env holds real keys
     * (and never reach the real OpenAI / search endpoints).
     */
    private function useSearchProvider(bool $enabled): void
    {
        $this->app->bind(ApiCredentialService::class, fn () => new class($enabled) extends ApiCredentialService {
            public function __construct(private bool $serp)
            {
            }

            public function get(string $provider, ?string $envFallback = null): ?string
            {
                return $provider === 'serpapi' && $this->serp ? 'test-key' : null;
            }
        });
    }

    private function fakeSearchResults(): void
    {
        Http::fake([
            'serpapi.com/*' => Http::response(['organic_results' => [
                ['title' => 'BIM Engineer jobs in Riyadh', 'link' => 'https://www.bayt.com/en/saudi-arabia/jobs/bim-engineer-123/', 'snippet' => 'Apply now for BIM Engineer jobs in Riyadh.'],
                ['title' => 'Hiring: BIM Engineer - Apply now', 'link' => 'https://acme-contracting.com/careers/bim-engineer', 'snippet' => 'We are hiring a BIM Engineer. Job description and responsibilities.'],
                ['title' => 'Sara Qahtani CV', 'link' => 'https://example.net/cv/sara-q.pdf', 'snippet' => 'BIM Engineer. Education: University of Jordan. Worked with the hiring manager on staffing plans. 6 years of experience in Revit.'],
                ['title' => 'BIM Engineer Resume Template - Free Download', 'link' => 'https://cvtemplates.example.com/bim-engineer-resume-template', 'snippet' => 'Download our free BIM engineer resume template.'],
                ['title' => 'Ahmed Al-Harbi - Senior BIM Engineer - CV', 'link' => 'https://cdn.example.org/files/ahmed_alharbi_cv.pdf', 'snippet' => 'Senior BIM Engineer with 9 years of experience in Revit and Navisworks, Riyadh, Saudi Arabia.'],
                ['title' => 'Khalid Omar - BIM Engineer - Dar | LinkedIn', 'link' => 'https://sa.linkedin.com/in/khalid-omar-123', 'snippet' => 'Riyadh, Saudi Arabia. BIM Engineer. Revit.'],
            ]]),
        ]);
    }

    /** @return array<string, array<string, mixed>> results keyed by URL */
    private function runSearch(): array
    {
        $this->fakeSearchResults();
        $rows = app(SearchProviderService::class)->cvSourcing($this->filters);

        return collect($rows)->keyBy('url')->all();
    }

    public function test_queries_go_from_precise_to_broad_and_keep_the_role_flexible(): void
    {
        $queries = app(SearchProviderService::class)->buildQueries($this->filters);

        $this->assertCount(4, $queries);
        $this->assertStringStartsWith('filetype:pdf', $queries[0]);
        // The title as typed, without seniority, and the singular specialty — not three exact phrases AND-ed together.
        $this->assertStringContainsString('("Senior BIM Engineer" OR "BIM Engineer")', $queries[0]);
        $this->assertStringContainsString('"Revit"', $queries[0]);
        $this->assertStringContainsString('"Riyadh"', $queries[0]);
        $this->assertStringContainsString('-hiring', $queries[0]);
        $this->assertStringContainsString('site:linkedin.com/in', $queries[3]);
        // Languages help ranking but would only cripple recall as required search words.
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('Arabic', $query);
        }
    }

    public function test_queries_stay_valid_with_only_a_specialization(): void
    {
        $queries = app(SearchProviderService::class)->buildQueries(['specialization' => 'Accountants']);

        $this->assertStringContainsString('"Accountant"', $queries[1]);
        $this->assertStringNotContainsString('""', implode(' ', $queries));
    }

    public function test_real_cvs_are_separated_from_job_postings_templates_and_social_pages(): void
    {
        $results = $this->runSearch();

        $this->assertSame('likely_candidate', $results['https://cdn.example.org/files/ahmed_alharbi_cv.pdf']['match_quality']);
        $this->assertSame('likely_candidate', $results['https://sa.linkedin.com/in/khalid-omar-123']['match_quality']);
        $this->assertSame('likely_job_posting', $results['https://www.bayt.com/en/saudi-arabia/jobs/bim-engineer-123/']['match_quality']);
        $this->assertSame('likely_job_posting', $results['https://acme-contracting.com/careers/bim-engineer']['match_quality']);
        $this->assertSame('likely_other', $results['https://cvtemplates.example.com/bim-engineer-resume-template']['match_quality']);
    }

    public function test_a_cv_that_merely_mentions_hiring_or_a_university_is_still_a_cv(): void
    {
        $results = $this->runSearch();

        // This CV mentions "hiring manager" and "University of Jordan" — both used to flag it as noise.
        $this->assertSame('likely_candidate', $results['https://example.net/cv/sara-q.pdf']['match_quality']);
    }

    public function test_results_are_ranked_by_relevance_with_noise_last(): void
    {
        $results = array_values($this->runSearch());

        $this->assertSame('https://cdn.example.org/files/ahmed_alharbi_cv.pdf', $results[0]['url']);
        $this->assertGreaterThan($results[1]['relevance_score'], $results[0]['relevance_score']);

        $qualities = array_column($results, 'match_quality');
        $firstNoise = collect($qualities)->search(fn ($q) => in_array($q, ['likely_job_posting', 'likely_other'], true));
        foreach (array_slice($qualities, $firstNoise) as $quality) {
            $this->assertContains($quality, ['likely_job_posting', 'likely_other']);
        }
    }

    public function test_each_result_says_what_matched_and_what_was_not_seen(): void
    {
        $ahmed = $this->runSearch()['https://cdn.example.org/files/ahmed_alharbi_cv.pdf'];

        $this->assertContains('Revit', $ahmed['matched_terms']);
        $this->assertContains('Navisworks', $ahmed['matched_terms']);
        $this->assertContains('Riyadh', $ahmed['matched_terms']);
        $this->assertContains('BIM Coordination', $ahmed['missing_terms']);
    }

    public function test_candidate_names_come_from_the_best_available_clue(): void
    {
        $results = $this->runSearch();
        $this->assertSame('Ahmed Al-Harbi', $results['https://cdn.example.org/files/ahmed_alharbi_cv.pdf']['candidate_name']);
        $this->assertSame('Khalid Omar', $results['https://sa.linkedin.com/in/khalid-omar-123']['candidate_name']);

        $provider = app(SearchProviderService::class);
        $this->assertSame('Ahmed Ali', $provider->candidateName('Resume of Ahmed Ali', '', ''));
        $this->assertSame('Mona Zaid', $provider->candidateName('CV', '', 'https://x.example.org/Mona_Zaid_CV_2024.pdf'));
        $this->assertNull($provider->candidateName('Curriculum Vitae', 'A document', 'https://x.example.org/cv.pdf'));
    }

    public function test_guides_and_careers_pages_are_not_candidates(): void
    {
        Http::fake(['serpapi.com/*' => Http::response(['organic_results' => [
            ['title' => 'BIM Engineer Job Guide and Resume | PDF', 'link' => 'https://www.scribd.com/document/1/guide', 'snippet' => 'A guide to becoming a BIM engineer.'],
            ['title' => 'Technical - Senior BIM Mechanical Engineer - Careers', 'link' => 'https://careers.example-co.com/role/42', 'snippet' => 'Join our team in Riyadh.'],
            ['title' => 'Sara Qahtani - BIM Coordinator Resume | PDF', 'link' => 'https://www.scribd.com/document/2/sara', 'snippet' => 'Resume of Sara Qahtani. 6 years of experience in Revit.'],
        ]])]);

        $rows = collect(app(SearchProviderService::class)->cvSourcing($this->filters))->keyBy('url');

        $this->assertSame('likely_other', $rows['https://www.scribd.com/document/1/guide']['match_quality']);
        $this->assertSame('likely_job_posting', $rows['https://careers.example-co.com/role/42']['match_quality']);
        $this->assertSame('likely_candidate', $rows['https://www.scribd.com/document/2/sara']['match_quality']);
    }

    public function test_search_page_warns_when_no_provider_is_configured(): void
    {
        $this->useSearchProvider(false);

        $this->actingAs($this->owner)
            ->get('/ai-search')
            ->assertOk()
            ->assertSee('No search provider is configured');
    }

    public function test_search_requires_something_to_search_for(): void
    {
        $this->actingAs($this->owner)
            ->post('/ai-search/cv-sourcing', ['quantity' => 10])
            ->assertSessionHasErrors('job_title');
    }

    public function test_history_page_hides_job_postings_unless_asked(): void
    {
        $this->fakeSearchResults();
        $this->actingAs($this->owner)->post('/ai-search/cv-sourcing', array_merge($this->filters, [
            'skills' => 'BIM coordination', 'software_skills' => 'Revit; Navisworks', 'languages' => 'Arabic; English',
        ]));
        $job = AiSearchJob::query()->latest('id')->firstOrFail();

        $this->actingAs($this->owner)->get(route('ai-search.show', $job))
            ->assertOk()
            ->assertSee('Ahmed Al-Harbi')
            ->assertDontSee('BIM Engineer jobs in Riyadh')
            ->assertSee('hidden');

        $this->actingAs($this->owner)->get(route('ai-search.show', ['searchJob' => $job, 'all' => 1]))
            ->assertOk()
            ->assertSee('BIM Engineer jobs in Riyadh');
    }

    // ------------------------------------------------------------------ import

    private function resultFor(string $url, string $fileType = 'pdf'): \App\Models\AiSearchResult
    {
        $job = AiSearchJob::create([
            'company_id' => $this->owner->company_id,
            'created_by' => $this->owner->id,
            'filters' => ['specialization' => 'BIM Engineers', 'country' => 'Saudi Arabia', 'city' => 'Riyadh'],
            'queries' => [],
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        return $job->results()->create([
            'source' => 'SerpAPI',
            'source_url' => $url,
            'raw_payload' => [
                'source' => 'SerpAPI', 'title' => 'CV', 'url' => $url, 'snippet' => 'BIM engineer CV',
                'file_type' => $fileType, 'compliance_status' => 'allowed', 'match_quality' => 'likely_candidate',
            ],
        ]);
    }

    public function test_import_downloads_the_cv_classifies_it_and_stores_searchable_text(): void
    {
        Storage::fake('local');
        Http::fake([
            'files.example.com/*' => Http::response(
                "Faisal Rahman\nSenior BIM Engineer\nfaisal.rahman@example.com\n+966 50 555 1212\nBIM Engineer with 7 years of experience in Revit, Navisworks, BIM 360 and clash detection.\nRiyadh, Saudi Arabia",
                200,
                ['Content-Type' => 'application/pdf']
            ),
        ]);
        $result = $this->resultFor('https://files.example.com/cv/anon.pdf');

        // The form now sends the specialty blank so the CV decides.
        $this->actingAs($this->owner)->post('/ai-search/import-result', [
            'result_id' => $result->id,
            'consent_status' => 'PENDING',
            'specialization' => '',
        ])->assertRedirect();

        $candidate = Candidate::where('email', 'faisal.rahman@example.com')->firstOrFail();
        $this->assertSame('Faisal Rahman', $candidate->full_name);
        $this->assertSame('Senior BIM Engineer', $candidate->title);
        $this->assertSame('BIM Engineers', $candidate->specialization);
        $this->assertSame('Saudi Arabia', $candidate->country);
        $this->assertSame(7, (int) $candidate->years_experience);
        $this->assertStringContainsString('Navisworks', $candidate->cv_text);
        $this->assertContains('Navisworks', $candidate->skills()->pluck('name')->all());

        $document = $candidate->documents()->firstOrFail();
        $this->assertStringStartsWith('private/cv-bank/bim-engineers/', $document->storage_path);
        Storage::disk('local')->assertExists($document->storage_path);
        $this->assertSame($candidate->id, $result->fresh()->candidate_id);
    }

    public function test_import_keeps_a_specialty_the_recruiter_typed(): void
    {
        Storage::fake('local');
        Http::fake(['files.example.com/*' => Http::response("Nadia Farouk\nQuantity Surveyor\nnadia@example.com\nQuantity surveyor with BOQ and FIDIC experience.", 200, ['Content-Type' => 'application/pdf'])]);
        $result = $this->resultFor('https://files.example.com/cv/nadia.pdf');

        $this->actingAs($this->owner)->post('/ai-search/import-result', [
            'result_id' => $result->id, 'consent_status' => 'PENDING', 'specialization' => 'Project Managers',
        ])->assertRedirect();

        $this->assertSame('Project Managers', Candidate::where('email', 'nadia@example.com')->firstOrFail()->specialization);
    }

    public function test_import_never_fetches_private_network_addresses(): void
    {
        Storage::fake('local');
        Http::fake();
        $result = $this->resultFor('http://127.0.0.1/secret/cv.pdf');

        $this->actingAs($this->owner)->post('/ai-search/import-result', [
            'result_id' => $result->id, 'consent_status' => 'PENDING', 'specialization' => '',
        ])->assertRedirect()->assertSessionHas('status');

        Http::assertNothingSent();
        $this->assertStringContainsString('could not be downloaded', session('status'));
        // The import itself still succeeds, with the snippet only and no stored file.
        $candidate = Candidate::query()->latest('id')->firstOrFail();
        $this->assertSame(0, $candidate->documents()->count());
        $this->assertSame('BIM Engineers', $candidate->specialization);
    }

    public function test_a_web_page_served_as_a_pdf_is_not_saved_as_a_cv(): void
    {
        Storage::fake('local');
        Http::fake(['files.example.com/*' => Http::response('<!doctype html><html><body>Please log in</body></html>', 200, ['Content-Type' => 'text/html'])]);
        $result = $this->resultFor('https://files.example.com/cv/login.pdf');

        $this->actingAs($this->owner)->post('/ai-search/import-result', [
            'result_id' => $result->id, 'consent_status' => 'PENDING', 'specialization' => '',
        ])->assertRedirect();

        $candidate = Candidate::query()->latest('id')->firstOrFail();
        $this->assertSame(0, $candidate->documents()->count());
        $this->assertEmpty($candidate->cv_text);
    }

    // ------------------------------------------------------ auto sourcing guard

    public function test_auto_sourcing_does_not_turn_job_adverts_into_candidates(): void
    {
        $search = SourcingSearch::create([
            'company_id' => $this->owner->company_id, 'name' => 'BIM', 'job_title' => 'BIM Engineer',
            'specialization' => 'BIM Engineers', 'country' => 'Saudi Arabia', 'city' => 'Riyadh', 'quantity' => 25,
            'download_cvs' => false, 'auto_import' => true, 'default_consent_status' => 'PENDING',
            'frequency' => 'daily', 'is_active' => true, 'created_by' => $this->owner->id,
        ]);
        $run = SourcingRun::create([
            'company_id' => $search->company_id, 'sourcing_search_id' => $search->id, 'status' => 'RUNNING', 'started_at' => now(),
        ]);

        app(AutoSourcingService::class)->importResults($search, [
            ['source' => 'SerpAPI', 'title' => 'Hiring BIM Engineer - Apply now', 'url' => 'https://acme.example.com/careers/bim',
                'snippet' => 'We are hiring', 'file_type' => 'profile', 'compliance_status' => 'allowed', 'match_quality' => 'likely_job_posting', 'relevance_score' => 12],
            ['source' => 'SerpAPI', 'title' => 'BIM Engineer Resume Template', 'url' => 'https://cv.example.com/template',
                'snippet' => 'Free template', 'file_type' => 'profile', 'compliance_status' => 'allowed', 'match_quality' => 'likely_other'],
            ['source' => 'SerpAPI', 'title' => 'Ahmed Al-Harbi - BIM Engineer', 'url' => 'https://example.org/in/ahmed',
                'snippet' => 'BIM Engineer Revit', 'file_type' => 'profile', 'compliance_status' => 'allowed',
                'match_quality' => 'likely_candidate', 'relevance_score' => 80, 'candidate_name' => 'Ahmed Al-Harbi'],
        ], $run, $this->owner);

        $this->assertDatabaseHas('candidates', ['full_name' => 'Ahmed Al-Harbi']);
        $this->assertSame(0, Candidate::where('full_name', 'like', '%Hiring%')->orWhere('full_name', 'like', '%Template%')->count());
        // The adverts are still kept in the search history for a human to look at.
        $this->assertSame(3, AiSearchJob::query()->latest('id')->first()->results()->count());
    }

    // ----------------------------------------------------------- opening a CV

    public function test_cv_opens_inline_in_the_browser_and_is_audited(): void
    {
        Storage::fake('local');
        $candidate = Candidate::query()->first();
        Storage::disk('local')->put('private/cv-bank/civil-engineers/a.pdf', "%PDF-1.4\nfake body");
        $document = $candidate->documents()->create([
            'file_name' => 'Ahmed CV.pdf', 'mime_type' => 'text/html', // the stored mime type is not trusted
            'storage_path' => 'private/cv-bank/civil-engineers/a.pdf', 'checksum' => 'x', 'scan_status' => 'COMPLETED',
        ]);

        $response = $this->actingAs($this->owner)->get(route('candidates.documents.view', [$candidate, $document]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString("object-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'CV_DOCUMENT_VIEW']);
    }

    public function test_word_cvs_fall_back_to_download_because_browsers_cannot_render_them(): void
    {
        Storage::fake('local');
        $candidate = Candidate::query()->first();
        Storage::disk('local')->put('private/cv-bank/civil-engineers/b.docx', "PK\x03\x04fake");
        $document = $candidate->documents()->create([
            'file_name' => 'b.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'storage_path' => 'private/cv-bank/civil-engineers/b.docx', 'checksum' => 'x', 'scan_status' => 'COMPLETED',
        ]);

        $this->actingAs($this->owner)->get(route('candidates.documents.view', [$candidate, $document]))
            ->assertRedirect(route('candidates.documents.download', [$candidate, $document]));
    }

    public function test_profile_page_offers_open_and_download_buttons(): void
    {
        Storage::fake('local');
        $candidate = Candidate::query()->first();
        Storage::disk('local')->put('private/cv-bank/civil-engineers/c.pdf', '%PDF-1.4 x');
        $candidate->documents()->create([
            'file_name' => 'c.pdf', 'mime_type' => 'application/pdf',
            'storage_path' => 'private/cv-bank/civil-engineers/c.pdf', 'checksum' => 'x', 'scan_status' => 'COMPLETED',
        ]);

        $this->actingAs($this->owner)->get(route('candidates.show', $candidate))
            ->assertOk()
            ->assertSee('Open CV')
            ->assertSee('Download CV');
    }
}
