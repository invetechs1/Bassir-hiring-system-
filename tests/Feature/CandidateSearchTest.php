<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\User;
use App\Services\CandidateSearchService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Candidate $civil;

    private Candidate $mechanical;

    private Candidate $developer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = User::where('username', 'yahya')->firstOrFail();
        $this->owner->forceFill(['must_change_password' => false])->save();

        // Start from a clean slate so the seeded demo candidates cannot affect rankings.
        Candidate::query()->forceDelete();

        $this->civil = $this->makeCandidate([
            'full_name' => 'Sara Alamri', 'title' => 'Senior Civil Engineer', 'specialization' => 'Civil Engineers',
            'city' => 'Riyadh', 'country' => 'Saudi Arabia', 'years_experience' => 8,
            'cv_text' => 'Led structural packages for a giga-project with Saudi Aramco. Planned in Primavera P6 and ETABS.',
        ], ['Primavera P6', 'ETABS']);
        $this->mechanical = $this->makeCandidate([
            'full_name' => 'Omar Nasser', 'title' => 'Mechanical Engineer', 'specialization' => 'Mechanical Engineers',
            'city' => 'Riyadh', 'country' => 'Saudi Arabia', 'years_experience' => 3,
            'cv_text' => 'HVAC design and commissioning for commercial towers.',
        ], ['HVAC', 'AutoCAD']);
        $this->developer = $this->makeCandidate([
            'full_name' => 'Layla Haddad', 'title' => 'Full Stack Developer', 'specialization' => 'Software Developers',
            'city' => 'Jeddah', 'country' => 'Saudi Arabia', 'years_experience' => 5,
            'cv_text' => 'Builds web apps with JavaScript, React and Laravel. Previously at a fintech in Dubai.',
        ], ['JavaScript', 'Laravel']);
    }

    private function makeCandidate(array $attributes, array $skills): Candidate
    {
        $candidate = Candidate::create($attributes + [
            'company_id' => $this->owner->company_id,
            'consent_status' => 'PENDING',
            'status' => 'NEW',
        ]);
        foreach ($skills as $skill) {
            $candidate->skills()->create(['name' => $skill]);
        }

        return $candidate;
    }

    private function search(string $query, array $options = []): array
    {
        return app(CandidateSearchService::class)->search($query, $this->owner, $options)->pluck('full_name')->all();
    }

    public function test_best_fit_ranks_first_and_unrelated_candidates_are_excluded(): void
    {
        $names = $this->search('civil engineer Primavera Riyadh');

        $this->assertSame('Sara Alamri', $names[0]);
        $this->assertNotContains('Layla Haddad', $names);
    }

    public function test_every_term_must_match_for_a_two_word_request(): void
    {
        // "civil engineer" used to return every engineer; both words must now be present.
        $this->assertSame(['Sara Alamri'], $this->search('civil engineer'));
    }

    public function test_skill_aliases_find_the_same_skill(): void
    {
        $this->assertSame(['Sara Alamri'], $this->search('p6'));
        $this->assertSame(['Sara Alamri'], $this->search('Primavera'));
    }

    public function test_short_skill_names_do_not_match_inside_longer_words(): void
    {
        // Layla knows JavaScript, not Java.
        $this->assertSame([], $this->search('java'));
        $this->assertSame(['Layla Haddad'], $this->search('javascript'));
    }

    public function test_the_full_cv_text_is_searched_and_an_excerpt_is_returned(): void
    {
        $results = app(CandidateSearchService::class)->search('Aramco', $this->owner);

        $this->assertCount(1, $results);
        $this->assertSame('Sara Alamri', $results->first()->full_name);
        $this->assertStringContainsString('Aramco', $results->first()->search_snippet);
    }

    public function test_minimum_years_is_understood(): void
    {
        $names = $this->search('engineer 6 years');

        $this->assertSame(['Sara Alamri'], $names);
    }

    public function test_results_explain_what_matched_and_what_is_missing(): void
    {
        $results = app(CandidateSearchService::class)->search('engineer Riyadh Dubai', $this->owner, ['mode' => 'any']);
        $mechanical = $results->firstWhere('full_name', 'Omar Nasser');

        $this->assertNotNull($mechanical);
        $this->assertSame('2/3', $mechanical->search_coverage);
        $this->assertSame(['dubai'], $mechanical->search_missing);
    }

    public function test_strict_mode_requires_every_term(): void
    {
        $this->assertSame([], $this->search('civil engineer Primavera Jeddah', ['mode' => 'all']));
        $this->assertSame(['Sara Alamri'], $this->search('civil engineer Primavera Riyadh', ['mode' => 'all']));
    }

    public function test_gibberish_matches_nobody_instead_of_everybody(): void
    {
        $this->assertSame([], $this->search('zzzxqv'));

        $this->actingAs($this->owner)
            ->get('/search-assistant?q=zzzxqv')
            ->assertOk()
            ->assertSee('No candidates matched this request')
            ->assertDontSee('Sara Alamri');
    }

    public function test_like_wildcards_typed_by_a_user_are_not_treated_as_patterns(): void
    {
        $this->assertSame([], $this->search('%'));
        $this->assertSame([], $this->search('_'));
    }

    public function test_search_assistant_page_shows_why_a_candidate_matched(): void
    {
        $this->actingAs($this->owner)
            ->get('/search-assistant?q='.urlencode('civil engineer Primavera 5 years Riyadh'))
            ->assertOk()
            ->assertSee('Sara Alamri')
            ->assertSee('% match')
            ->assertSee('criteria');
    }

    public function test_candidates_list_search_uses_ranked_search_and_highlights_on_the_profile(): void
    {
        $this->actingAs($this->owner)
            ->get('/candidates?q=Aramco')
            ->assertOk()
            ->assertSee('Sara Alamri')
            ->assertDontSee('Omar Nasser');

        $this->actingAs($this->owner)
            ->get('/candidates/'.$this->civil->id.'?hl=Aramco')
            ->assertOk()
            ->assertSee('<mark>Aramco</mark>', false);
    }

    public function test_cv_bank_folder_search_finds_skills_not_just_names(): void
    {
        $this->actingAs($this->owner)
            ->get('/cv-bank/civil-engineers?q=ETABS')
            ->assertOk()
            ->assertSee('Sara Alamri');

        $this->actingAs($this->owner)
            ->get('/cv-bank/mechanical-engineers?q=ETABS')
            ->assertOk()
            ->assertDontSee('Omar Nasser');
    }

    public function test_search_is_tenant_scoped(): void
    {
        $other = \App\Models\Company::create(['name' => 'Other Co', 'slug' => 'other-co']);
        Candidate::create([
            'full_name' => 'Hidden Person', 'title' => 'Senior Civil Engineer', 'specialization' => 'Civil Engineers',
            'company_id' => $other->id, 'consent_status' => 'PENDING', 'status' => 'NEW',
        ]);
        $recruiter = User::where('username', 'recruiter')->firstOrFail();
        $this->assertSame($this->owner->company_id, $recruiter->company_id);

        $search = app(CandidateSearchService::class);

        // A company user sees only their own company's candidates...
        $this->assertSame(['Sara Alamri'], $search->search('civil engineer', $recruiter)->pluck('full_name')->all());
        // ...while the platform super-admin sees every company.
        $this->assertEqualsCanonicalizing(['Sara Alamri', 'Hidden Person'], $search->search('civil engineer', $this->owner)->pluck('full_name')->all());
    }
}
