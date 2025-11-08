<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateViewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('views', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuidMorphs('viewable');

            $table->foreignUuid('analytic_id')
                ->constrained('analytics')
                ->onDelete('cascade');

            $table->uuid('user_id')->nullable()->index(); // remove FK constraint


            // For anonymous tracking
            $table->string('visitor_token')->nullable()->index(); // for guests (session or cookie)
            $table->string('ip_address', 45)->nullable()->index(); // IPv6 compatible - ADDED INDEX
            $table->string('session_id')->nullable()->index();     // To track unique sessions

            $table->timestamp('visited_at')->useCurrent()->index();
            
            $table->string('action_type')->nullable();
            $table->string('request_path')->nullable();
            
            $table->string('method', 20)->nullable();
            $table->mediumText('request')->nullable();
            $table->text('url')->nullable();
            $table->text('referer')->nullable();
            $table->string('page_url')->nullable()->index();
            $table->text('languages')->nullable();
            $table->text('useragent')->nullable();
            $table->mediumText('headers')->nullable();
            
            // UPDATED: Changed from text to string with proper lengths and indexes
            $table->string('device', 50)->nullable()->index(); // CHANGED: from text to string(50)
            $table->string('device_type', 100)->nullable(); // NEW: Added device_type
            $table->string('platform', 100)->nullable(); // CHANGED: from text to string(100)
            $table->string('os', 50)->nullable()->index();
            $table->string('browser', 50)->nullable()->index();
            $table->string('browser_version', 50)->nullable();

            // NEW: Added bot detection fields
            $table->boolean('is_robot')->default(0)->index(); // NEW: Added is_robot
            $table->string('robot_category', 100)->nullable()->index(); // NEW: Added robot_category
            $table->string('robot_name', 100)->nullable()->index(); // NEW: Added robot_name

            $table->string('country', 100)->nullable()->index();
            $table->string('country_code', 5)->nullable()->index();
            $table->string('region', 100)->nullable()->index();
            $table->string('region_name', 100)->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('zip', 20)->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lon', 11, 8)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->string('isp', 100)->nullable();
            $table->string('org', 100)->nullable();
            $table->string('as_name', 100)->nullable();

            $table->boolean('view_status')->default(1)->index(); // CHANGED: default to true
            $table->boolean('view_lock')->default(0); // CHANGED: default to false

            $table->timestamps();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable()->index(); // ADDED INDEX
            $table->uuid('deleted_by')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->uuid('read_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->uuid('restored_by')->nullable();

            // Prevent duplicate view entries for guests
            $table->unique(
                ['visitor_token', 'viewable_type', 'viewable_id', 'action_type', 'request_path', 'ip_address'],
                'unique_public_view_entry'
            );

            // Prevent duplicate view entries for logged-in users
            $table->unique(
                ['user_id', 'viewable_type', 'viewable_id', 'action_type', 'request_path', 'ip_address'],
                'unique_user_view_entry'
            );


            $table->index(['viewable_id', 'viewable_type']);
            $table->index(['user_id', 'created_at']);
            $table->index(['visitor_token', 'created_at']);
            $table->index(['visited_at', 'view_status']); // NEW: Added composite index
            $table->index(['device', 'os']); // NEW: Added composite index
            $table->index(['country', 'city']); // NEW: Added composite index
            $table->index(['is_robot', 'visited_at']); // NEW: Added composite index for bot analysis
            $table->index('created_at'); // NEW: Added single index
            $table->index('updated_at'); // NEW: Added single index
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('views');
    }
}