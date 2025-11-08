<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePeriodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // 🔗 Link to the parent analytics record (which is linked to the item/owner)
            $table->foreignUuid('analytic_id')
                  ->constrained('analytics') // Assumes the analytics table exists
                  ->onDelete('cascade');

            // The specific metric being tracked (e.g., 'views_count', 'impressions_count')
            $table->string('analytic_type', 50);

            // The granularity of the record ('daily', 'weekly', 'monthly', 'yearly')
            $table->enum('period_granularity', ['daily', 'weekly', 'monthly', 'yearly'])->index();

            // The date the period starts (e.g., 2025-10-13 for daily)
            $table->date('period_start_date');
            $table->date('period_end_date')->nullable();

            // The aggregated value for that period
            $table->unsignedBigInteger('value')->default(0);

            $table->decimal('growth_rate', 8, 2)->nullable();
            $table->unsignedBigInteger('previous_value')->nullable();

            $table->boolean('period_status')->default(1);
            $table->boolean('period_lock')->default(0);

            $table->timestamps();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->uuid('read_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->uuid('restored_by')->nullable();

            // ⚡ UNIQUE INDEX: Prevents duplicate metric/period entries
            $table->unique([
                'analytic_id', 
                'analytic_type', 
                'period_start_date', 
                'period_granularity'
            ], 'periods_unique_metric_per_period');
            
            // 👇 RECOMMENDED ADDITION: INDEX for querying by date and metric
            $table->index(['analytic_id', 'analytic_type', 'period_start_date']);
            $table->index(['period_start_date', 'analytic_type']);
            $table->index(['analytic_id', 'period_start_date']);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('periods');
    }
}