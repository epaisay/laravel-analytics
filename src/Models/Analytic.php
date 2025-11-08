<?php

namespace Epaisay\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Ramsey\Uuid\Uuid;

class Analytic extends Model
{
    use LogsActivity, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Polymorphic relationship: analyticable (any model that owns analytics)
     */
    public function analyticable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relationship with the user (if authenticated)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    // --- Audit Relationships ---
    public function createdBy(): BelongsTo { 
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by'); 
    }
    
    public function updatedBy(): BelongsTo { 
        return $this->belongsTo(config('auth.providers.users.model'), 'updated_by'); 
    }
    
    public function deletedBy(): BelongsTo { 
        return $this->belongsTo(config('auth.providers.users.model'), 'deleted_by'); 
    }
    
    public function approvedBy(): BelongsTo { 
        return $this->belongsTo(config('auth.providers.users.model'), 'approved_by'); 
    }
    
    public function rejectedBy(): BelongsTo { 
        return $this->belongsTo(config('auth.providers.users.model'), 'rejected_by'); 
    }
    
    public function readBy(): BelongsTo { 
        return $this->belongsTo(config('auth.providers.users.model'), 'read_by'); 
    }
    
    public function restoredBy(): BelongsTo { 
        return $this->belongsTo(config('auth.providers.users.model'), 'restored_by'); 
    }

    /**
     * Fillable attributes
     */
    protected $fillable = [
        'user_id',
        'visitor_token',
        'session_id',
        'analyticable_id',
        'analyticable_type',
        'ip_address',
        'action_type',
        'request_path',

        // Engagement metrics
        'views_count',
        'unique_viewers',
        'user_views',
        'public_views',
        'bot_views',
        'human_views',
        'impressions_count',
        'likes_count',
        'shares_count',
        'votes_count',
        'follows_count',
        'replies_count',
        'complaints_count',
        'bookmarks_count',
        'clicks_count',
        'comments_count',
        'messages_count',
        'chats_count',
        'contacts_count',
        'wishlists_count',
        'listings_count',
        'subscriptions_count',
        'users_count',
        'sellers_count',
        'cartitems_count',
        'checkouts_count',
        'payments_count',
        'orders_count',
        'brands_count',
        'shops_count',
        'articles_count',
        'posts_count',
        'video_count',
        'reaction_counts',
        'contributors_count',

        // Scores
        'click_through_rate',
        'trend_score',

        // State
        'last_activity_at',
        'analytics_status',
        'analytics_lock',

        // Audit timestamp fields
        'read_at',
        'approved_at',
        'rejected_at',
        'restored_at',

        // Audit user fields
        'created_by',
        'updated_by',
        'deleted_by',
        'read_by',
        'approved_by',
        'rejected_by',
        'restored_by',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'analytics_status' => 'boolean',
        'analytics_lock' => 'boolean',
        'last_activity_at' => 'datetime',
        'click_through_rate' => 'decimal:2',
        'trend_score' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'read_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'restored_at' => 'datetime',

        // Common integer counts
        'views_count' => 'integer',
        'unique_viewers' => 'integer',
        'user_views' => 'integer',
        'public_views' => 'integer',
        'bot_views' => 'integer',
        'human_views' => 'integer',
        'impressions_count' => 'integer',
        'likes_count' => 'integer',
        'shares_count' => 'integer',
        'votes_count' => 'integer',
        'follows_count' => 'integer',
        'replies_count' => 'integer',
        'complaints_count' => 'integer',
        'bookmarks_count' => 'integer',
        'clicks_count' => 'integer',
        'comments_count' => 'integer',
        'messages_count' => 'integer',
        'chats_count' => 'integer',
        'contacts_count' => 'integer',
        'wishlists_count' => 'integer',
        'listings_count' => 'integer',
        'subscriptions_count' => 'integer',
        'users_count' => 'integer',
        'sellers_count' => 'integer',
        'cartitems_count' => 'integer',
        'checkouts_count' => 'integer',
        'payments_count' => 'integer',
        'orders_count' => 'integer',
        'brands_count' => 'integer',
        'shops_count' => 'integer',
        'articles_count' => 'integer',
        'posts_count' => 'integer',
        'video_count' => 'integer',
        'reaction_counts' => 'integer',
        'contributors_count' => 'integer',
    ];

