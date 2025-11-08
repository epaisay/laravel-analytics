<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAnalyticsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::create('analytics', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id')->nullable()->index(); // remove FK constraint
            $table->string('visitor_token')->nullable()->index(); // for guests (session or cookie)
            
            $table->string('session_id')->nullable()->index();     // To track unique sessions

            // Polymorphic relation to any model
            $table->uuidMorphs('analyticable');
            
            $table->string('ip_address', 45)->nullable()->index(); // IPv6 compatible - ADDED INDEX

            $table->string('action_type')->nullable();
            $table->string('request_path')->nullable();

            // Engagement Metrics
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('unique_viewers')->default(0);
            $table->unsignedBigInteger('user_views')->default(0);
            $table->unsignedBigInteger('public_views')->default(0);
            $table->unsignedBigInteger('bot_views')->default(0);
            $table->unsignedBigInteger('human_views')->default(0);
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0); // Model Like
            $table->unsignedBigInteger('shares_count')->default(0); // Model Share
            $table->unsignedBigInteger('votes_count')->default(0); // Model Votes
            $table->unsignedBigInteger('follows_count')->default(0); // Model Follows
            $table->unsignedBigInteger('replies_count')->default(0); // Model Reply
            $table->unsignedBigInteger('complaints_count')->default(0); // Model Complaint
            $table->unsignedBigInteger('bookmarks_count')->default(0); // Model Bookmark
            $table->unsignedBigInteger('clicks_count')->default(0); // Should i create a model Click for this?
            $table->unsignedBigInteger('comments_count')->default(0); // Model Comments
            $table->unsignedBigInteger('messages_count')->default(0); // Model Messages
            $table->unsignedBigInteger('chats_count')->default(0); // Model Chats
            $table->unsignedBigInteger('contacts_count')->default(0); // Model Contact
            $table->unsignedBigInteger('wishlists_count')->default(0); // Model Wishlist
            $table->unsignedBigInteger('listings_count')->default(0); // Model Listings
            $table->unsignedBigInteger('subscriptions_count')->default(0); // Model Subscriptions where subscription_status == 1
            $table->unsignedBigInteger('users_count')->default(0); // Model Users where user_status == 1, Active users only
            $table->unsignedBigInteger('sellers_count')->default(0); // Model Users where hasRole Seller
            $table->unsignedBigInteger('cartitems_count')->default(0); // Model Cart items
            $table->unsignedBigInteger('checkouts_count')->default(0); // Model Checkout
            $table->unsignedBigInteger('payments_count')->default(0); // Model Payment
            $table->unsignedBigInteger('orders_count')->default(0); // Model Order
            $table->unsignedBigInteger('brands_count')->default(0); // Model Brand
            $table->unsignedBigInteger('shops_count')->default(0); // Model Shop
            $table->unsignedBigInteger('articles_count')->default(0); // Model Article
            $table->unsignedBigInteger('posts_count')->default(0); // Model Post
            $table->unsignedBigInteger('video_count')->default(0); // Model Video

            $table->decimal('click_through_rate', 5, 2)->default(0); // CTR 
            $table->decimal('trend_score', 10, 2)->default(0);
            $table->unsignedBigInteger('reaction_counts')->default(0); // Model Reaction (Not yet Created, Will add in new Version)
            $table->unsignedBigInteger('contributors_count')->default(0); // Model Discussion
            $table->timestamp('last_activity_at')->nullable();

            // State & flags
            $table->boolean('analytics_status')->default(1);
            $table->boolean('analytics_lock')->default(0);

            // Audit Trail
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

            // UNIQUE INDEX
            $table->unique(
                ['visitor_token', 'analyticable_type', 'analyticable_id', 'action_type', 'request_path', 'ip_address'],
                'unique_public_analytic_entry'
            );

            $table->unique(
                ['user_id', 'analyticable_type', 'analyticable_id', 'action_type', 'request_path', 'ip_address'],
                'unique_user_analytic_entry'
            );


            // ==================== COMPREHENSIVE INDEXING STRATEGY ====================
            
            // 1. PRIMARY QUERY INDEXES (Most Frequently Used)
            $table->index(['analyticable_type', 'analyticable_id'], 'analytics_item_lookup');
            $table->index('user_id', 'analytics_user_index');
            
            // 2. PERFORMANCE & SORTING INDEXES
            $table->index(['trend_score', 'last_activity_at'], 'analytics_trending_index');
            $table->index(['views_count', 'last_activity_at'], 'analytics_popularity_index');
            $table->index(['created_at', 'analytics_status'], 'analytics_recent_active_index');
            
            // 3. ENGAGEMENT METRICS INDEXES (For Analytics Dashboards)
            $table->index(['likes_count', 'views_count'], 'analytics_engagement_ratio_index');
            $table->index(['shares_count', 'created_at'], 'analytics_virality_index');
            $table->index(['comments_count', 'replies_count'], 'analytics_discussion_index');
            
            // 4. TIME-BASED ANALYTICS INDEXES
            $table->index('last_activity_at', 'analytics_activity_time_index');
            $table->index('created_at', 'analytics_creation_time_index');
            $table->index(['analyticable_type', 'created_at'], 'analytics_type_time_index');
            
            // 5. STATUS & FILTERING INDEXES
            $table->index('analytics_status', 'analytics_status_index');
            $table->index(['analytics_status', 'user_id'], 'analytics_user_status_index');
            $table->index(['analyticable_type', 'analytics_status'], 'analytics_type_status_index');
            
            // 6. E-COMMERCE SPECIFIC INDEXES
            $table->index(['orders_count', 'last_activity_at'], 'analytics_sales_index');
            $table->index(['checkouts_count', 'payments_count'], 'analytics_conversion_index');
            $table->index(['cartitems_count', 'orders_count'], 'analytics_cart_conversion_index');
            
            // 7. CONTENT ANALYTICS INDEXES
            $table->index(['posts_count', 'articles_count', 'video_count'], 'analytics_content_types_index');
            $table->index(['unique_viewers', 'views_count'], 'analytics_audience_reach_index');
            
            // 8. USER ENGAGEMENT INDEXES
            $table->index(['follows_count', 'subscriptions_count'], 'analytics_followers_index');
            $table->index(['bookmarks_count', 'wishlists_count'], 'analytics_saves_index');
            
            // 9. BUSINESS METRICS INDEXES
            $table->index(['sellers_count', 'shops_count'], 'analytics_business_index');
            $table->index(['brands_count', 'listings_count'], 'analytics_catalog_index');
            
            // 10. COMPOSITE PERFORMANCE INDEXES (For Complex Queries)
            $table->index([
                'analyticable_type', 
                'analytics_status', 
                'trend_score', 
                'last_activity_at'
            ], 'analytics_comprehensive_performance_index');
            
            $table->index([
                'user_id',
                'analyticable_type',
                'created_at'
            ], 'analytics_user_activity_timeline_index');
            
            $table->index([
                'views_count',
                'likes_count', 
                'shares_count',
                'comments_count'
            ], 'analytics_engagement_score_index');

            // 11. AUDIT TRAIL INDEXES
            $table->index('created_by', 'analytics_created_by_index');
            $table->index('updated_by', 'analytics_updated_by_index');
            $table->index('approved_by', 'analytics_approved_by_index');
            $table->index(['deleted_at', 'analytics_status'], 'analytics_soft_delete_index');
            
            // 12. ACTION TYPE INDEXES (If you filter by action types)
            $table->index('action_type', 'analytics_action_type_index');
            $table->index(['action_type', 'created_at'], 'analytics_action_timeline_index');

            // 13. BOT VS HUMAN ANALYTICS
            $table->index(['bot_views', 'human_views'], 'analytics_audience_type_index');
            $table->index(['public_views', 'user_views'], 'analytics_visibility_index');

            // 14. REAL-TIME ANALYTICS INDEXES
            $table->index(['last_activity_at', 'trend_score'], 'analytics_realtime_trending_index');
            $table->index(['created_at', 'views_count'], 'analytics_growth_tracking_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('analytics');
    }
    
}