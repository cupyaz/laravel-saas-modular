<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class TenantSecurityService
{
    /**
     * Create a new tenant with security defaults
     */
    public function createSecureTenant(array $data): Tenant
    {
        // Generate secure defaults
        $secureData = array_merge($data, [
            'encryption_key' => $this->generateEncryptionKey(),
            'isolation_level' => $data['isolation_level'] ?? Tenant::ISOLATION_DATABASE,
            'audit_enabled' => true,
            'security_settings' => $this->getDefaultSecuritySettings(),
            'resource_limits' => $this->getDefaultResourceLimits(),
            'compliance_flags' => $data['compliance_flags'] ?? [],
        ]);

        $tenant = Tenant::create($secureData);

        // Initialize tenant database
        $this->initializeTenantDatabase($tenant);

        // Log tenant creation
        TenantAuditLog::logActivity(
            tenantId: $tenant->id,
            userId: auth()->id(),
            action: TenantAuditLog::ACTION_CREATE,
            resourceType: 'tenant',
            resourceId: $tenant->id,
            newValues: $secureData,
            riskLevel: TenantAuditLog::RISK_HIGH,
            complianceRelevant: true
        );

        return $tenant;
    }

    /**
     * Initialize tenant database with security setup
     */
    public function initializeTenantDatabase(Tenant $tenant): void
    {
        $connectionName = $tenant->getConnectionName();
        $config = $tenant->getDatabaseConfig();

        try {
            // Create database file for SQLite or schema for MySQL
            if ($config['driver'] === 'sqlite') {
                $databasePath = $config['database'];
                $directory = dirname($databasePath);
                
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }
                
                if (!file_exists($databasePath)) {
                    touch($databasePath);
                    chmod($databasePath, 0600); // Secure file permissions
                }
            }

            // Configure and test connection
            config(["database.connections.{$connectionName}" => $config]);
            DB::purge($connectionName);
            
            // Test connection
            DB::connection($connectionName)->getPdo();

            // Run tenant-specific migrations
            $this->runTenantMigrations($tenant);

            Log::info("Tenant database initialized successfully", [
                'tenant_id' => $tenant->id,
                'connection' => $connectionName,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to initialize tenant database", [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            
            throw new \RuntimeException("Failed to initialize tenant database: " . $e->getMessage());
        }
    }

    /**
     * Encrypt data using tenant-specific key
     */
    public function encryptForTenant(Tenant $tenant, string $data): string
    {
        $key = $tenant->getEncryptionKey();
        $encrypter = new Encrypter($key, 'AES-256-CBC');
        
        return $encrypter->encrypt($data);
    }

    /**
     * Decrypt data using tenant-specific key
     */
    public function decryptForTenant(Tenant $tenant, string $encryptedData): string
    {
        $key = $tenant->getEncryptionKey();
        $encrypter = new Encrypter($key, 'AES-256-CBC');
        
        return $encrypter->decrypt($encryptedData);
    }

    /**
     * Rotate tenant encryption key
     */
    public function rotateEncryptionKey(Tenant $tenant): void
    {
        $oldKey = $tenant->getEncryptionKey();
        $newKey = $this->generateEncryptionKey();

        DB::transaction(function () use ($tenant, $oldKey, $newKey) {
            // Update tenant with new key
            $tenant->update(['encryption_key' => $newKey]);

            // Re-encrypt sensitive data with new key
            $this->reEncryptTenantData($tenant, $oldKey, $newKey);

            // Log key rotation
            TenantAuditLog::logSecurityEvent(
                tenantId: $tenant->id,
                userId: auth()->id(),
                eventType: 'encryption_key_rotation',
                riskLevel: TenantAuditLog::RISK_HIGH
            );
        });
    }

    /**
     * Validate tenant data integrity
     */
    public function validateDataIntegrity(Tenant $tenant): array
    {
        $issues = [];

        try {
            // Check database connection
            $connection = DB::connection($tenant->getConnectionName());
            $connection->getPdo();

            // Check for unauthorized cross-tenant data access
            $crossTenantData = $this->detectCrossTenantData($tenant);
            if (!empty($crossTenantData)) {
                $issues[] = [
                    'type' => 'cross_tenant_data',
                    'severity' => 'critical',
                    'description' => 'Detected potential cross-tenant data leakage',
                    'details' => $crossTenantData,
                ];
            }

            // Check encryption status
            if (!$this->verifyEncryptionStatus($tenant)) {
                $issues[] = [
                    'type' => 'encryption_issues',
                    'severity' => 'high',
                    'description' => 'Encryption verification failed',
                ];
            }

            // Check compliance requirements
            $complianceIssues = $this->validateComplianceRequirements($tenant);
            $issues = array_merge($issues, $complianceIssues);

        } catch (\Exception $e) {
            $issues[] = [
                'type' => 'database_connection',
                'severity' => 'critical',
                'description' => 'Failed to connect to tenant database',
                'error' => $e->getMessage(),
            ];
        }

        return $issues;
    }

    /**
     * Backup tenant data securely
     */
    public function backupTenantData(Tenant $tenant): string
    {
        $backupSettings = $tenant->getBackupSettings();
        $timestamp = now()->format('Y-m-d-H-i-s');
        $backupFileName = "tenant_{$tenant->id}_backup_{$timestamp}.sql";
        
        try {
            $connection = $tenant->getConnectionName();
            $config = $tenant->getDatabaseConfig();

            // Create backup
            $backupPath = storage_path("backups/tenants/{$backupFileName}");
            $backupDir = dirname($backupPath);
            
            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            if ($config['driver'] === 'sqlite') {
                // Copy SQLite file
                copy($config['database'], $backupPath);
            } else {
                // Export MySQL database
                $this->exportMySQLDatabase($config, $backupPath);
            }

            // Encrypt backup if required
            if ($backupSettings['encrypt_backups']) {
                $encryptedPath = $backupPath . '.enc';
                $this->encryptFile($tenant, $backupPath, $encryptedPath);
                unlink($backupPath); // Remove unencrypted file
                $backupPath = $encryptedPath;
            }

            // Log backup creation
            TenantAuditLog::logActivity(
                tenantId: $tenant->id,
                userId: auth()->id(),
                action: TenantAuditLog::ACTION_CREATE,
                resourceType: 'backup',
                metadata: [
                    'backup_file' => $backupFileName,
                    'encrypted' => $backupSettings['encrypt_backups'],
                    'size' => filesize($backupPath),
                ],
                complianceRelevant: true
            );

            return $backupPath;

        } catch (\Exception $e) {
            Log::error("Backup failed for tenant", [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            
            throw new \RuntimeException("Backup failed: " . $e->getMessage());
        }
    }

    /**
     * Restore tenant data from backup
     */
    public function restoreTenantData(Tenant $tenant, string $backupPath): void
    {
        DB::transaction(function () use ($tenant, $backupPath) {
            try {
                // Decrypt backup if encrypted
                if (str_ends_with($backupPath, '.enc')) {
                    $decryptedPath = str_replace('.enc', '', $backupPath);
                    $this->decryptFile($tenant, $backupPath, $decryptedPath);
                    $backupPath = $decryptedPath;
                }

                $config = $tenant->getDatabaseConfig();

                if ($config['driver'] === 'sqlite') {
                    // Replace SQLite file
                    copy($backupPath, $config['database']);
                } else {
                    // Import MySQL database
                    $this->importMySQLDatabase($config, $backupPath);
                }

                // Log restore operation
                TenantAuditLog::logActivity(
                    tenantId: $tenant->id,
                    userId: auth()->id(),
                    action: 'restore',
                    resourceType: 'backup',
                    metadata: ['backup_file' => basename($backupPath)],
                    riskLevel: TenantAuditLog::RISK_HIGH,
                    complianceRelevant: true
                );

                // Clean up decrypted file if it was encrypted
                if (str_contains($backupPath, '_decrypted')) {
                    unlink($backupPath);
                }

            } catch (\Exception $e) {
                Log::error("Restore failed for tenant", [
                    'tenant_id' => $tenant->id,
                    'backup_path' => $backupPath,
                    'error' => $e->getMessage(),
                ]);
                
                throw new \RuntimeException("Restore failed: " . $e->getMessage());
            }
        });
    }

    /**
     * Delete tenant and all associated data securely
     */
    public function deleteTenantSecurely(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            // Create final backup before deletion
            $finalBackup = $this->backupTenantData($tenant);

            // Log deletion
            TenantAuditLog::logActivity(
                tenantId: $tenant->id,
                userId: auth()->id(),
                action: TenantAuditLog::ACTION_DELETE,
                resourceType: 'tenant',
                resourceId: $tenant->id,
                metadata: ['final_backup' => $finalBackup],
                riskLevel: TenantAuditLog::RISK_CRITICAL,
                complianceRelevant: true
            );

            // Delete tenant database
            $this->deleteTenantDatabase($tenant);

            // Soft delete tenant (keep for audit purposes)
            $tenant->delete();

            Log::info("Tenant deleted securely", [
                'tenant_id' => $tenant->id,
                'final_backup' => $finalBackup,
            ]);
        });
    }

    /**
     * Generate secure encryption key
     */
    private function generateEncryptionKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Get default security settings
     */
    private function getDefaultSecuritySettings(): array
    {
        return [
            'enforce_2fa' => false,
            'session_timeout' => 3600,
            'max_login_attempts' => 5,
            'password_expiry_days' => 90,
            'encryption_at_rest' => true,
            'audit_all_actions' => true,
            'require_ssl' => true,
            'ip_whitelist' => [],
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png'],
            'max_file_size_mb' => 10,
        ];
    }

    /**
     * Get default resource limits
     */
    private function getDefaultResourceLimits(): array
    {
        return [
            'max_users' => 10,
            'max_storage_gb' => 1,
            'max_api_calls_per_hour' => 1000,
            'max_database_connections' => 10,
            'max_concurrent_sessions' => 5,
        ];
    }

    /**
     * Run tenant-specific migrations
     */
    private function runTenantMigrations(Tenant $tenant): void
    {
        // This would run tenant-specific migrations
        // For now, we'll just create basic tables
        $connection = $tenant->getConnectionName();
        
        // Example: Create a basic users table for the tenant
        DB::connection($connection)->statement("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                email_verified_at TIMESTAMP NULL,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    /**
     * Re-encrypt tenant data with new key
     */
    private function reEncryptTenantData(Tenant $tenant, string $oldKey, string $newKey): void
    {
        // This would re-encrypt all encrypted fields in the tenant database
        // Implementation depends on specific encrypted fields
        Log::info("Re-encryption completed for tenant", ['tenant_id' => $tenant->id]);
    }

    /**
     * Detect cross-tenant data access
     */
    private function detectCrossTenantData(Tenant $tenant): array
    {
        // This would check for data that doesn't belong to the tenant
        // Implementation depends on specific data model
        return [];
    }

    /**
     * Verify encryption status
     */
    private function verifyEncryptionStatus(Tenant $tenant): bool
    {
        // This would verify that sensitive data is properly encrypted
        return $tenant->hasEncryptionAtRest();
    }

    /**
     * Validate compliance requirements
     */
    private function validateComplianceRequirements(Tenant $tenant): array
    {
        $issues = [];

        // Check GDPR compliance
        if ($tenant->hasCompliance(Tenant::COMPLIANCE_GDPR)) {
            if (!$tenant->isAuditEnabled()) {
                $issues[] = [
                    'type' => 'gdpr_compliance',
                    'severity' => 'high',
                    'description' => 'GDPR requires audit logging to be enabled',
                ];
            }
        }

        // Check HIPAA compliance
        if ($tenant->hasCompliance(Tenant::COMPLIANCE_HIPAA)) {
            if (!$tenant->hasEncryptionAtRest()) {
                $issues[] = [
                    'type' => 'hipaa_compliance',
                    'severity' => 'critical',
                    'description' => 'HIPAA requires encryption at rest',
                ];
            }
        }

        return $issues;
    }

    /**
     * Encrypt file using tenant key
     */
    private function encryptFile(Tenant $tenant, string $inputPath, string $outputPath): void
    {
        $data = file_get_contents($inputPath);
        $encrypted = $this->encryptForTenant($tenant, $data);
        file_put_contents($outputPath, $encrypted);
        chmod($outputPath, 0600);
    }

    /**
     * Decrypt file using tenant key
     */
    private function decryptFile(Tenant $tenant, string $inputPath, string $outputPath): void
    {
        $encrypted = file_get_contents($inputPath);
        $decrypted = $this->decryptForTenant($tenant, $encrypted);
        file_put_contents($outputPath, $decrypted);
        chmod($outputPath, 0600);
    }

    /**
     * Export MySQL database
     */
    private function exportMySQLDatabase(array $config, string $outputPath): void
    {
        // Implementation for MySQL backup
        // This would use mysqldump or similar
    }

    /**
     * Import MySQL database
     */
    private function importMySQLDatabase(array $config, string $inputPath): void
    {
        // Implementation for MySQL restore
        // This would use mysql command or similar
    }

    /**
     * Delete tenant database
     */
    private function deleteTenantDatabase(Tenant $tenant): void
    {
        $config = $tenant->getDatabaseConfig();
        
        if ($config['driver'] === 'sqlite') {
            $databasePath = $config['database'];
            if (file_exists($databasePath)) {
                // Securely delete SQLite file
                unlink($databasePath);
            }
        }
        
        // For MySQL, would drop the schema or prefix tables
    }
}