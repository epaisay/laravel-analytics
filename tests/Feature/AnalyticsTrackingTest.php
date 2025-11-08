<?php

namespace Epaisay\Analytics\Tests\Feature;

use Epaisay\Analytics\Models\Analytic;
use Epaisay\Analytics\Models\View;
use Epaisay\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class AnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test route
        Route::get('/test-post/{post}', function () {
            return response()->json(['message' => 'OK']);
        })->name('test.post.show')->middleware('track.analytics');
    }

    /** @test */
    public function it_tracks_views_via_middleware()
    {
        $response = $this->get('/test-post/1');

        $response->assertStatus(200);
        
        // Check if a view was recorded
        $this->assertDatabaseCount('views', 1);
        
        $view = View::first();
        $this->assertNotNull($view);
        $this->assertEquals('1', $view->viewable_id);
    }

    /** @test */
    public function it_creates_analytics_record_for_tracked_views()
    {
        $this->get('/test-post/1');

        $analytic = Analytic::first();
        $this->assertNotNull($analytic);
        $this->assertEquals(1, $analytic->views_count);
    }

    /** @test */
    public function it_increments_views_count_on_subsequent_requests()
    {
        $this->get('/test-post/1');
        $this->get('/test-post/1');
        $this->get('/test-post/1');

        $analytic = Analytic::first();
        $this->assertEquals(3, $analytic->views_count);
    }

    /** @test */
    public function it_tracks_different_models_separately()
    {
        $this->get('/test-post/1');
        $this->get('/test-post/2');
        $this->get('/test-post/1');

        $analytics = Analytic::all();
        $this->assertCount(2, $analytics);

        $analytic1 = Analytic::where('analyticable_id', '1')->first();
        $analytic2 = Analytic::where('analyticable_id', '2')->first();

        $this->assertEquals(2, $analytic1->views_count);
        $this->assertEquals(1, $analytic2->views_count);
    }

    /** @test */
    public function it_tracks_user_information_when_authenticated()
    {
        // This would require setting up authentication for testing
        $this->markTestSkipped('Authentication setup required for this test');
    }

    /** @test */
    public function it_tracks_visitor_information_for_guests()
    {
        $this->get('/test-post/1');

        $view = View::first();
        $this->assertNotNull($view->visitor_token);
        $this->assertNotNull($view->session_id);
        $this->assertNotNull($view->ip_address);
    }
}