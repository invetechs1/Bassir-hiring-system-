<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_recruitment_analytics_sections(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('username', 'yahya')->firstOrFail();
        $owner->forceFill(['must_change_password' => false])->save();

        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()
            ->assertSee('Recruitment Analytics')
            ->assertSee('Source of Hire')
            ->assertSee('Recruiter Productivity')
            ->assertSee('Pipeline Stage Distribution')
            // Seeded data flows into the analytics: the demo candidate source and the recruiter who owns the seeded job.
            ->assertSee('Seed Data')
            ->assertSee('Demo Recruiter');
    }
}
