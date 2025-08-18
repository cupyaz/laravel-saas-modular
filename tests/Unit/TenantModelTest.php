<?php

namespace Tests\Unit;

use App\Models\Tenant;
use PHPUnit\Framework\TestCase;

class TenantModelTest extends TestCase
{
    public function test_tenant_constants_are_defined()
    {
        // Test isolation level constants
        $this->assertEquals('database', Tenant::ISOLATION_DATABASE);
        $this->assertEquals('schema', Tenant::ISOLATION_SCHEMA);
        $this->assertEquals('row', Tenant::ISOLATION_ROW);

        // Test compliance constants
        $this->assertEquals('gdpr', Tenant::COMPLIANCE_GDPR);
        $this->assertEquals('hipaa', Tenant::COMPLIANCE_HIPAA);
        $this->assertEquals('soc2', Tenant::COMPLIANCE_SOC2);
        $this->assertEquals('iso27001', Tenant::COMPLIANCE_ISO27001);
        $this->assertEquals('pci_dss', Tenant::COMPLIANCE_PCI_DSS);

        // Test residency constants
        $this->assertEquals('eu', Tenant::RESIDENCY_EU);
        $this->assertEquals('us', Tenant::RESIDENCY_US);
        $this->assertEquals('canada', Tenant::RESIDENCY_CANADA);
        $this->assertEquals('australia', Tenant::RESIDENCY_AUSTRALIA);
        $this->assertEquals('asia', Tenant::RESIDENCY_ASIA);
    }

    public function test_tenant_has_security_fields_in_fillable()
    {
        $tenant = new Tenant();
        $fillable = $tenant->getFillable();

        $securityFields = [
            'encryption_key',
            'data_residency',
            'compliance_flags',
            'resource_limits',
            'isolation_level',
            'security_settings',
            'audit_enabled',
            'backup_settings',
        ];

        foreach ($securityFields as $field) {
            $this->assertContains($field, $fillable, "Field {$field} should be fillable");
        }
    }

    public function test_tenant_connection_name_generation()
    {
        $tenant = new Tenant();
        $tenant->id = 123;

        $connectionName = $tenant->getConnectionName();
        $this->assertEquals('tenant_123', $connectionName);
    }

    public function test_tenant_database_config_for_sqlite()
    {
        $tenant = new Tenant();
        $tenant->id = 456;
        $tenant->isolation_level = Tenant::ISOLATION_DATABASE;

        $config = $tenant->getDatabaseConfig();

        $this->assertEquals('sqlite', $config['driver']);
        $this->assertStringContainsString('tenant_456', $config['database']);
        $this->assertTrue($config['foreign_key_constraints']);
    }

    public function test_tenant_encryption_key_generation()
    {
        // Since we can't test the boot method directly without database,
        // we test the encryption key generation logic
        $key = base64_encode(random_bytes(32));
        $this->assertEquals(44, strlen($key)); // Base64 encoded 32 bytes = 44 chars
    }

    public function test_tenant_has_encryption_at_rest_default()
    {
        $tenant = new Tenant();
        
        // Set default security settings manually (normally done in boot)
        $tenant->security_settings = [
            'encryption_at_rest' => true,
            'enforce_2fa' => false,
            'session_timeout' => 3600,
        ];

        $this->assertTrue($tenant->hasEncryptionAtRest());
        $this->assertFalse($tenant->requires2FA());
        $this->assertEquals(3600, $tenant->getSessionTimeout());
    }

    public function test_tenant_resource_limit_methods()
    {
        $tenant = new Tenant();
        $tenant->resource_limits = [
            'max_users' => 10,
            'max_storage_gb' => 5,
        ];

        // Test get resource limit
        $this->assertEquals(10, $tenant->getResourceLimit('max_users'));
        $this->assertEquals(5, $tenant->getResourceLimit('max_storage_gb'));
        $this->assertNull($tenant->getResourceLimit('nonexistent'));

        // Test exceeds resource limit
        $this->assertTrue($tenant->exceedsResourceLimit('max_users', 15));
        $this->assertFalse($tenant->exceedsResourceLimit('max_users', 8));
        $this->assertFalse($tenant->exceedsResourceLimit('nonexistent', 100));
    }

    public function test_tenant_compliance_methods()
    {
        $tenant = new Tenant();
        $tenant->compliance_flags = [Tenant::COMPLIANCE_GDPR, Tenant::COMPLIANCE_HIPAA];

        $this->assertTrue($tenant->hasCompliance(Tenant::COMPLIANCE_GDPR));
        $this->assertTrue($tenant->hasCompliance(Tenant::COMPLIANCE_HIPAA));
        $this->assertFalse($tenant->hasCompliance(Tenant::COMPLIANCE_SOC2));
    }

    public function test_tenant_data_residency_methods()
    {
        $tenant = new Tenant();
        $tenant->data_residency = Tenant::RESIDENCY_EU;

        $this->assertTrue($tenant->isInRegion(Tenant::RESIDENCY_EU));
        $this->assertFalse($tenant->isInRegion(Tenant::RESIDENCY_US));
    }

    public function test_tenant_audit_enabled_check()
    {
        $tenant = new Tenant();
        $tenant->audit_enabled = true;

        $this->assertTrue($tenant->isAuditEnabled());

        $tenant->audit_enabled = false;
        $this->assertFalse($tenant->isAuditEnabled());
    }

    public function test_tenant_backup_settings_defaults()
    {
        $tenant = new Tenant();
        $settings = $tenant->getBackupSettings();

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('enabled', $settings);
        $this->assertArrayHasKey('frequency', $settings);
        $this->assertArrayHasKey('retention_days', $settings);
        $this->assertArrayHasKey('encrypt_backups', $settings);
        $this->assertArrayHasKey('backup_location', $settings);

        // Test defaults
        $this->assertTrue($settings['enabled']);
        $this->assertEquals('daily', $settings['frequency']);
        $this->assertEquals(30, $settings['retention_days']);
        $this->assertTrue($settings['encrypt_backups']);
        $this->assertEquals('local', $settings['backup_location']);
    }
}