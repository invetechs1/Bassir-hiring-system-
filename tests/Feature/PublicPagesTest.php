<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_login_page_loads_with_bassir_branding(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Bassir AI Recruitment System')
            ->assertSee('Powered by Bassir Technology');
    }

    public function test_privacy_notice_loads(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy Notice')
            ->assertSee('Powered by Bassir Technology');
    }

    public function test_commercial_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('applications.index'));
        $this->assertTrue(Route::has('applications.store'));
        $this->assertTrue(Route::has('applications.stage'));
        $this->assertTrue(Route::has('jobs.create'));
        $this->assertTrue(Route::has('candidates.create'));
        $this->assertTrue(Route::has('rankings.job'));
        $this->assertTrue(Route::has('search-assistant.index'));
        $this->assertTrue(Route::has('talent-pools.index'));
        $this->assertTrue(Route::has('comparisons.candidates'));
        $this->assertTrue(Route::has('candidates.job-matches'));
    }
}
