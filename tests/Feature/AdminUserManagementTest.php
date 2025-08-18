<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\AdminRole;
use App\Models\AdminPermission;
use App\Models\AdminAuditLog;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected AdminRole $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin role and permissions
        $this->adminRole = AdminRole::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Full system access',
            'is_system_role' => true,
            'permissions' => ['users.*', 'admin.*', 'system.*']
        ]);

        // Create admin user
        $this->adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'is_admin' => true
        ]);

        $this->adminUser->adminRoles()->attach($this->adminRole);
    }

    public function test_admin_can_view_user_list()
    {
        // Create some test users
        User::factory()->count(5)->create();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'email_verified_at',
                        'created_at',
                        'is_active',
                        'tenant_id'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'total',
                    'per_page'
                ]
            ]);
    }

    public function test_admin_can_create_user()
    {
        $tenant = Tenant::factory()->create();

        $userData = [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'SecurePassword123!',
            'tenant_id' => $tenant->id,
            'send_welcome_email' => true
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/users', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'tenant_id'
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'testuser@example.com',
            'tenant_id' => $tenant->id
        ]);

        // Check audit log
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $this->adminUser->id,
            'action' => 'user.created',
            'resource_type' => 'App\\Models\\User'
        ]);
    }

    public function test_admin_can_update_user()
    {
        $user = User::factory()->create(['name' => 'Original Name']);

        $updateData = [
            'name' => 'Updated Name',
            'is_active' => false
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/admin/users/{$user->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'is_active' => false
        ]);

        // Check audit log
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $this->adminUser->id,
            'action' => 'user.updated',
            'resource_id' => $user->id
        ]);
    }

    public function test_admin_can_suspend_user()
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/users/{$user->id}/suspend", [
                'reason' => 'Terms of service violation'
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false
        ]);

        // Check audit log with reason
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $this->adminUser->id,
            'action' => 'user.suspended',
            'resource_id' => $user->id
        ]);
    }

    public function test_admin_can_search_and_filter_users()
    {
        $tenant1 = Tenant::factory()->create(['name' => 'Tenant One']);
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant Two']);

        User::factory()->create(['name' => 'John Doe', 'tenant_id' => $tenant1->id]);
        User::factory()->create(['name' => 'Jane Smith', 'tenant_id' => $tenant2->id]);
        User::factory()->create(['name' => 'Bob Johnson', 'tenant_id' => $tenant1->id, 'is_active' => false]);

        // Test search by name
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/users?search=John');

        $response->assertStatus(200);
        $users = $response->json('data');
        $this->assertCount(2, $users); // John Doe and Bob Johnson

        // Test filter by tenant
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/users?tenant_id={$tenant1->id}");

        $response->assertStatus(200);
        $users = $response->json('data');
        $this->assertCount(2, $users); // Users from tenant1

        // Test filter by status
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/users?status=inactive');

        $response->assertStatus(200);
        $users = $response->json('data');
        $this->assertCount(1, $users); // Only Bob Johnson
    }

    public function test_bulk_user_operations()
    {
        $users = User::factory()->count(3)->create();
        $userIds = $users->pluck('id')->toArray();

        // Test bulk suspend
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/users/bulk-suspend', [
                'user_ids' => $userIds,
                'reason' => 'Bulk suspension test'
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'operation_id',
                'total_users',
                'status'
            ]);

        // Check that users were suspended
        foreach ($userIds as $userId) {
            $this->assertDatabaseHas('users', [
                'id' => $userId,
                'is_active' => false
            ]);
        }

        // Test bulk reactivate
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/users/bulk-reactivate', [
                'user_ids' => $userIds
            ]);

        $response->assertStatus(200);

        // Check that users were reactivated
        foreach ($userIds as $userId) {
            $this->assertDatabaseHas('users', [
                'id' => $userId,
                'is_active' => true
            ]);
        }
    }

    public function test_admin_permissions_are_enforced()
    {
        // Create a limited admin role
        $limitedRole = AdminRole::create([
            'name' => 'Limited Admin',
            'slug' => 'limited-admin',
            'description' => 'Limited access',
            'is_system_role' => false,
            'permissions' => ['users.view'] // Only view permission
        ]);

        $limitedAdmin = User::factory()->create(['is_admin' => true]);
        $limitedAdmin->adminRoles()->attach($limitedRole);

        // Limited admin should be able to view users
        $response = $this->actingAs($limitedAdmin)
            ->getJson('/api/admin/users');

        $response->assertStatus(200);

        // But should not be able to create users
        $response = $this->actingAs($limitedAdmin)
            ->postJson('/api/admin/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password'
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_audit_log_creation()
    {
        $user = User::factory()->create();

        // Perform an action
        $this->actingAs($this->adminUser)
            ->putJson("/api/admin/users/{$user->id}", [
                'name' => 'Updated Name'
            ]);

        // Check audit log was created
        $auditLog = AdminAuditLog::where('admin_user_id', $this->adminUser->id)
            ->where('action', 'user.updated')
            ->where('resource_id', $user->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('App\\Models\\User', $auditLog->resource_type);
        $this->assertArrayHasKey('changes', $auditLog->details);
        $this->assertNotNull($auditLog->ip_address);
        $this->assertNotNull($auditLog->user_agent);
    }

    public function test_admin_dashboard_analytics()
    {
        // Create some test data
        User::factory()->count(10)->create();
        
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/analytics/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user_analytics' => [
                    'total_users',
                    'active_users',
                    'inactive_users',
                    'new_users_this_month',
                    'user_growth_rate'
                ],
                'activity_analytics' => [
                    'recent_logins',
                    'user_activity_trends'
                ],
                'security_analytics' => [
                    'failed_login_attempts',
                    'security_incidents'
                ]
            ]);
    }

    public function test_user_impersonation_system()
    {
        $targetUser = User::factory()->create();

        // Start impersonation
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/users/{$targetUser->id}/impersonate");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'session_id',
                'impersonated_user',
                'expires_at'
            ]);

        $sessionId = $response->json('session_id');

        // Check impersonation session was created
        $this->assertDatabaseHas('user_impersonation_sessions', [
            'id' => $sessionId,
            'admin_user_id' => $this->adminUser->id,
            'impersonated_user_id' => $targetUser->id,
            'is_active' => true
        ]);

        // End impersonation
        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/impersonation/{$sessionId}");

        $response->assertStatus(200);

        // Check session was ended
        $this->assertDatabaseHas('user_impersonation_sessions', [
            'id' => $sessionId,
            'is_active' => false
        ]);
    }

    public function test_non_admin_cannot_access_admin_endpoints()
    {
        $regularUser = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($regularUser)
            ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_admin_endpoints()
    {
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(401);
    }
}