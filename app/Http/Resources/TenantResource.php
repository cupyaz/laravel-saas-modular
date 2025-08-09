<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
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
            'slug' => $this->slug,
            'domain' => $this->domain,
            'logo' => $this->logo,
            'is_active' => $this->is_active,
            'settings' => $this->when($this->userCanViewSettings($request), $this->settings),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // User's role in this tenant
            'user_role' => $this->when($this->pivot, $this->pivot->role),
            
            // Relationships
            'users' => UserResource::collection($this->whenLoaded('users')),
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
        if ($this->pivot && in_array($this->pivot->role, ['owner', 'admin'])) {
            return true;
        }
        
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