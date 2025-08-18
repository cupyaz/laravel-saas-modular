<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'version' => $this->version,
            'author' => $this->author,
            'category' => $this->category,
            'category_name' => $this->resource::CATEGORIES[$this->category] ?? $this->category,
            'license' => $this->license,
            'icon' => $this->icon_url,
            'screenshots' => $this->screenshots,
            'price' => $this->price,
            'price_formatted' => $this->price > 0 ? '€' . number_format($this->price / 100, 2) : 'Free',
            'is_free' => $this->price == 0,
            'rating' => $this->rating,
            'rating_stars' => $this->stars,
            'download_count' => $this->download_count,
            'download_count_formatted' => $this->formatDownloadCount(),
            'is_core' => $this->is_core,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->toISOString(),
            'repository_url' => $this->repository_url,
            'documentation_url' => $this->documentation_url,
            
            // Compatibility information
            'compatibility' => [
                'is_compatible' => $this->isCompatible(),
                'requirements' => $this->requirements,
                'issues' => $this->when($request->route('slug') === $this->slug, function () {
                    return $this->getCompatibilityIssues();
                }),
            ],

            // Configuration
            'config_schema' => $this->when($request->route('slug') === $this->slug, function () {
                return $this->getConfigSchema();
            }),
            'default_config' => $this->when($request->route('slug') === $this->slug, function () {
                return $this->getDefaultConfig();
            }),

            // Version information
            'latest_version' => $this->whenLoaded('versions', function () {
                return $this->versions->first()?->version;
            }),
            'versions' => $this->when($request->route('slug') === $this->slug, function () {
                return $this->versions->map(function ($version) {
                    return [
                        'version' => $version->version,
                        'title' => $version->title,
                        'description' => $version->description,
                        'is_stable' => $version->is_stable,
                        'is_beta' => $version->is_beta,
                        'is_alpha' => $version->is_alpha,
                        'release_type' => $version->getReleaseType(),
                        'published_at' => $version->published_at?->toISOString(),
                        'download_count' => $version->download_count,
                        'file_size' => $version->getFormattedFileSize(),
                        'compatibility' => [
                            'is_compatible' => $version->isCompatible(),
                            'issues' => $version->getCompatibilityIssues()
                        ],
                        'changelog' => $version->changelog,
                        'breaking_changes' => $version->breaking_changes,
                        'security_fixes' => $version->security_fixes,
                        'has_security_fixes' => $version->hasSecurityFixes(),
                        'has_breaking_changes' => $version->hasBreakingChanges()
                    ];
                });
            }),

            // Review information  
            'reviews' => [
                'count' => $this->whenLoaded('reviews', function () {
                    return $this->reviews->where('is_approved', true)->count();
                }),
                'average_rating' => $this->rating,
                'recent' => $this->when($request->route('slug') === $this->slug && $this->relationLoaded('reviews'), function () {
                    return $this->reviews
                        ->where('is_approved', true)
                        ->sortByDesc('created_at')
                        ->take(3)
                        ->map(function ($review) {
                            return [
                                'id' => $review->id,
                                'rating' => $review->rating,
                                'rating_stars' => $review->getStarsDisplay(),
                                'title' => $review->title,
                                'content' => $review->getSummary(200),
                                'author' => $review->user->name,
                                'created_at' => $review->created_at->toISOString(),
                                'helpful_count' => $review->helpful_count,
                                'recommendation' => $review->getRecommendationText()
                            ];
                        })
                        ->values();
                })
            ],

            // Installation statistics
            'installations' => $this->when($request->route('slug') === $this->slug && $this->relationLoaded('installations'), function () {
                $activeInstallations = $this->installations->where('status', 'active')->count();
                $totalInstallations = $this->installations->count();
                
                return [
                    'total' => $totalInstallations,
                    'active' => $activeInstallations,
                    'success_rate' => $totalInstallations > 0 ? round(($activeInstallations / $totalInstallations) * 100, 1) : 0
                ];
            }),

            // Metadata
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Format download count for display.
     */
    private function formatDownloadCount(): string
    {
        $count = $this->download_count;
        
        if ($count >= 1000000) {
            return round($count / 1000000, 1) . 'M';
        } elseif ($count >= 1000) {
            return round($count / 1000, 1) . 'K';
        }
        
        return (string) $count;
    }
}