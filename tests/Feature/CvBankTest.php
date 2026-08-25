<?php

namespace Tests\Feature;

use App\Models\Candidate;
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

    public function test_classifier_routes_civil_engineer_cv_to_civil_folder(): void
    {
        $classifier = app(SpecialtyClassifierService::class);
        $result = $classifier->classify([
            'title' => 'Senior Civil Engineer',
            'summary' => 'Reinforced concrete design, quantity surveyor, road engineer with 12 years of construction experience.',
            'skills' => ['ETABS', 'SAP2000', 'AutoCAD'],
        ]);

        $this->assertSame('civil-engineer', $result['slug']);
        $this->assertSame('Civil Engineer', $result['name']);
        $this->assertGreaterThan(0, $result['confidence']);
    }

    public function test_classifier_routes_software_engineer_cv(): void
    {
        $classifier = app(SpecialtyClassifierService::class);
        $result = $classifier->classify([
            'title' => 'Backend Developer',
            'skills' => ['Laravel', 'PHP', 'REST API', 'React'],
        ]);

        $this->assertSame('software-engineer', $result['slug']);
    }

    public function test_classifier_recognizes_arabic_civil_engineer(): void
    {
        $classifier = app(SpecialtyClassifierService::class);
        $result = $classifier->classify([
            'title' => 'مهندس مدني',
            'summary' => 'خبرة في هندسة مدنية وتصميم إنشائي',
        ]);

        $this->assertSame('civil-engineer', $result['slug']);
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
        // Point one seeded candidate at the Civil Engineer bucket so the KPI is non-zero.
        Candidate::query()->first()->update(['specialization' => 'Civil Engineer']);

        $this->actingAs($this->owner)
            ->get('/cv-bank')
            ->assertOk()
            ->assertSee('Civil Engineer')
            ->assertSee('Software Engineer');
    }

    public function test_cv_bank_show_page_filters_by_specialty(): void
    {
        $candidate = Candidate::query()->first();
        $candidate->update([
            'full_name' => 'Ahmed Al-Civil',
            'specialization' => 'Civil Engineer',
        ]);

        $this->actingAs($this->owner)
            ->get('/cv-bank/civil-engineer')
            ->assertOk()
            ->assertSee('Ahmed Al-Civil');
    }

    public function test_cv_bank_show_supports_search_query(): void
    {
        Candidate::query()->first()->update([
            'full_name' => 'Uniquely Named Person',
            'specialization' => 'Civil Engineer',
        ]);

        $this->actingAs($this->owner)
            ->get('/cv-bank/civil-engineer?q=Uniquely')
            ->assertOk()
            ->assertSee('Uniquely Named Person');
    }

    public function test_reclassify_endpoint_updates_specialty(): void
    {
        $candidate = Candidate::query()->first();
        $candidate->update(['specialization' => 'Unclassified']);

        $this->actingAs($this->owner)
            ->post("/cv-bank/candidates/{$candidate->id}/reclassify", [
                'specialization' => 'Civil Engineer',
            ])
            ->assertRedirect();

        $this->assertSame('Civil Engineer', $candidate->fresh()->specialization);
    }
}
