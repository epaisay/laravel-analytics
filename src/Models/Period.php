<?php

namespace Epaisay\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Ramsey\Uuid\Uuid;
use Carbon\Carbon;

class Period extends Model
{
    use LogsActivity, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Period granularity options
     */
    const GRANULARITY_DAILY = 'daily';
    const GRANULARITY_WEEKLY = 'weekly';
    const GRANULARITY_MONTHLY = 'monthly';
    const GRANULARITY_YEARLY = 'yearly';

    /**
     * Get the analytic that owns this period
     */
    public function analytic(): BelongsTo
    {
        return $this->belongsTo(Analytic::class, 'analytic_id');
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
        'analytic_id',
        'analytic_type',
        'period_granularity',
        'period_start_date',
        'period_end_date',
        'value',
        'growth_rate',
        'previous_value',
        'period_status',
        'period_lock',
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
        'period_status' => 'boolean',
        'period_lock' => 'boolean',
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'value' => 'integer',
        'previous_value' => 'integer',
        'growth_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'read_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'restored_at' => 'datetime',
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

            // Set created_by if user is authenticated
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }

            // Set default values if not provided
            if (!isset($model->period_status)) {
                $model->period_status = true;
            }

            if (!isset($model->period_lock)) {
                $model->period_lock = false;
            }

            // Calculate period_end_date if not provided
            if (empty($model->period_end_date) && $model->period_start_date) {
                $model->period_end_date = $model->calculatePeriodEndDate();
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
        return $query->where('period_status', true);
    }

    public function scopeLocked($query)
    {
        return $query->where('period_lock', true);
    }

    public function scopeGranularity($query, string $granularity)
    {
        return $query->where('period_granularity', $granularity);
    }

    public function scopeDaily($query)
    {
        return $query->where('period_granularity', self::GRANULARITY_DAILY);
    }

    public function scopeWeekly($query)
    {
        return $query->where('period_granularity', self::GRANULARITY_WEEKLY);
    }

    public function scopeMonthly($query)
    {
        return $query->where('period_granularity', self::GRANULARITY_MONTHLY);
    }

    public function scopeYearly($query)
    {
        return $query->where('period_granularity', self::GRANULARITY_YEARLY);
    }

    public function scopeAnalyticType($query, string $type)
    {
        return $query->where('analytic_type', $type);
    }

    public function scopeForAnalytic($query, string $analyticId)
    {
        return $query->where('analytic_id', $analyticId);
    }

    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        $query->where('period_start_date', '>=', $startDate);
        
        if ($endDate) {
            $query->where('period_start_date', '<=', $endDate);
        }
        
