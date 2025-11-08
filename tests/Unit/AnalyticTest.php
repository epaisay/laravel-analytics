<?php

namespace Epaisay\Analytics\Tests\Unit;

use Epaisay\Analytics\Models\Analytic;
use Epaisay\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AnalyticTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_an_analytic_record()
    {
        $analytic = Analytic::create([
            'analyticable_type' => 'TestModel',
            'analyticable_id' => 'test-id',
            'views_count' => 10,
            'unique_viewers' => 5,
        ]);

        $this->assertNotNull($analytic->id);
        $this->assertEquals('TestModel', $analytic->analyticable_type);
        $this->assertEquals(10, $analytic->views_count);
        $this->assertEquals(5, $analytic->unique_viewers);
    }

    /** @test */
    public function it_calculates_engagement_score_correctly()
    {
        $analytic = Analytic::create([
            'analyticable_type' => 'TestModel',
            'analyticable_id' => 'test-id',
            'views_count' => 100,
            'likes_count' => 20,
            'shares_count' => 10,
            'clicks_count' => 15,
            'replies_count' => 5,
            'follows_count' => 3,
            'bookmarks_count' => 2,
        ]);

        $expectedScore = (100 * 0.15) + (20 * 0.25) + (10 * 0.20) + 
                        (15 * 0.15) + (5 * 0.10) + (3 * 0.10) + (2 * 0.05);
        
        $this->assertEquals($expectedScore, $analytic->engagement_score);
    }

    /** @test */
    public function it_calculates_engagement_rate_correctly()
    {
        $analytic = Analytic::create([
            'analyticable_type' => 'TestModel',
            'analyticable_id' => 'test-id',
            'views_count' => 100,
            'likes_count' => 20,
            'shares_count' => 10,
        ]);

        $engagementScore = (100 * 0.15) + (20 * 0.25) + (10 * 0.20);
        $expectedRate = ($engagementScore / 100) * 100;

        $this->assertEquals($expectedRate, $analytic->engagement_rate);
    }

    /** @test */
    public function it_returns_zero_engagement_rate_for_zero_views()
    {
        $analytic = Analytic::create([
            'analyticable_type' => 'TestModel',
            'analyticable_id' => 'test-id',
            'views_count' => 0,
        ]);

        $this->assertEquals(0.0, $analytic->engagement_rate);
    }

    /** @test */
    public function it_can_increment_views()
    {
        $analytic = Analytic::create([
            'analyticable_type' => 'TestModel',
            'analyticable_id' => 'test-id',
            'views_count' => 10,
        ]);

        $analytic->incrementViews(5);

        $this->assertEquals(15, $analytic->fresh()->views_count);
    }

    /** @test */
    public function it_can_check_if_active()
    {
        $analytic = Analytic::create([
            'analyticable_type' => 'TestModel',
            'analyticable_id' => 'test-id',
            'analytics_status' => true,
        ]);

        $this->assertTrue($analytic->isActive());
    }

    /** @test */
    public function it_can_generate_summary()
    {
        $analytic = Analytic::create([
            'analyticable_type' => 'TestModel',
            'analyticable_id' => 'test-id',
            'views_count' => 100,
            'unique_viewers' => 50,
            'human_views' => 80,
            'bot_views' => 20,
            'likes_count' => 25,
            'shares_count' => 10,
            'comments_count' => 15,
        ]);

        $summary = $analytic->getSummary();

        $this->assertArrayHasKey('total_views', $summary);
        $this->assertArrayHasKey('unique_viewers', $summary);
        $this->assertArrayHasKey('engagement_rate', $summary);
        $this->assertEquals(100, $summary['total_views']);
        $this->assertEquals(50, $summary['unique_viewers']);
    }
}