<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminBulkOperation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'admin_user_id',
        'operation_type',
        'target_model',
        'status',
        'total_records',
        'processed_records',
        'successful_records',
        'failed_records',
        'operation_parameters',
        'results',
        'error_message',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation_parameters' => 'array',
            'results' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Operation type constants
     */
    const TYPE_IMPORT = 'import';
    const TYPE_EXPORT = 'export';
    const TYPE_UPDATE = 'update';
    const TYPE_DELETE = 'delete';
    const TYPE_ACTIVATE = 'activate';
    const TYPE_DEACTIVATE = 'deactivate';
    const TYPE_SUSPEND = 'suspend';
    const TYPE_ASSIGN_ROLE = 'assign_role';
    const TYPE_REMOVE_ROLE = 'remove_role';
    const TYPE_RESET_PASSWORD = 'reset_password';
    const TYPE_SEND_EMAIL = 'send_email';

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Target model constants
     */
    const TARGET_USER = 'User';
    const TARGET_TENANT = 'Tenant';
    const TARGET_SUBSCRIPTION = 'Subscription';

    /**
     * Get the admin user who initiated the operation.
     */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Scope for specific status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for specific operation type.
     */
    public function scopeType($query, string $type)
    {
        return $query->where('operation_type', $type);
    }

    /**
     * Scope for specific target model.
     */
    public function scopeTargetModel($query, string $model)
    {
        return $query->where('target_model', $model);
    }

    /**
     * Scope for completed operations.
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_FAILED]);
    }

    /**
     * Scope for active operations.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Get the operation progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_records === 0) {
            return 0;
        }

        return round(($this->processed_records / $this->total_records) * 100, 2);
    }

    /**
     * Get the success rate percentage.
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->processed_records === 0) {
            return 0;
        }

        return round(($this->successful_records / $this->processed_records) * 100, 2);
    }

    /**
     * Get the operation duration.
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        $duration = $this->started_at->diff($this->completed_at);
        
        $parts = [];
        if ($duration->h > 0) {
            $parts[] = $duration->h . 'h';
        }
        if ($duration->i > 0) {
            $parts[] = $duration->i . 'm';
        }
        if ($duration->s > 0 || empty($parts)) {
            $parts[] = $duration->s . 's';
        }

        return implode(' ', $parts);
    }

    /**
     * Check if operation is completed.
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    /**
     * Check if operation is in progress.
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Start the operation.
     */
    public function start(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Complete the operation.
     */
    public function complete(array $results = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'results' => array_merge($this->results ?? [], $results),
        ]);
    }

    /**
     * Fail the operation.
     */
    public function fail(string $errorMessage, array $results = []): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error_message' => $errorMessage,
            'results' => array_merge($this->results ?? [], $results),
        ]);
    }

    /**
     * Cancel the operation.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Update progress.
     */
    public function updateProgress(int $processed, int $successful, int $failed, array $additionalResults = []): void
    {
        $this->update([
            'processed_records' => $processed,
            'successful_records' => $successful,
            'failed_records' => $failed,
            'results' => array_merge($this->results ?? [], $additionalResults),
        ]);
    }

    /**
     * Create a new bulk operation.
     */
    public static function create(array $attributes = []): self
    {
        $attributes = array_merge([
            'status' => self::STATUS_PENDING,
            'total_records' => 0,
            'processed_records' => 0,
            'successful_records' => 0,
            'failed_records' => 0,
        ], $attributes);

        return parent::create($attributes);
    }

    /**
     * Get operation type display name.
     */
    public function getTypeDisplayNameAttribute(): string
    {
        return match ($this->operation_type) {
            self::TYPE_IMPORT => 'Import',
            self::TYPE_EXPORT => 'Export',
            self::TYPE_UPDATE => 'Update',
            self::TYPE_DELETE => 'Delete',
            self::TYPE_ACTIVATE => 'Activate',
            self::TYPE_DEACTIVATE => 'Deactivate',
            self::TYPE_SUSPEND => 'Suspend',
            self::TYPE_ASSIGN_ROLE => 'Assign Role',
            self::TYPE_REMOVE_ROLE => 'Remove Role',
            self::TYPE_RESET_PASSWORD => 'Reset Password',
            self::TYPE_SEND_EMAIL => 'Send Email',
            default => ucwords(str_replace('_', ' ', $this->operation_type)),
        };
    }

    /**
     * Get status display name.
     */
    public function getStatusDisplayNameAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucwords($this->status),
        };
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_PROCESSING => 'blue',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED => 'red',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get recently completed operations.
     */
    public static function getRecentCompleted(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return self::completed()
            ->latest('completed_at')
            ->limit($limit)
            ->with('adminUser')
            ->get();
    }

    /**
     * Get operation statistics.
     */
    public static function getStatistics(): array
    {
        $stats = self::selectRaw('
            status,
            COUNT(*) as count,
            SUM(total_records) as total_records,
            SUM(successful_records) as successful_records,
            SUM(failed_records) as failed_records
        ')
        ->groupBy('status')
        ->get()
        ->keyBy('status');

        return [
            'total_operations' => self::count(),
            'pending' => $stats[self::STATUS_PENDING]->count ?? 0,
            'processing' => $stats[self::STATUS_PROCESSING]->count ?? 0,
            'completed' => $stats[self::STATUS_COMPLETED]->count ?? 0,
            'failed' => $stats[self::STATUS_FAILED]->count ?? 0,
            'total_records_processed' => $stats->sum('total_records'),
            'total_successful' => $stats->sum('successful_records'),
            'total_failed' => $stats->sum('failed_records'),
        ];
    }
}