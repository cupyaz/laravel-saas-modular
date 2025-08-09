<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'date_of_birth',
        'gender',
        'company',
        'job_title',
        'bio',
        'country',
        'timezone',
        'avatar',
        'gdpr_consent',
        'gdpr_consent_at',
        'gdpr_consent_ip',
        'marketing_consent',
        'marketing_consent_at',
        'registration_ip',
        'registration_user_agent',
        'is_active',
        'onboarding_completed',
        'preferences',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_token',
        'registration_ip',
        'registration_user_agent',
        'gdpr_consent_ip',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_sent_at' => 'datetime',
            'gdpr_consent_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'date_of_birth' => 'date',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'gdpr_consent' => 'boolean',
            'marketing_consent' => 'boolean',
            'onboarding_completed' => 'boolean',
            'preferences' => 'array',
        ];
    }

    /**
     * Get the user's security logs.
     */
    public function securityLogs(): HasMany
    {
        return $this->hasMany(SecurityLog::class);
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return "{$this->first_name} {$this->last_name}";
        }
        
        return $this->name ?? '';
    }

    /**
     * Check if the user has given GDPR consent.
     */
    public function hasGdprConsent(): bool
    {
        return $this->gdpr_consent && $this->gdpr_consent_at;
    }

    /**
     * Check if the user requires GDPR consent (EU users).
     */
    public function requiresGdprConsent(): bool
    {
        $euCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
        ];
        
        return in_array($this->country, $euCountries);
    }

    /**
     * Mark the user's GDPR consent.
     */
    public function giveGdprConsent(string $ipAddress): void
    {
        $this->update([
            'gdpr_consent' => true,
            'gdpr_consent_at' => now(),
            'gdpr_consent_ip' => $ipAddress,
        ]);
    }

    /**
     * Check if the user is locked due to failed login attempts.
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Lock the user account for a specified duration.
     */
    public function lockAccount(int $minutes = 30): void
    {
        $this->update([
            'locked_until' => now()->addMinutes($minutes),
        ]);
    }

    /**
     * Reset failed login attempts.
     */
    public function resetFailedLoginAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Increment failed login attempts.
     */
    public function incrementFailedLoginAttempts(): void
    {
        $attempts = $this->failed_login_attempts + 1;
        
        $this->update(['failed_login_attempts' => $attempts]);
        
        // Lock account after 5 failed attempts
        if ($attempts >= 5) {
            $this->lockAccount();
        }
    }

    /**
     * Check if onboarding is completed.
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed;
    }

    /**
     * Mark onboarding as completed.
     */
    public function completeOnboarding(): void
    {
        $this->update(['onboarding_completed' => true]);
    }

    /**
     * Get the tenants that belong to the user.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Check if user belongs to a tenant.
     */
    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenants()->where('tenant_id', $tenant->id)->exists();
    }

    /**
     * Get user's role in a specific tenant.
     */
    public function roleInTenant(Tenant $tenant): ?string
    {
        $pivot = $this->tenants()->where('tenant_id', $tenant->id)->first()?->pivot;
        
        return $pivot?->role;
    }

    /**
     * Check if user has a specific role in a tenant.
     */
    public function hasRoleInTenant(Tenant $tenant, string $role): bool
    {
        return $this->roleInTenant($tenant) === $role;
    }

    /**
     * Check if user is owner of a tenant.
     */
    public function isOwnerOf(Tenant $tenant): bool
    {
        return $this->hasRoleInTenant($tenant, 'owner');
    }

    /**
     * Get user's active tenants.
     */
    public function activeTenants(): BelongsToMany
    {
        return $this->tenants()->where('tenants.is_active', true);
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}