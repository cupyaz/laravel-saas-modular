<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityLog extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'event_type',
        'status',
        'ip_address',
        'user_agent',
        'metadata',
        'session_id',
        'email',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that owns the security log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a security event.
     */
    public static function logEvent(
        string $eventType,
        string $status,
        ?int $userId = null,
        ?string $email = null,
        array $metadata = [],
        ?string $reason = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'status' => $status,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
            'session_id' => session()->getId(),
            'email' => $email,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Log a registration attempt.
     */
    public static function logRegistrationAttempt(
        string $email,
        string $status = 'success',
        ?int $userId = null,
        array $metadata = [],
        ?string $reason = null
    ): self {
        return static::logEvent(
            'registration_attempt',
            $status,
            $userId,
            $email,
            $metadata,
            $reason
        );
    }

    /**
     * Log an email verification attempt.
     */
    public static function logEmailVerification(
        int $userId,
        string $status = 'success',
        array $metadata = [],
        ?string $reason = null
    ): self {
        return static::logEvent(
            'email_verification',
            $status,
            $userId,
            null,
            $metadata,
            $reason
        );
    }

    /**
     * Log a login attempt.
     */
    public static function logLoginAttempt(
        string $email,
        string $status = 'success',
        ?int $userId = null,
        array $metadata = [],
        ?string $reason = null
    ): self {
        return static::logEvent(
            'login_attempt',
            $status,
            $userId,
            $email,
            $metadata,
            $reason
        );
    }
}