        return $query;
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('period_start_date', [$startDate, $endDate])
              ->orWhereBetween('period_end_date', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('period_start_date', '<=', $startDate)
                     ->where('period_end_date', '>=', $endDate);
              });
        });
    }

    public function scopeRecent($query, int $count = 10)
    {
        return $query->orderBy('period_start_date', 'desc')->limit($count);
    }

    public function scopeTrending($query, float $minGrowthRate = 10.0)
    {
        return $query->where('growth_rate', '>=', $minGrowthRate)
                     ->orderBy('growth_rate', 'desc');
    }

    public function scopeDeclining($query, float $maxGrowthRate = -5.0)
    {
        return $query->where('growth_rate', '<=', $maxGrowthRate)
                     ->orderBy('growth_rate', 'asc');
    }

    // ===========================
    //   COMPUTED ATTRIBUTES
    // ===========================

    public function getPeriodLabelAttribute(): string
    {
        $start = Carbon::parse($this->period_start_date)->format('M j, Y');
        
        if ($this->period_end_date && $this->period_start_date != $this->period_end_date) {
            $end = Carbon::parse($this->period_end_date)->format('M j, Y');
            return "{$start} - {$end}";
        }
        
        return $start;
    }

    public function getFormattedValueAttribute(): string
    {
        return number_format($this->value);
    }

    public function getFormattedGrowthRateAttribute(): string
    {
        if ($this->growth_rate === null) {
            return 'N/A';
        }

        $sign = $this->growth_rate > 0 ? '+' : '';
        return $sign . number_format($this->growth_rate, 2) . '%';
    }

    public function getGrowthIndicatorAttribute(): string
    {
        if ($this->growth_rate === null) {
            return 'neutral';
        }
        
        return $this->growth_rate > 0 ? 'positive' : ($this->growth_rate < 0 ? 'negative' : 'neutral');
    }

    public function getDurationInDaysAttribute(): int
    {
        if (!$this->period_start_date || !$this->period_end_date) {
            return 1;
        }
        
        $start = Carbon::parse($this->period_start_date);
        $end = Carbon::parse($this->period_end_date ?? $this->period_start_date);
        
        return $start->diffInDays($end) + 1;
    }

    public function getIsCurrentPeriodAttribute(): bool
    {
        $now = Carbon::now();
        $start = Carbon::parse($this->period_start_date);
        $end = Carbon::parse($this->period_end_date ?? $this->period_start_date);

        return $now->between($start, $end);
    }

    // ===========================
    //     UTILITY METHODS
    // ===========================

    public function isActive(): bool
    {
        return $this->period_status === true;
    }

    public function isLocked(): bool
    {
        return $this->period_lock === true;
    }

    public function calculatePeriodEndDate(): string
    {
        $startDate = Carbon::parse($this->period_start_date);

        return match ($this->period_granularity) {
            self::GRANULARITY_DAILY => $startDate->format('Y-m-d'),
            self::GRANULARITY_WEEKLY => $startDate->endOfWeek()->format('Y-m-d'),
            self::GRANULARITY_MONTHLY => $startDate->endOfMonth()->format('Y-m-d'),
            self::GRANULARITY_YEARLY => $startDate->endOfYear()->format('Y-m-d'),
            default => $startDate->format('Y-m-d'),
        };
    }

    public function calculateGrowthRate(): ?float
    {
        if ($this->previous_value === null || $this->previous_value === 0) {
            return null;
        }

        return (($this->value - $this->previous_value) / $this->previous_value) * 100;
    }

    public function updateGrowthRate(): bool
    {
        $growthRate = $this->calculateGrowthRate();
        
        return $this->update(['growth_rate' => $growthRate]);
    }

    public function incrementValue(int $amount = 1): bool
    {
        return $this->increment('value', $amount);
    }

    public function decrementValue(int $amount = 1): bool
    {
        return $this->decrement('value', $amount);
    }

    public function setValue(int $value): bool
    {
        return $this->update(['value' => $value]);
    }

    public function overlapsWith(Period $otherPeriod): bool
    {
        $thisStart = Carbon::parse($this->period_start_date);
        $thisEnd = Carbon::parse($this->period_end_date ?? $this->period_start_date);
        $otherStart = Carbon::parse($otherPeriod->period_start_date);
        $otherEnd = Carbon::parse($otherPeriod->period_end_date ?? $otherPeriod->period_start_date);

        return $thisStart <= $otherEnd && $thisEnd >= $otherStart;
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
     * Get period summary for analytics
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'analytic_type' => $this->analytic_type,
            'granularity' => $this->period_granularity,
            'period_label' => $this->period_label,
            'value' => $this->value,
            'formatted_value' => $this->formatted_value,
            'growth_rate' => $this->growth_rate,
            'formatted_growth_rate' => $this->formatted_growth_rate,
            'growth_indicator' => $this->growth_indicator,
            'duration_days' => $this->duration_in_days,
            'is_current_period' => $this->is_current_period,
            'start_date' => $this->period_start_date,
            'end_date' => $this->period_end_date,
        ];
    }

    /**
     * Get all available granularity options
     */
    public static function getGranularityOptions(): array
    {
        return [
            self::GRANULARITY_DAILY => 'Daily',
            self::GRANULARITY_WEEKLY => 'Weekly',
            self::GRANULARITY_MONTHLY => 'Monthly',
            self::GRANULARITY_YEARLY => 'Yearly',
        ];
    }

    /**
     * Get analytic type options (common metrics)
     */
    public static function getAnalyticTypeOptions(): array
    {
        return [
            'views_count' => 'Views',
            'unique_viewers' => 'Unique Viewers',
            'impressions_count' => 'Impressions',
            'likes_count' => 'Likes',
            'shares_count' => 'Shares',
            'comments_count' => 'Comments',
            'clicks_count' => 'Clicks',
            'conversion_rate' => 'Conversion Rate',
            'engagement_rate' => 'Engagement Rate',
        ];
    }
}