<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->when($this->shouldShowSensitiveData($request), $this->phone),
            'date_of_birth' => $this->when($this->shouldShowSensitiveData($request), $this->date_of_birth),
            'gender' => $this->when($this->shouldShowSensitiveData($request), $this->gender),
            'company' => $this->company,
            'job_title' => $this->job_title,
            'bio' => $this->bio,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'avatar' => $this->avatar,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'onboarding_completed' => $this->onboarding_completed,
            'preferences' => $this->preferences,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // GDPR compliance information
            'gdpr_consent' => $this->gdpr_consent,
            'gdpr_consent_at' => $this->when($this->shouldShowSensitiveData($request), $this->gdpr_consent_at),
            'marketing_consent' => $this->marketing_consent,
            'marketing_consent_at' => $this->when($this->shouldShowSensitiveData($request), $this->marketing_consent_at),
            
            // Relationships
            'tenants' => TenantResource::collection($this->whenLoaded('tenants')),
            'security_logs' => SecurityLogResource::collection($this->whenLoaded('securityLogs')),
        ];
    }
    
    /**
     * Determine if sensitive data should be shown to the user.
     */
    private function shouldShowSensitiveData(Request $request): bool
    {
        $authUser = $request->user();
        
        // Show sensitive data if the user is accessing their own profile
        if ($authUser && $authUser->id === $this->id) {
            return true;
        }
        
        // TODO: Add admin permission check here when implementing admin roles
        // if ($authUser && $authUser->hasRole('admin')) {
        //     return true;
        // }
        
        return false;
    }
    
    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'timestamp' => now(),
            ],
        ];
    }
}