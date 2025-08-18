<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\User;
use App\Services\TenantSecurityService;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use WithFaker;

    private TenantSecurityService $tenantSecurityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantSecurityService = app(TenantSecurityService::class);
    }

    public function test_tenant_model_security_fields()
    {
        // Test tenant creation with security defaults
        $tenantData = [
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'user_id' => 1,
        ];

        $tenant = Tenant::create($tenantData);

        // Verify security defaults are applied
        $this->assertNotNull($tenant->encryption_key);
        $this->assertEquals(Tenant::ISOLATION_DATABASE, $tenant->isolation_level);
        $this->assertTrue($tenant->audit_enabled);
        $this->assertNotNull($tenant->security_settings);
        $this->assertNotNull($tenant->resource_limits);
    }

    public function test_tenant_isolation_levels()
    {
        $tenant = Tenant::create([
            'name' => 'Isolated Tenant',
            'slug' => 'isolated-tenant',
            'user_id' => 1,
            'isolation_level' => Tenant::ISOLATION_DATABASE,
        ]);

        // Test database configuration generation
        $config = $tenant->getDatabaseConfig();
        $this->assertArrayHasKey('driver', $config);
        $this->assertEquals('sqlite', $config['driver']);
        $this->assertStringContains("tenant_{$tenant->id}", $config['database']);

        // Test connection name
        $connectionName = $tenant->getConnectionName();
        $this->assertEquals("tenant_{$tenant->id}", $connectionName);
    }

    public function test_tenant_compliance_management()
    {
        $tenant = Tenant::create([
            'name' => 'Compliant Tenant',
            'slug' => 'compliant-tenant',
            'user_id' => 1,
        ]);

        // Test adding compliance
        $tenant->addCompliance(Tenant::COMPLIANCE_GDPR);
        $this->assertTrue($tenant->hasCompliance(Tenant::COMPLIANCE_GDPR));

        // Test removing compliance
        $tenant->removeCompliance(Tenant::COMPLIANCE_GDPR);
        $this->assertFalse($tenant->hasCompliance(Tenant::COMPLIANCE_GDPR));

        // Test multiple compliance flags
        $tenant->addCompliance(Tenant::COMPLIANCE_HIPAA);
        $tenant->addCompliance(Tenant::COMPLIANCE_SOC2);
        $this->assertTrue($tenant->hasCompliance(Tenant::COMPLIANCE_HIPAA));
        $this->assertTrue($tenant->hasCompliance(Tenant::COMPLIANCE_SOC2));
    }

    public function test_tenant_resource_limits()
    {
        $tenant = Tenant::create([
            'name' => 'Limited Tenant',
            'slug' => 'limited-tenant',
            'user_id' => 1,
        ]);

        // Test resource limit checking
        $this->assertFalse($tenant->exceedsResourceLimit('max_users', 5));
        
        // Set a lower limit
        $tenant->setResourceLimit('max_users', 3);
        $this->assertTrue($tenant->exceedsResourceLimit('max_users', 5));

        // Test limit retrieval
        $this->assertEquals(3, $tenant->getResourceLimit('max_users'));
    }

    public function test_tenant_security_settings()
    {
        $tenant = Tenant::create([
            'name' => 'Secure Tenant',
            'slug' => 'secure-tenant',
            'user_id' => 1,
        ]);

        // Test encryption at rest
        $this->assertTrue($tenant->hasEncryptionAtRest());

        // Test 2FA settings
        $this->assertFalse($tenant->requires2FA());
        $tenant->setSecuritySetting('enforce_2fa', true);
        $this->assertTrue($tenant->requires2FA());

        // Test session timeout
        $this->assertEquals(3600, $tenant->getSessionTimeout());
        $tenant->setSecuritySetting('session_timeout', 7200);
        $this->assertEquals(7200, $tenant->getSessionTimeout());
    }

    public function test_tenant_audit_logging()
    {
        $tenant = Tenant::create([
            'name' => 'Audited Tenant',
            'slug' => 'audited-tenant',
            'user_id' => 1,
        ]);

        // Test basic audit logging
        $log = TenantAuditLog::logActivity(
            tenantId: $tenant->id,
            userId: 1,
            action: TenantAuditLog::ACTION_CREATE,
            resourceType: 'test_resource',
            resourceId: 123,
            newValues: ['test' => 'data'],
            riskLevel: TenantAuditLog::RISK_MEDIUM
        );

        $this->assertInstanceOf(TenantAuditLog::class, $log);
        $this->assertEquals($tenant->id, $log->tenant_id);
        $this->assertEquals(TenantAuditLog::ACTION_CREATE, $log->action);
        $this->assertEquals(TenantAuditLog::RISK_MEDIUM, $log->risk_level);

        // Test security event logging
        $securityLog = TenantAuditLog::logSecurityEvent(
            tenantId: $tenant->id,
            userId: 1,
            eventType: 'test_security_event',
            details: ['ip' => '127.0.0.1']
        );

        $this->assertEquals(TenantAuditLog::ACTION_SECURITY_EVENT, $securityLog->action);
        $this->assertTrue($securityLog->compliance_relevant);
    }

    public function test_audit_summary_generation()
    {
        $tenant = Tenant::create([
            'name' => 'Summary Tenant',
            'slug' => 'summary-tenant',
            'user_id' => 1,
        ]);

        // Create some audit logs
        for ($i = 0; $i < 5; $i++) {
            TenantAuditLog::logActivity(
                tenantId: $tenant->id,
                userId: 1,
                action: TenantAuditLog::ACTION_READ,
                resourceType: 'test_resource',
                riskLevel: $i % 2 ? TenantAuditLog::RISK_HIGH : TenantAuditLog::RISK_LOW
            );
        }

        // Generate summary
        $summary = TenantAuditLog::getAuditSummary($tenant->id, 30);

        $this->assertArrayHasKey('total_events', $summary);
        $this->assertArrayHasKey('high_risk_events', $summary);
        $this->assertArrayHasKey('actions_breakdown', $summary);
        $this->assertEquals(5, $summary['total_events']);
    }

    public function test_anomaly_detection()
    {
        $tenant = Tenant::create([
            'name' => 'Anomaly Tenant',
            'slug' => 'anomaly-tenant',
            'user_id' => 1,
        ]);

        // Create suspicious activity pattern
        for ($i = 0; $i < 25; $i++) {
            TenantAuditLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => 1,
                'action' => TenantAuditLog::ACTION_LOGIN,
                'resource_type' => 'authentication',
                'ip_address' => '192.168.1.100',
                'risk_level' => TenantAuditLog::RISK_LOW,
                'created_at' => now(),
            ]);
        }

        // Detect anomalies
        $anomalies = TenantAuditLog::detectAnomalies($tenant->id);

        $this->assertNotEmpty($anomalies);
        $this->assertEquals('suspicious_login_pattern', $anomalies[0]['type']);
    }

    public function test_tenant_security_service_creation()
    {
        $tenantData = [
            'name' => 'Service Test Tenant',
            'slug' => 'service-test-tenant',
            'user_id' => 1,
            'compliance_flags' => [Tenant::COMPLIANCE_GDPR],
            'data_residency' => Tenant::RESIDENCY_EU,
        ];

        $tenant = $this->tenantSecurityService->createSecureTenant($tenantData);

        $this->assertNotNull($tenant->encryption_key);
        $this->assertEquals(Tenant::RESIDENCY_EU, $tenant->data_residency);
        $this->assertTrue($tenant->hasCompliance(Tenant::COMPLIANCE_GDPR));
        $this->assertTrue($tenant->audit_enabled);
    }

    public function test_tenant_encryption_decryption()
    {
        $tenant = Tenant::create([
            'name' => 'Crypto Tenant',
            'slug' => 'crypto-tenant',
            'user_id' => 1,
        ]);

        $originalData = 'Sensitive data that needs encryption';
        
        // Test encryption
        $encrypted = $this->tenantSecurityService->encryptForTenant($tenant, $originalData);
        $this->assertNotEquals($originalData, $encrypted);

        // Test decryption
        $decrypted = $this->tenantSecurityService->decryptForTenant($tenant, $encrypted);
        $this->assertEquals($originalData, $decrypted);
    }

    public function test_data_integrity_validation()
    {
        $tenant = Tenant::create([
            'name' => 'Integrity Tenant',
            'slug' => 'integrity-tenant',
            'user_id' => 1,
        ]);

        // Initialize tenant database
        $this->tenantSecurityService->initializeTenantDatabase($tenant);

        // Validate data integrity
        $issues = $this->tenantSecurityService->validateDataIntegrity($tenant);

        // Should have no issues for a fresh tenant
        $this->assertEmpty($issues);
    }

    public function test_tenant_scopes()
    {
        // Create tenants with different properties
        $activeTenant = Tenant::create([
            'name' => 'Active Tenant',
            'slug' => 'active-tenant',
            'user_id' => 1,
            'is_active' => true,
            'encryption_key' => 'test-key',
            'compliance_flags' => [Tenant::COMPLIANCE_GDPR],
            'data_residency' => Tenant::RESIDENCY_EU,
            'audit_enabled' => true,
        ]);

        $inactiveTenant = Tenant::create([
            'name' => 'Inactive Tenant',
            'slug' => 'inactive-tenant',
            'user_id' => 1,
            'is_active' => false,
        ]);

        // Test active and secure scope
        $activeSecure = Tenant::activeAndSecure()->get();
        $this->assertTrue($activeSecure->contains($activeTenant));
        $this->assertFalse($activeSecure->contains($inactiveTenant));

        // Test compliance scope
        $gdprTenants = Tenant::withCompliance(Tenant::COMPLIANCE_GDPR)->get();
        $this->assertTrue($gdprTenants->contains($activeTenant));

        // Test region scope
        $euTenants = Tenant::inRegion(Tenant::RESIDENCY_EU)->get();
        $this->assertTrue($euTenants->contains($activeTenant));

        // Test audit scope
        $auditTenants = Tenant::withAudit()->get();
        $this->assertTrue($auditTenants->contains($activeTenant));
    }

    public function test_tenant_backup_settings()
    {
        $tenant = Tenant::create([
            'name' => 'Backup Tenant',
            'slug' => 'backup-tenant',
            'user_id' => 1,
        ]);

        $backupSettings = $tenant->getBackupSettings();
        
        $this->assertArrayHasKey('enabled', $backupSettings);
        $this->assertArrayHasKey('frequency', $backupSettings);
        $this->assertArrayHasKey('retention_days', $backupSettings);
        $this->assertArrayHasKey('encrypt_backups', $backupSettings);
        $this->assertTrue($backupSettings['enabled']);
        $this->assertTrue($backupSettings['encrypt_backups']);
    }
}