<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'stripe_invoice_id',
        'number',
        'status',
        'amount_paid',
        'amount_due',
        'subtotal',
        'tax',
        'total',
        'currency',
        'description',
        'period_start',
        'period_end',
        'invoice_pdf',
        'hosted_invoice_url',
        'line_items',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'invoice_pdf' => 'datetime',
            'hosted_invoice_url' => 'datetime',
            'line_items' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the billable model (tenant).
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the subscription for this invoice.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Check if the invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if the invoice is open (unpaid).
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if the invoice is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the invoice is voided.
     */
    public function isVoided(): bool
    {
        return $this->status === 'void';
    }

    /**
     * Get the formatted total amount.
     */
    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format($this->total, 2);
    }

    /**
     * Get the formatted amount due.
     */
    public function getFormattedAmountDueAttribute(): string
    {
        return '$' . number_format($this->amount_due, 2);
    }

    /**
     * Get the formatted amount paid.
     */
    public function getFormattedAmountPaidAttribute(): string
    {
        return '$' . number_format($this->amount_paid, 2);
    }

    /**
     * Scope to get paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope to get open (unpaid) invoices.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope to get voided invoices.
     */
    public function scopeVoided($query)
    {
        return $query->where('status', 'void');
    }
}