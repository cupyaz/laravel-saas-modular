<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModuleReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'module_id',
        'user_id',
        'tenant_id',
        'rating',
        'title',
        'content',
        'pros',
        'cons',
        'module_version',
        'usage_duration',
        'use_case',
        'recommendation',
        'is_verified_purchase',
        'is_featured',
        'is_approved',
        'approved_by',
        'approved_at',
        'helpful_count',
        'not_helpful_count',
        'reply_count',
        'tags',
        'metadata'
    ];

    protected $casts = [
        'pros' => 'array',
        'cons' => 'array',
        'tags' => 'array',
        'metadata' => 'array',
        'is_verified_purchase' => 'boolean',
        'is_featured' => 'boolean',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'rating' => 'decimal:1'
    ];

    // Rating Constants
    public const MIN_RATING = 1;
    public const MAX_RATING = 5;

    // Recommendation Types
    public const RECOMMENDATION_YES = 'yes';
    public const RECOMMENDATION_NO = 'no';
    public const RECOMMENDATION_MAYBE = 'maybe';

    // Usage Duration Options
    public const DURATION_LESS_THAN_WEEK = 'less_than_week';
    public const DURATION_WEEK_TO_MONTH = 'week_to_month';
    public const DURATION_MONTH_TO_THREE_MONTHS = 'month_to_three_months';
    public const DURATION_THREE_TO_SIX_MONTHS = 'three_to_six_months';
    public const DURATION_SIX_MONTHS_TO_YEAR = 'six_months_to_year';
    public const DURATION_MORE_THAN_YEAR = 'more_than_year';

    /**
     * Get the module this review belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who wrote this review.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant this review belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who approved this review.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get replies to this review.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ModuleReviewReply::class, 'review_id');
    }

    /**
     * Scope to get only approved reviews.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope to get only featured reviews.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to get only verified purchase reviews.
     */
    public function scopeVerifiedPurchase($query)
    {
        return $query->where('is_verified_purchase', true);
    }

    /**
     * Scope to filter by rating.
     */
    public function scopeByRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * Scope to filter by minimum rating.
     */
    public function scopeMinRating($query, int $minRating)
    {
        return $query->where('rating', '>=', $minRating);
    }

    /**
     * Scope to filter by module version.
     */
    public function scopeForVersion($query, string $version)
    {
        return $query->where('module_version', $version);
    }

    /**
     * Scope to order by helpfulness.
     */
    public function scopeByHelpfulness($query)
    {
        return $query->orderByRaw('(helpful_count - not_helpful_count) DESC');
    }

    /**
     * Scope to search reviews by content.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('content', 'like', "%{$search}%")
              ->orWhereJsonContains('pros', $search)
              ->orWhereJsonContains('cons', $search);
        });
    }

    /**
     * Check if review is positive (rating >= 4).
     */
    public function isPositive(): bool
    {
        return $this->rating >= 4;
    }

    /**
     * Check if review is negative (rating <= 2).
     */
    public function isNegative(): bool
    {
        return $this->rating <= 2;
    }

    /**
     * Check if review is neutral (rating = 3).
     */
    public function isNeutral(): bool
    {
        return $this->rating == 3;
    }

    /**
     * Get rating as stars for display.
     */
    public function getStarsDisplay(): string
    {
        $fullStars = floor($this->rating);
        $halfStar = ($this->rating - $fullStars) >= 0.5;
        
        $stars = str_repeat('★', $fullStars);
        if ($halfStar) {
            $stars .= '☆';
            $fullStars++;
        }
        $stars .= str_repeat('☆', 5 - $fullStars);
        
        return $stars;
    }

    /**
     * Get helpfulness score.
     */
    public function getHelpfulnessScore(): int
    {
        return $this->helpful_count - $this->not_helpful_count;
    }

    /**
     * Get helpfulness percentage.
     */
    public function getHelpfulnessPercentage(): float
    {
        $total = $this->helpful_count + $this->not_helpful_count;
        
        if ($total === 0) {
            return 0;
        }

        return ($this->helpful_count / $total) * 100;
    }

    /**
     * Mark review as helpful by user.
     */
    public function markHelpful(User $user): void
    {
        // Check if user already voted
        $existingVote = ModuleReviewVote::where([
            'review_id' => $this->id,
            'user_id' => $user->id
        ])->first();

        if ($existingVote) {
            if ($existingVote->is_helpful) {
                return; // Already marked as helpful
            }
            
            // Change vote from not helpful to helpful
            $existingVote->update(['is_helpful' => true]);
            $this->decrement('not_helpful_count');
        } else {
            // Create new helpful vote
            ModuleReviewVote::create([
                'review_id' => $this->id,
                'user_id' => $user->id,
                'is_helpful' => true
            ]);
        }

        $this->increment('helpful_count');
    }

    /**
     * Mark review as not helpful by user.
     */
    public function markNotHelpful(User $user): void
    {
        $existingVote = ModuleReviewVote::where([
            'review_id' => $this->id,
            'user_id' => $user->id
        ])->first();

        if ($existingVote) {
            if (!$existingVote->is_helpful) {
                return; // Already marked as not helpful
            }
            
            // Change vote from helpful to not helpful
            $existingVote->update(['is_helpful' => false]);
            $this->decrement('helpful_count');
        } else {
            // Create new not helpful vote
            ModuleReviewVote::create([
                'review_id' => $this->id,
                'user_id' => $user->id,
                'is_helpful' => false
            ]);
        }

        $this->increment('not_helpful_count');
    }

    /**
     * Approve the review.
     */
    public function approve(User $approver): void
    {
        $this->update([
            'is_approved' => true,
            'approved_by' => $approver->id,
            'approved_at' => now()
        ]);

        // Update module rating after approval
        $this->module->updateRating();
    }

    /**
     * Reject the review.
     */
    public function reject(): void
    {
        $this->update([
            'is_approved' => false,
            'approved_by' => null,
            'approved_at' => null
        ]);
    }

    /**
     * Feature the review.
     */
    public function feature(): void
    {
        $this->update(['is_featured' => true]);
    }

    /**
     * Unfeature the review.
     */
    public function unfeature(): void
    {
        $this->update(['is_featured' => false]);
    }

    /**
     * Get usage duration display text.
     */
    public function getUsageDurationText(): string
    {
        return match($this->usage_duration) {
            self::DURATION_LESS_THAN_WEEK => 'Less than a week',
            self::DURATION_WEEK_TO_MONTH => '1 week to 1 month',
            self::DURATION_MONTH_TO_THREE_MONTHS => '1-3 months',
            self::DURATION_THREE_TO_SIX_MONTHS => '3-6 months',
            self::DURATION_SIX_MONTHS_TO_YEAR => '6 months to 1 year',
            self::DURATION_MORE_THAN_YEAR => 'More than 1 year',
            default => 'Not specified'
        };
    }

    /**
     * Get recommendation display text.
     */
    public function getRecommendationText(): string
    {
        return match($this->recommendation) {
            self::RECOMMENDATION_YES => 'Yes, I recommend this module',
            self::RECOMMENDATION_NO => 'No, I do not recommend this module',
            self::RECOMMENDATION_MAYBE => 'Maybe, it depends on your needs',
            default => 'No recommendation provided'
        };
    }

    /**
     * Get recommendation badge color.
     */
    public function getRecommendationBadgeColor(): string
    {
        return match($this->recommendation) {
            self::RECOMMENDATION_YES => 'green',
            self::RECOMMENDATION_NO => 'red',
            self::RECOMMENDATION_MAYBE => 'yellow',
            default => 'gray'
        };
    }

    /**
     * Get review age in days.
     */
    public function getAge(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Check if review is recent (less than 30 days).
     */
    public function isRecent(): bool
    {
        return $this->getAge() <= 30;
    }

    /**
     * Get review summary for display.
     */
    public function getSummary(int $length = 150): string
    {
        $content = strip_tags($this->content);
        
        if (strlen($content) <= $length) {
            return $content;
        }

        return substr($content, 0, $length) . '...';
    }

    /**
     * Check if user can edit this review.
     */
    public function canEdit(User $user): bool
    {
        // User can edit own review within 24 hours if not approved yet
        return $this->user_id === $user->id && 
               !$this->is_approved && 
               $this->created_at->diffInHours(now()) <= 24;
    }

    /**
     * Check if user can delete this review.
     */
    public function canDelete(User $user): bool
    {
        // User can delete own review, or admin can delete any review
        return $this->user_id === $user->id || $user->isAdmin();
    }

    /**
     * Validate review content.
     */
    public static function validateReviewContent(array $data): array
    {
        $errors = [];

        // Rating validation
        if (!isset($data['rating']) || $data['rating'] < self::MIN_RATING || $data['rating'] > self::MAX_RATING) {
            $errors['rating'] = 'Rating must be between ' . self::MIN_RATING . ' and ' . self::MAX_RATING;
        }

        // Title validation
        if (!isset($data['title']) || strlen($data['title']) < 5) {
            $errors['title'] = 'Title must be at least 5 characters long';
        }

        // Content validation
        if (!isset($data['content']) || strlen($data['content']) < 20) {
            $errors['content'] = 'Review content must be at least 20 characters long';
        }

        return $errors;
    }
}

// Supporting model for review votes
class ModuleReviewVote extends Model
{
    protected $fillable = [
        'review_id',
        'user_id',
        'is_helpful'
    ];

    protected $casts = [
        'is_helpful' => 'boolean'
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ModuleReview::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

// Supporting model for review replies
class ModuleReviewReply extends Model
{
    protected $fillable = [
        'review_id',
        'user_id',
        'content',
        'is_official',
        'is_approved'
    ];

    protected $casts = [
        'is_official' => 'boolean',
        'is_approved' => 'boolean'
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ModuleReview::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}