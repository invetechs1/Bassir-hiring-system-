<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Specialization;
use App\Models\User;
use App\Services\SpecialtyClassifierService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CvBankTest extends TestCase
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

    public function test_classifier_names_match_the_real_specialization_taxonomy_exactly(): void
    {
        // The classifier groups candidates by an exact match on `specialization`, so its
        // canonical names must be identical to the platform's real taxonomy (Specializations
        // admin page / DatabaseSeeder) — a divergence here means every candidate silently
        // lands in "Unclassified" regardless of their actual specialization.
        $classifier = app(SpecialtyClassifierService::class);
        $classifierNames = collect($classifier->all())->pluck('name')->reject(fn ($name) => $name === SpecialtyClassifierService::UNCLASSIFIED_NAME)->sort()->values();
        $realNames = Specialization::query()->pluck('name')->sort()->values();

        $this->assertSame($realNames->all(), $classifierNames->all());
    }

    public function test_classifier_routes_civil_engineer_cv_to_civil_folder(): void
    {
        $classifier = app(SpecialtyClassifierService::class);
        $result = $classifier->classify([
            'title' => 'Senior Civil Engineer',
            'summary' => 'Reinforced concrete design with 12 years of construction experience.',
            'skills' => ['ETABS', 'SAP2000', 'AutoCAD'],
        ]);

        $this->assertSame('civil-engineers', $result['slug']);
        $this->assertSame('Civil Engineers', $result['name']);
        $this->assertGreaterThan(0, $result['confidence']);
    }

    public function test_classifier_routes_software_engineer_cv(): void
    {
        $classifier = app(SpecialtyClassifierService::class);
        $result = $classifier->classify([
            'title' => 'Backend Developer',
            'skills' => ['Laravel', 'PHP', 'REST API', 'React'],
        ]);

        $this->assertSame('software-developers', $result['slug']);
        $this->assertSame('Software Developers', $result['name']);
    }

    public function test_classifier_recognizes_arabic_civil_engineer(): void
    {
        $classifier = app(SpecialtyClassifierService::class);
        $result = $classifier->classify([
            'title' => 'مهندس مدني',
            'summary' => 'خبرة في هندسة مدنية',
        ]);

        $this->assertSame('civil-engineers', $result['slug']);
    }

    public function test_classifier_returns_unclassified_for_empty_input(): void
    {
        $classifier = app(SpecialtyClassifierService::class);
        $result = $classifier->classify([]);

        $this->assertSame('unclassified', $result['slug']);
        $this->assertSame(0, $result['confidence']);
    }

    public function test_cv_bank_index_lists_specialties_with_counts(): void
    {
        Candidate::query()->first()->update(['specialization' => 'Civil Engineers']);

        $this->actingAs($this->owner)
            ->get('/cv-bank')
            ->assertOk()
            ->assertSee('Civil Engineers')
            ->assertSee('Software Developers');
    }

    public function test_cv_bank_show_page_filters_by_specialty(): void
    {
        $candidate = Candidate::query()->first();
        $candidate->update([
            'full_name' => 'Ahmed Al-Civil',
            'specialization' => 'Civil Engineers',
        ]);

        $this->actingAs($this->owner)
            ->get('/cv-bank/civil-engineers')
            ->assertOk()
            ->assertSee('Ahmed Al-Civil');
    }

    public function test_cv_bank_show_supports_search_query(): void
    {
        Candidate::query()->first()->update([
            'full_name' => 'Uniquely Named Person',
            'specialization' => 'Civil Engineers',
        ]);

        $this->actingAs($this->owner)
            ->get('/cv-bank/civil-engineers?q=Uniquely')
            ->assertOk()
            ->assertSee('Uniquely Named Person');
    }

    public function test_reclassify_endpoint_updates_specialty(): void
    {
        $candidate = Candidate::query()->first();
        $candidate->update(['specialization' => 'Unclassified']);

        $this->actingAs($this->owner)
            ->post("/cv-bank/candidates/{$candidate->id}/reclassify", [
                'specialization' => 'Civil Engineers',
            ])
            ->assertRedirect();

        $this->assertSame('Civil Engineers', $candidate->fresh()->specialization);
    }

    public function test_seeded_demo_candidate_is_correctly_classified_without_any_override(): void
    {
        // Regression guard: the seeded demo candidate (Aisha Al-Fahad, specialization
        // "BIM Engineers") must show up under her real specialty folder with no manual
        // reclassification — this is exactly the scenario that was broken before the fix.
        $candidate = Candidate::where('full_name', 'Aisha Al-Fahad')->firstOrFail();
        $this->assertSame('BIM Engineers', $candidate->specialization);

        $this->actingAs($this->owner)
            ->get('/cv-bank/bim-engineers')
            ->assertOk()
            ->assertSee('Aisha Al-Fahad');

        $this->actingAs($this->owner)
            ->get('/cv-bank')
            ->assertOk()
            ->assertSee('BIM Engineers');
    }
}
