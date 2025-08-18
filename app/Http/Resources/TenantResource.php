<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TenantResource extends BaseApiResource
{
    /**
     * Transform the tenant data.
     */
    protected function transformData(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'domain' => $this->resource->domain,
            'logo' => $this->resource->logo,
            'is_active' => $this->formatBoolean($this->resource->is_active, 'Active', 'Inactive'),
            'created_at' => $this->formatDate($this->resource->created_at),
            'updated_at' => $this->formatDate($this->resource->updated_at),
            
            // User's role in this tenant
            'user_role' => $this->when($this->resource->pivot, $this->resource->pivot->role),
            
            // Trial information
            'trial' => [
                'is_on_trial' => $this->resource->onTrial(),
                'trial_ends_at' => $this->formatDate($this->resource->trial_ends_at),
                'trial_expired' => $this->resource->trialExpired(),
            ],
            
            // Subscription information
            'subscription' => $this->whenLoaded('subscription', function () {
                return new SubscriptionResource($this->resource->subscription);
            }),
            
            // Current plan
            'current_plan' => $this->when($this->resource->currentPlan(), function () {
                return new PlanResource($this->resource->currentPlan());
            }),
            
            // Multi-tenant security information
            'security' => $this->when($request->user()?->can('view-tenant-security'), [
                'isolation_level' => $this->resource->isolation_level,
                'data_residency' => $this->resource->data_residency,
                'audit_enabled' => $this->resource->isAuditEnabled(),
                'compliance_flags' => $this->resource->compliance_flags ?? [],
                'encryption_enabled' => $this->resource->hasEncryptionAtRest(),
                '2fa_required' => $this->resource->requires2FA(),
            ]),
            
            // Resource limits
            'resource_limits' => $this->when($request->user()?->can('view-tenant-limits'), 
                $this->resource->resource_limits ?? []
            ),
            
            // Settings (with permission check)
            'settings' => $this->when($this->userCanViewSettings($request), $this->resource->settings),
            
            // Users relationship
            'users' => $this->whenLoaded('users', function () {
                return UserResource::collection($this->resource->users);
            }),
            
            'users_count' => $this->whenLoaded('users', function () {
                return $this->resource->users->count();
            }),
        ];
    }

    /**
     * Determine if the user can view tenant settings.
     */
    private function userCanViewSettings(Request $request): bool
    {
        $authUser = $request->user();
        
        if (!$authUser) {
            return false;
        }
        
        // Check if user is owner or admin of this tenant
        if ($this->resource->pivot && in_array($this->resource->pivot->role, ['owner', 'admin'])) {
            return true;
        }
        
        return false;
    }

    /**
     * Get the links for the tenant resource.
     */
    protected function links(Request $request): array
    {
        return array_merge(parent::links($request), [
            'users' => route('api.tenant.users', $this->resource->id),
            'subscription' => $this->when($this->resource->subscription, 
                route('api.subscriptions.show', $this->resource->subscription?->id)
            ),
            'security' => route('api.tenant.security-status'),
            'audit_logs' => route('api.tenant.audit-logs'),
        ]);
    }
}