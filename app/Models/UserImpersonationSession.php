<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserImpersonationSession extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'admin_user_id',
        'impersonated_user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'reason',
        'started_at',
        'ended_at',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the admin user who initiated the impersonation.
     */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Get the user being impersonated.
     */
    public function impersonatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_user_id');
    }

    /**
     * Scope for active sessions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('ended_at');
    }

    /**
     * Scope for ended sessions.
     */
    public function scopeEnded($query)
    {
        return $query->where('is_active', false)->whereNotNull('ended_at');
    }

    /**
     * Scope for specific admin user.
     */
    public function scopeByAdmin($query, int $adminUserId)
    {
        return $query->where('admin_user_id', $adminUserId);
    }

    /**
     * Scope for specific impersonated user.
     */
    public function scopeByImpersonatedUser($query, int $userId)
    {
        return $query->where('impersonated_user_id', $userId);
    }

    /**
     * Get the duration of the session.
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->ended_at) {
            return 'Ongoing';
        }

        $duration = $this->started_at->diff($this->ended_at);
        
        $parts = [];
        if ($duration->h > 0) {
            $parts[] = $duration->h . ' hour' . ($duration->h > 1 ? 's' : '');
        }
        if ($duration->i > 0) {
            $parts[] = $duration->i . ' minute' . ($duration->i > 1 ? 's' : '');
        }
        if ($duration->s > 0 || empty($parts)) {
            $parts[] = $duration->s . ' second' . ($duration->s > 1 ? 's' : '');
        }

        return implode(', ', $parts);
    }

    /**
     * End the impersonation session.
     */
    public function end(): void
    {
        $this->update([
            'ended_at' => now(),
            'is_active' => false,
        ]);
    }

    /**
     * Check if session has expired (after 4 hours).
     */
    public function hasExpired(): bool
    {
        return $this->started_at->addHours(4)->isPast();
    }

    /**
     * Start a new impersonation session.
     */
    public static function start(
        int $adminUserId,
        int $impersonatedUserId,
        string $sessionId,
        string $reason
    ): self {
        // End any existing active sessions for this admin
        self::where('admin_user_id', $adminUserId)
            ->where('is_active', true)
            ->update([
                'ended_at' => now(),
                'is_active' => false,
            ]);

        return self::create([
            'admin_user_id' => $adminUserId,
            'impersonated_user_id' => $impersonatedUserId,
            'session_id' => $sessionId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason,
            'started_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Find active session by session ID.
     */
    public static function findActiveBySessionId(string $sessionId): ?self
    {
        return self::where('session_id', $sessionId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get session statistics for admin user.
     */
    public static function getStatsForAdmin(int $adminUserId): array
    {
        $sessions = self::where('admin_user_id', $adminUserId);
        
        return [
            'total_sessions' => $sessions->count(),
            'active_sessions' => $sessions->active()->count(),
            'total_duration_minutes' => $sessions->ended()
                ->get()
                ->sum(function ($session) {
                    return $session->started_at->diffInMinutes($session->ended_at);
                }),
            'last_session' => $sessions->latest('started_at')->first(),
        ];
    }

    /**
     * Clean up expired sessions.
     */
    public static function cleanupExpiredSessions(): int
    {
        $expiredSessions = self::where('is_active', true)
            ->where('started_at', '<', now()->subHours(4))
            ->get();

        foreach ($expiredSessions as $session) {
            $session->end();
        }

        return $expiredSessions->count();
    }
}