<?php

namespace App\Services;

use App\Models\AdminBulkOperation;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Writer;
use App\Services\AdminNotificationService;
use SplTempFileObject;

class AdminBulkOperationService
{
    /**
     * Start a bulk user import operation.
     */
    public function bulkImportUsers(int $adminUserId, array $csvData, array $options = []): AdminBulkOperation
    {
        $operation = AdminBulkOperation::create([
            'admin_user_id' => $adminUserId,
            'operation_type' => AdminBulkOperation::TYPE_BULK_IMPORT,
            'target_model' => User::class,
            'total_records' => count($csvData),
            'operation_parameters' => array_merge([
                'send_welcome_email' => true,
                'force_email_verification' => false,
                'default_role' => 'user',
            ], $options),
        ]);

        AdminAuditLog::logBulkOperationStarted($adminUserId, 'user_import', [
            'total_records' => count($csvData),
            'options' => $options,
        ]);

        // Process in background
        dispatch(function() use ($operation, $csvData) {
            $this->processUserImport($operation, $csvData);
        });

        return $operation;
    }

    /**
     * Start a bulk user export operation.
     */
    public function bulkExportUsers(int $adminUserId, array $filters = []): AdminBulkOperation
    {
        $query = $this->buildUserQuery($filters);
        $totalUsers = $query->count();

        $operation = AdminBulkOperation::create([
            'admin_user_id' => $adminUserId,
            'operation_type' => AdminBulkOperation::TYPE_BULK_EXPORT,
            'target_model' => User::class,
            'total_records' => $totalUsers,
            'operation_parameters' => $filters,
        ]);

        AdminAuditLog::logBulkOperationStarted($adminUserId, 'user_export', [
            'total_records' => $totalUsers,
            'filters' => $filters,
        ]);

        // Process in background
        dispatch(function() use ($operation, $filters) {
            $this->processUserExport($operation, $filters);
        });

        return $operation;
    }

    /**
     * Start a bulk user update operation.
     */
    public function bulkUpdateUsers(int $adminUserId, array $userIds, array $updates): AdminBulkOperation
    {
        $operation = AdminBulkOperation::create([
            'admin_user_id' => $adminUserId,
            'operation_type' => AdminBulkOperation::TYPE_BULK_UPDATE,
            'target_model' => User::class,
            'total_records' => count($userIds),
            'operation_parameters' => [
                'user_ids' => $userIds,
                'updates' => $updates,
            ],
        ]);

        AdminAuditLog::logBulkOperationStarted($adminUserId, 'user_bulk_update', [
            'user_count' => count($userIds),
            'updates' => $updates,
        ]);

        // Process in background
        dispatch(function() use ($operation, $userIds, $updates) {
            $this->processUserBulkUpdate($operation, $userIds, $updates);
        });

        return $operation;
    }

    /**
     * Start a bulk user suspension operation.
     */
    public function bulkSuspendUsers(int $adminUserId, array $userIds, string $reason): AdminBulkOperation
    {
        $operation = AdminBulkOperation::create([
            'admin_user_id' => $adminUserId,
            'operation_type' => AdminBulkOperation::TYPE_BULK_SUSPEND,
            'target_model' => User::class,
            'total_records' => count($userIds),
            'operation_parameters' => [
                'user_ids' => $userIds,
                'reason' => $reason,
            ],
        ]);

        AdminAuditLog::logBulkOperationStarted($adminUserId, 'user_bulk_suspend', [
            'user_count' => count($userIds),
            'reason' => $reason,
        ]);

        // Process in background
        dispatch(function() use ($operation, $userIds, $reason) {
            $this->processUserBulkSuspension($operation, $userIds, $reason);
        });

        return $operation;
    }

    /**
     * Start a bulk user reactivation operation.
     */
    public function bulkReactivateUsers(int $adminUserId, array $userIds): AdminBulkOperation
    {
        $operation = AdminBulkOperation::create([
            'admin_user_id' => $adminUserId,
            'operation_type' => AdminBulkOperation::TYPE_BULK_REACTIVATE,
            'target_model' => User::class,
            'total_records' => count($userIds),
            'operation_parameters' => [
                'user_ids' => $userIds,
            ],
        ]);

        AdminAuditLog::logBulkOperationStarted($adminUserId, 'user_bulk_reactivate', [
            'user_count' => count($userIds),
        ]);

        // Process in background
        dispatch(function() use ($operation, $userIds) {
            $this->processUserBulkReactivation($operation, $userIds);
        });

        return $operation;
    }