    /**
     * Activity Log Configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Boot method — handles UUIDs and created_by / updated_by tracking
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Uuid::uuid4()->toString();
            }
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function ($model) {
            if (auth()->check() && !$model->isForceDeleting()) {
                $model->deleted_by = auth()->id();
                $model->save();
            }
        });
    }

    // ===========================
    //       QUERY SCOPES
    // ===========================

    public function scopeActive($query) { 
        return $query->where('analytics_status', true); 
    }
    
    public function scopeLocked($query) { 
        return $query->where('analytics_lock', true); 
    }
    
    public function scopeAction($query, string $action) { 
        return $query->where('action_type', $action); 
    }
    
    public function scopeForModel($query, string $modelClass) { 
        return $query->where('analyticable_type', $modelClass); 
    }
    
    public function scopeForUser($query, string $userId) { 
        return $query->where('user_id', $userId); 
    }
    
    public function scopeForVisitor($query, string $visitorToken) { 
        return $query->where('visitor_token', $visitorToken); 
    }
    
    public function scopeForSession($query, string $sessionId) { 
        return $query->where('session_id', $sessionId); 
    }
    
    public function scopeTrending($query, float $minScore = 10.0) {
        return $query->where('trend_score', '>=', $minScore)
                     ->orderBy('trend_score', 'desc');
    }
    
    public function scopeRecentlyActive($query, int $days = 7) {
        return $query->where('last_activity_at', '>=', now()->subDays($days));
    }
    
    public function scopeByIp($query, string $ipAddress) {
        return $query->where('ip_address', $ipAddress);
    }

    // ===========================
    //   COMPUTED ATTRIBUTES
    // ===========================

    public function getEngagementScoreAttribute(): float
    {
        $weights = config('analytics.engagement_weights', [
            'views' => 0.15,
            'likes' => 0.25,
            'shares' => 0.20,
            'clicks' => 0.15,
            'replies' => 0.10,
            'follows' => 0.10,
            'bookmarks' => 0.05,
        ]);

        return (
            ($this->views_count * $weights['views']) +
            ($this->likes_count * $weights['likes']) +
            ($this->shares_count * $weights['shares']) +
            ($this->clicks_count * $weights['clicks']) +
            ($this->replies_count * $weights['replies']) +
            ($this->follows_count * $weights['follows']) +
            ($this->bookmarks_count * $weights['bookmarks'])
        );
    }

    public function getEngagementRateAttribute(): float
    {
        if ($this->views_count === 0) {
            return 0.0;
        }
        return ($this->engagement_score / $this->views_count) * 100;
    }

    public function getConversionRateAttribute(): float
    {
        if ($this->views_count === 0) {
            return 0.0;
        }
        return ($this->orders_count / $this->views_count) * 100;
    }

    // ===========================
    //     UTILITY METHODS
    // ===========================

    public function isActive(): bool { 
        return $this->analytics_status === true; 
    }
    
    public function isLocked(): bool { 
        return $this->analytics_lock === true; 
    }

    public function isAuthenticatedView(): bool {
        return !is_null($this->user_id);
    }

    public function isGuestView(): bool {
        return is_null($this->user_id) && !is_null($this->visitor_token);
    }

    public function incrementViews(int $count = 1): bool
    {
        return $this->increment('views_count', $count);
    }

    public function incrementUniqueViewers(int $count = 1): bool
    {
        return $this->increment('unique_viewers', $count);
    }

    public function incrementHumanViews(int $count = 1): bool
    {
        return $this->increment('human_views', $count);
    }

    public function incrementBotViews(int $count = 1): bool
    {
        return $this->increment('bot_views', $count);
    }

    public function touchLastActivity(): bool
    {
        return $this->update(['last_activity_at' => now()]);
    }

    public function resetCounters(): bool
    {
        $resetFields = array_fill_keys([
            'views_count', 'unique_viewers', 'user_views', 'public_views', 'bot_views', 'human_views',
            'impressions_count', 'likes_count', 'shares_count', 'votes_count', 'follows_count',
            'replies_count', 'complaints_count', 'bookmarks_count', 'clicks_count', 'comments_count',
            'messages_count', 'chats_count', 'contacts_count', 'wishlists_count', 'listings_count',
            'subscriptions_count', 'users_count', 'sellers_count', 'cartitems_count', 'checkouts_count',
            'payments_count', 'orders_count', 'brands_count', 'shops_count', 'articles_count',
            'posts_count', 'video_count', 'reaction_counts', 'contributors_count'
        ], 0);

        $resetFields['click_through_rate'] = 0;
        $resetFields['trend_score'] = 0;

        return $this->update($resetFields);
    }

    public function getSummary(): array
    {
        return [
            'total_views' => $this->views_count,
            'unique_viewers' => $this->unique_viewers,
            'human_views' => $this->human_views,
            'bot_views' => $this->bot_views,
            'engagement_rate' => $this->engagement_rate,
            'conversion_rate' => $this->conversion_rate,
            'total_engagement' => $this->likes_count + $this->shares_count + $this->comments_count + $this->replies_count,
            'click_through_rate' => $this->click_through_rate,
            'trend_score' => $this->trend_score,
            'last_activity' => $this->last_activity_at,
            'is_authenticated' => $this->isAuthenticatedView(),
        ];
    }

    /**
     * Mark as read
     */
    public function markAsRead(): bool
    {
        if (auth()->check()) {
            return $this->update([
                'read_at' => now(),
                'read_by' => auth()->id()
            ]);
        }
        
        return $this->update(['read_at' => now()]);
    }

    /**
     * Mark as approved
     */
    public function markAsApproved(): bool
    {
        if (auth()->check()) {
            return $this->update([
                'approved_at' => now(),
                'approved_by' => auth()->id()
            ]);
        }
        
        return $this->update(['approved_at' => now()]);
    }
}