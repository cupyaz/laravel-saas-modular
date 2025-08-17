<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ABTestAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'test_name',
        'variant_name',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    /**
     * Get the tenant assigned to this variant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the variant details.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ABTestVariant::class, 'variant_name', 'variant_name')
            ->where('test_name', $this->test_name);
    }

    /**
     * Scope for specific test.
     */
    public function scopeForTest($query, string $testName)
    {
        return $query->where('test_name', $testName);
    }

    /**
     * Scope for specific variant.
     */
    public function scopeForVariant($query, string $variantName)
    {
        return $query->where('variant_name', $variantName);
    }

    /**
     * Scope for recent assignments.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('assigned_at', '>=', now()->subDays($days));
    }
}