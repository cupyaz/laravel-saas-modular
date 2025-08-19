<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsReportExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'executed_by',
        'status',
        'started_at',
        'completed_at',
        'file_path',
        'file_size',
        'parameters',
        'result_data',
        'error_message',
        'execution_time',
        'rows_processed',
        'memory_used',
        'metadata'
    ];

    protected $casts = [
        'parameters' => 'array',
        'result_data' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    // Execution Status Constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the report this execution belongs to.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(AnalyticsReport::class, 'report_id');
    }

    /**
     * Get the user who executed this report.
     */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /**
     * Scope for completed executions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed executions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for running executions.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Check if execution is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if execution failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if execution is running.
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Mark execution as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now()
        ]);
    }

    /**
     * Mark execution as completed.
     */
    public function markAsCompleted(array $resultData = [], ?string $filePath = null, ?int $fileSize = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'result_data' => $resultData,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'execution_time' => $this->started_at ? $this->started_at->diffInSeconds(now()) : null
        ]);
    }

    /**
     * Mark execution as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error_message' => $errorMessage,
            'execution_time' => $this->started_at ? $this->started_at->diffInSeconds(now()) : null
        ]);
    }

    /**
     * Get execution duration in human readable format.
     */
    public function getDurationAttribute(): string
    {
        if (!$this->started_at || !$this->completed_at) {
            return 'N/A';
        }

        $seconds = $this->started_at->diffInSeconds($this->completed_at);
        
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        }
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSize(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get status badge color for UI.
     */
    public function getStatusBadgeColor(): string
    {
        return match($this->status) {
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED, self::STATUS_CANCELLED => 'red',
            self::STATUS_RUNNING => 'blue',
            self::STATUS_PENDING => 'yellow',
            default => 'gray'
        };
    }

    /**
     * Get human-readable status text.
     */
    public function getStatusText(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_RUNNING => 'Running',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status)
        };
    }
}