    /**
     * Process user import operation.
     */
    protected function processUserImport(AdminBulkOperation $operation, array $csvData): void
    {
        $operation->start();
        $results = ['imported' => [], 'skipped' => [], 'errors' => []];

        try {
            DB::beginTransaction();

            foreach ($csvData as $row) {
                try {
                    $this->validateUserImportRow($row);
                    
                    // Check if user already exists
                    $existingUser = User::where('email', $row['email'])->first();
                    if ($existingUser) {
                        $results['skipped'][] = "User {$row['email']} already exists";
                        $operation->incrementProgress(true);
                        continue;
                    }

                    // Create user
                    $userData = $this->prepareUserDataFromImport($row, $operation->operation_parameters);
                    $user = User::create($userData);

                    // Send welcome email if configured
                    if ($operation->operation_parameters['send_welcome_email'] ?? false) {
                        // TODO: Send welcome email
                    }

                    $results['imported'][] = $user->email;
                    $operation->incrementProgress(true);

                    AdminAuditLog::logUserCreated($operation->admin_user_id, $user);

                } catch (\Exception $e) {
                    $results['errors'][] = "Error importing {$row['email']}: " . $e->getMessage();
                    $operation->incrementProgress(false);
                    Log::error('Bulk import error', [
                        'operation_id' => $operation->id,
                        'row' => $row,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();
            $operation->complete(['import_results' => $results]);

        } catch (\Exception $e) {
            DB::rollBack();
            $operation->fail('Import operation failed: ' . $e->getMessage(), $results);
            Log::error('Bulk import operation failed', [
                'operation_id' => $operation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process user export operation.
     */
    protected function processUserExport(AdminBulkOperation $operation, array $filters): void
    {
        $operation->start();

        try {
            $query = $this->buildUserQuery($filters);
            $fileName = 'user_export_' . now()->format('Y_m_d_H_i_s') . '.csv';
            $filePath = storage_path('app/exports/' . $fileName);
            
            // Ensure directory exists
            if (!is_dir(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }

            $csv = Writer::createFromPath($filePath, 'w+');
            
            // Write headers
            $headers = [
                'ID', 'Name', 'Email', 'Phone', 'Company', 'Country', 'Role',
                'Is Active', 'Email Verified', 'Created At', 'Last Login',
                'Tenant Count', 'Admin Roles'
            ];
            $csv->insertOne($headers);

            // Process users in chunks
            $query->chunk(500, function($users) use ($csv, $operation) {
                foreach ($users as $user) {
                    $csv->insertOne([
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->phone,
                        $user->company_name,
                        $user->country,
                        $user->role,
                        $user->is_active ? 'Yes' : 'No',
                        $user->hasVerifiedEmail() ? 'Yes' : 'No',
                        $user->created_at->format('Y-m-d H:i:s'),
                        $user->last_login_at?->format('Y-m-d H:i:s') ?: 'Never',
                        $user->tenants()->count(),
                        $user->activeAdminRoles()->pluck('name')->join(', '),
                    ]);
                    
                    $operation->incrementProgress(true);
                }
            });

            $operation->complete([
                'export_file' => $fileName,
                'file_path' => $filePath,
                'file_size' => filesize($filePath),
            ]);

        } catch (\Exception $e) {
            $operation->fail('Export operation failed: ' . $e->getMessage());
            Log::error('Bulk export operation failed', [
                'operation_id' => $operation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process user bulk update operation.
     */
    protected function processUserBulkUpdate(AdminBulkOperation $operation, array $userIds, array $updates): void
    {
        $operation->start();
        $results = ['updated' => [], 'errors' => []];

        try {
            foreach ($userIds as $userId) {
                try {
                    $user = User::find($userId);
                    if (!$user) {
                        $results['errors'][] = "User ID {$userId} not found";
                        $operation->incrementProgress(false);
                        continue;
                    }

                    $oldValues = $user->toArray();
                    $user->update($updates);

                    $results['updated'][] = $user->email;
                    $operation->incrementProgress(true);

                    AdminAuditLog::logUserUpdated(
                        $operation->admin_user_id,
                        $user,
                        array_intersect_key($oldValues, $updates),
                        $updates
                    );

                } catch (\Exception $e) {
                    $results['errors'][] = "Error updating user ID {$userId}: " . $e->getMessage();
                    $operation->incrementProgress(false);
                }
            }

            $operation->complete(['update_results' => $results]);

        } catch (\Exception $e) {
            $operation->fail('Bulk update operation failed: ' . $e->getMessage(), $results);
        }
    }

    /**
     * Process user bulk suspension operation.
     */
    protected function processUserBulkSuspension(AdminBulkOperation $operation, array $userIds, string $reason): void
    {
        $operation->start();
        $results = ['suspended' => [], 'errors' => []];

        try {
            foreach ($userIds as $userId) {
                try {
                    $user = User::find($userId);
                    if (!$user) {
                        $results['errors'][] = "User ID {$userId} not found";
                        $operation->incrementProgress(false);
                        continue;
                    }

                    if ($user->isSuspended()) {
                        $results['errors'][] = "User {$user->email} is already suspended";
                        $operation->incrementProgress(false);
                        continue;
                    }

                    $user->suspend($reason, $operation->admin_user_id);
                    $results['suspended'][] = $user->email;
                    $operation->incrementProgress(true);

                    AdminAuditLog::logUserSuspended($operation->admin_user_id, $user, $reason);

                } catch (\Exception $e) {
                    $results['errors'][] = "Error suspending user ID {$userId}: " . $e->getMessage();
                    $operation->incrementProgress(false);
                }
            }

            $operation->complete(['suspension_results' => $results]);

        } catch (\Exception $e) {
            $operation->fail('Bulk suspension operation failed: ' . $e->getMessage(), $results);
        }
    }

    /**
     * Process user bulk reactivation operation.
     */
    protected function processUserBulkReactivation(AdminBulkOperation $operation, array $userIds): void
    {
        $operation->start();
        $results = ['reactivated' => [], 'errors' => []];

        try {
            foreach ($userIds as $userId) {
                try {
                    $user = User::find($userId);
                    if (!$user) {
                        $results['errors'][] = "User ID {$userId} not found";
                        $operation->incrementProgress(false);
                        continue;
                    }

                    if (!$user->isSuspended()) {
                        $results['errors'][] = "User {$user->email} is not suspended";
                        $operation->incrementProgress(false);
                        continue;
                    }

                    $user->reactivate();
                    $results['reactivated'][] = $user->email;
                    $operation->incrementProgress(true);

                    AdminAuditLog::logAction(
                        $operation->admin_user_id,
                        AdminAuditLog::ACTION_USER_REACTIVATED,
                        "User '{$user->email}' reactivated by admin",
                        $user,
                        ['suspended_at' => $user->suspended_at],
                        ['suspended_at' => null],
                        ['user_id' => $user->id],
                        AdminAuditLog::SEVERITY_INFO
                    );

                } catch (\Exception $e) {
                    $results['errors'][] = "Error reactivating user ID {$userId}: " . $e->getMessage();
                    $operation->incrementProgress(false);
                }
            }

            $operation->complete(['reactivation_results' => $results]);

        } catch (\Exception $e) {
            $operation->fail('Bulk reactivation operation failed: ' . $e->getMessage(), $results);
        }
    }

    /**
     * Build user query with filters.
     */
    protected function buildUserQuery(array $filters): Builder
    {
        $query = User::query();

        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['is_suspended'])) {
            if ($filters['is_suspended']) {
                $query->whereNotNull('suspended_at');
            } else {
                $query->whereNull('suspended_at');
            }
        }

        if (isset($filters['has_admin_role'])) {
            if ($filters['has_admin_role']) {
                $query->admins();
            } else {
                $query->where('is_super_admin', false)
                      ->whereDoesntHave('activeAdminRoles');
            }
        }

        if (isset($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (isset($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        return $query->with(['tenants', 'activeAdminRoles']);
    }

    /**
     * Validate user import row.
     */
    protected function validateUserImportRow(array $row): void
    {
        $required = ['name', 'email'];
        
        foreach ($required as $field) {
            if (empty($row[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing");
            }
        }

        if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email format: {$row['email']}");
        }
    }

    /**
     * Prepare user data from import row.
     */
    protected function prepareUserDataFromImport(array $row, array $options): array
    {
        $password = $row['password'] ?? Str::random(12);
        
        return [
            'name' => $row['name'],
            'email' => $row['email'],
            'password' => Hash::make($password),
            'phone' => $row['phone'] ?? null,
            'company_name' => $row['company_name'] ?? null,
            'country' => $row['country'] ?? null,
            'role' => $row['role'] ?? $options['default_role'] ?? 'user',
            'is_active' => true,
            'email_verified_at' => $options['force_email_verification'] ?? false ? now() : null,
        ];
    }
}