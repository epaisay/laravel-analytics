<?php

namespace Epaisay\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Ramsey\Uuid\Uuid;

class View extends Model
{
    use LogsActivity, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Relationships
     */
    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function analytic(): BelongsTo
    {
        return $this->belongsTo(Analytic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    // Audit Relationships
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'updated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'deleted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'rejected_by');
    }

    public function readBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'read_by');
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'restored_by');
    }

    /**
     * Fillable attributes
     */
    protected $fillable = [
        'viewable_id',
        'viewable_type',
        'analytic_id',
        'user_id',
        'visitor_token',
        'ip_address',
        'session_id',
        'visited_at',
        'action_type',
        'request_path',
        'method',
        'request',
        'url',
        'referer',
        'page_url',
        'languages',
        'useragent',
        'headers',
        'device',
        'device_type',
        'platform',
        'os',
        'browser',
        'browser_version',
        'is_robot',
        'robot_category',
        'robot_name',
        'country',
        'country_code',
        'region',
        'region_name',
        'city',
        'zip',
        'lat',
        'lon',
        'timezone',
        'isp',
        'org',
        'as_name',
        'view_status',
        'view_lock',
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

    /**
     * Casts
     */
    protected $casts = [
        'view_status' => 'boolean',
        'view_lock' => 'boolean',
        'is_robot' => 'boolean',
        'visited_at' => 'datetime',
        'read_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'restored_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'lat' => 'decimal:8',
        'lon' => 'decimal:8',
        'headers' => 'array',
        'languages' => 'array',
        'request' => 'array',
    ];

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Boot method - handles UUIDs and default values
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Uuid::uuid4()->toString();
            }

            // Set default visited_at if not provided
            if (empty($model->visited_at)) {
                $model->visited_at = now();
            }

            // Set default values if not provided
            if (!isset($model->view_status)) {
                $model->view_status = true;
            }

            if (!isset($model->view_lock)) {
                $model->view_lock = false;
            }

            if (!isset($model->is_robot)) {
                $model->is_robot = false;
            }

            // Set created_by if user is authenticated
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            // Set updated_by if user is authenticated
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function ($model) {
            // Set deleted_by if user is authenticated and not force deleting
            if (auth()->check() && !$model->isForceDeleting()) {
                $model->deleted_by = auth()->id();
                $model->save();
            }
        });
    }

    // ===========================
    //       QUERY SCOPES
    // ===========================

    public function scopeActive($query)
    {
        return $query->where('view_status', true);
    }

    public function scopeLocked($query)
    {
        return $query->where('view_lock', true);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('visited_at', today());
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('visited_at', '>=', now()->subDays($days));
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByVisitor($query, $visitorToken)
    {
        return $query->where('visitor_token', $visitorToken);
    }

    public function scopeBySession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeByIp($query, $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    public function scopeFromCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeFromCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    public function scopeBrowser($query, string $browser)
    {
        return $query->where('browser', $browser);
    }

    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeDevice($query, string $device)
    {
        return $query->where('device', $device);
    }

    public function scopeOperatingSystem($query, string $os)
    {
        return $query->where('os', $os);
    }

    public function scopeRobots($query)
    {
        return $query->where('is_robot', true);
    }

    public function scopeHumans($query)
    {
        return $query->where('is_robot', false);
    }

    public function scopeByRobotCategory($query, string $category)
    {
        return $query->where('robot_category', $category);
    }

    public function scopeByRobotName($query, string $name)
    {
        return $query->where('robot_name', $name);
    }

    public function scopeActionType($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    public function scopeWithinBounds($query, float $minLat, float $maxLat, float $minLon, float $maxLon)
    {
        return $query->whereBetween('lat', [$minLat, $maxLat])
                     ->whereBetween('lon', [$minLon, $maxLon]);
    }

    public function scopeHasGeolocation($query)
    {
        return $query->whereNotNull('lat')
                     ->whereNotNull('lon')
                     ->whereNotNull('country');
    }

    // ===========================
    //   COMPUTED ATTRIBUTES
    // ===========================

    public function getFormattedLocationAttribute(): string
    {
        $parts = [];
        if ($this->city) $parts[] = $this->city;
        if ($this->region_name) $parts[] = $this->region_name;
        if ($this->country) $parts[] = $this->country;

        return $parts ? implode(', ', $parts) : 'Unknown Location';
    }

    public function getFormattedBrowserAttribute(): string
    {
        if ($this->browser && $this->browser_version) {
            return "{$this->browser} {$this->browser_version}";
        }

        return $this->browser ?? 'Unknown Browser';
    }

    public function getDevicePlatformAttribute(): string
    {
        $parts = [];
        if ($this->device_type) $parts[] = $this->device_type;
        if ($this->device) $parts[] = $this->device;
        if ($this->os) $parts[] = $this->os;

        return $parts ? implode(' · ', $parts) : 'Unknown Device';
    }

    public function getIsAuthenticatedAttribute(): bool
    {
        return !is_null($this->user_id);
    }

    public function getIsGuestAttribute(): bool
    {
        return is_null($this->user_id) && !is_null($this->visitor_token);
    }

    public function getIsBotAttribute(): bool
    {
        return $this->is_robot === true;
    }

    // ===========================
    //     UTILITY METHODS
    // ===========================

    public function isActive(): bool
    {
        return $this->view_status === true;
    }

    public function isLocked(): bool
    {
        return $this->view_lock === true;
    }

    public function hasGeolocation(): bool
    {
        return !is_null($this->lat) && !is_null($this->lon) && !is_null($this->country);
    }

    public function distanceFrom(float $lat, float $lon): ?float
    {
        if (!$this->hasGeolocation()) {
            return null;
        }

        $earthRadius = 6371; // km

        $latFrom = deg2rad($this->lat);
        $lonFrom = deg2rad($this->lon);
        $latTo = deg2rad($lat);
        $lonTo = deg2rad($lon);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return round($angle * $earthRadius, 2);
    }

    public function getPageNameAttribute(): string
    {
        if ($this->page_url) {
            $path = parse_url($this->page_url, PHP_URL_PATH);
            return $path && $path !== '/' ? basename($path) : 'Home';
        }

        if ($this->url) {
            $path = parse_url($this->url, PHP_URL_PATH);
            return $path && $path !== '/' ? basename($path) : 'Home';
        }

        return 'Unknown Page';
    }

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

    public function markAsRejected(): bool
    {
        if (auth()->check()) {
            return $this->update([
                'rejected_at' => now(),
                'rejected_by' => auth()->id()
            ]);
        }
        
        return $this->update(['rejected_at' => now()]);
    }

    /**
     * Get view summary for analytics
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'page' => $this->page_name,
            'location' => $this->formatted_location,
            'browser' => $this->formatted_browser,
            'device_platform' => $this->device_platform,
            'is_authenticated' => $this->is_authenticated,
            'is_bot' => $this->is_bot,
            'visited_at' => $this->visited_at,
            'ip_address' => $this->ip_address,
            'country_code' => $this->country_code,
            'user_agent' => $this->useragent,
        ];
    }
}