<?php

namespace Epaisay\Analytics\Tests\Unit;

use Epaisay\Analytics\Models\View;
use Epaisay\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ViewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_view_record()
    {
        $view = View::create([
            'viewable_type' => 'TestModel',
            'viewable_id' => 'test-id',
            'analytic_id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'ip_address' => '127.0.0.1',
            'useragent' => 'Test Browser',
            'device' => 'Desktop',
            'browser' => 'Chrome',
        ]);

        $this->assertNotNull($view->id);
        $this->assertEquals('TestModel', $view->viewable_type);
        $this->assertEquals('127.0.0.1', $view->ip_address);
        $this->assertEquals('Desktop', $view->device);
    }

    /** @test */
    public function it_sets_default_values_on_creation()
    {
        $view = View::create([
            'viewable_type' => 'TestModel',
            'viewable_id' => 'test-id',
            'analytic_id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        ]);

        $this->assertTrue($view->view_status);
        $this->assertFalse($view->view_lock);
        $this->assertFalse($view->is_robot);
        $this->assertNotNull($view->visited_at);
    }

    /** @test */
    public function it_can_check_if_authenticated()
    {
        $viewWithUser = View::create([
            'viewable_type' => 'TestModel',
            'viewable_id' => 'test-id',
            'analytic_id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'user_id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        ]);

        $viewWithoutUser = View::create([
            'viewable_type' => 'TestModel',
            'viewable_id' => 'test-id',
            'analytic_id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        ]);

        $this->assertTrue($viewWithUser->is_authenticated);
        $this->assertFalse($viewWithoutUser->is_authenticated);
    }

    /** @test */
    public function it_can_check_if_guest()
    {
        $viewWithUser = View::create([
            'viewable_type' => 'TestModel',
            'viewable_id' => 'test-id',
            'analytic_id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'user_id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        ]);

        $viewWithVisitor = View::create([
            'viewable_type' => 'TestModel',
            'viewable_id' => 'test-id',
            'analytic_id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'visitor_token' => 'test-token',
        ]);

        $this->assertFalse($viewWithUser->is_guest);
        $this->assertTrue($viewWithVisitor->is_guest);
    }

    /** @test */
    public function it_can_generate_formatted_location()
    {
        $view = View::create([
            'viewable_type' => 'TestModel',
            'viewable_id' => 'test-id',
            'analytic_id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'city' => 'New York',
            'region_name' => 'New York',
            'country' => 'United States',
        ]);

        $this->assertEquals('New York, New York, United States', $view->formatted_location);
    }

    /** @test */
    public function it_can_generate_formatted_browser()
    {
        $view = View::create([
            'viewable_type' => 'TestModel',
            'viewable_id' => 'test-id',
            'analytic_id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'browser' => 'Chrome',
            'browser_version' => '91.0',
        ]);

        $this->assertEquals('Chrome 91.0', $view->formatted_browser);
    }

    /** @test */
    public function it_can_generate_device_platform_string()
    {
        $view = View::create([
            'viewable_type' => 'TestModel',
            'viewable_id' => 'test-id',
            'analytic_id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'device_type' => 'Mobile',
            'device' => 'iPhone',
            'os' => 'iOS 14',
        ]);

        $this->assertEquals('Mobile · iPhone · iOS 14', $view->device_platform);
    }
}