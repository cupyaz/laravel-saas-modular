<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseApiResource extends JsonResource
{
    /**
     * Additional meta information to include with the resource.
     */
    protected array $metaData = [];

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->transformData($request),
            'meta' => $this->meta($request),
            'links' => $this->links($request),
        ];
    }

    /**
     * Transform the main resource data.
     */
    abstract protected function transformData(Request $request): array;

    /**
     * Get the meta information for the resource.
     */
    protected function meta(Request $request): array
    {
        return array_merge([
            'api_version' => config('api.version', '1.0'),
            'timestamp' => now()->toISOString(),
            'request_id' => $request->header('X-Request-ID', str()->uuid()),
        ], $this->metaData);
    }

    /**
     * Get the links for the resource.
     */
    protected function links(Request $request): array
    {
        return [
            'self' => $request->url(),
        ];
    }

    /**
     * Add meta data to the resource.
     */
    public function withMeta(array $meta): static
    {
        $this->metaData = array_merge($this->metaData, $meta);
        return $this;
    }

    /**
     * Conditionally include attributes.
     */
    protected function whenLoaded(string $relationship, $value = null, $default = null)
    {
        if ($this->resource && $this->resource->relationLoaded($relationship)) {
            return $value ?? $this->resource->{$relationship};
        }

        return $default;
    }

    /**
     * Include pagination information if available.
     */
    protected function paginationMeta($paginator): array
    {
        if (!$paginator) {
            return [];
        }

        return [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ]
        ];
    }

    /**
     * Format currency values.
     */
    protected function formatCurrency($amount, string $currency = 'USD'): array
    {
        return [
            'amount' => $amount,
            'currency' => $currency,
            'formatted' => number_format($amount, 2) . ' ' . $currency,
        ];
    }

    /**
     * Format boolean values with human-readable labels.
     */
    protected function formatBoolean(bool $value, string $trueLabel = 'Yes', string $falseLabel = 'No'): array
    {
        return [
            'value' => $value,
            'label' => $value ? $trueLabel : $falseLabel,
        ];
    }

    /**
     * Format dates with multiple representations.
     */
    protected function formatDate($date): ?array
    {
        if (!$date) {
            return null;
        }

        $carbonDate = is_string($date) ? \Carbon\Carbon::parse($date) : $date;

        return [
            'iso' => $carbonDate->toISOString(),
            'human' => $carbonDate->diffForHumans(),
            'formatted' => $carbonDate->format('Y-m-d H:i:s'),
            'timestamp' => $carbonDate->timestamp,
        ];
    }
}