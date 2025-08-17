<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\UpgradePrompt;
use App\Models\ABTestVariant;
use App\Models\ABTestAssignment;
use Illuminate\Support\Facades\Log;

class ABTestService
{
    /**
     * Get the appropriate variant for a tenant and prompt.
     */
    public function getVariantForTenant(Tenant $tenant, UpgradePrompt $prompt): string
    {
        // If prompt doesn't have A/B testing configured, return control
        if (!$prompt->ab_test_config || !isset($prompt->ab_test_config['test_name'])) {
            return 'control';
        }

        $testName = $prompt->ab_test_config['test_name'];

        // Check if tenant already has an assignment for this test
        $existingAssignment = ABTestAssignment::where('tenant_id', $tenant->id)
            ->where('test_name', $testName)
            ->first();

        if ($existingAssignment) {
            return $existingAssignment->variant_name;
        }

        // Assign tenant to a variant
        return $this->assignTenantToVariant($tenant, $testName);
    }

    /**
     * Assign a tenant to an A/B test variant.
     */
    public function assignTenantToVariant(Tenant $tenant, string $testName): string
    {
        // Get active variants for this test
        $variants = ABTestVariant::forTest($testName)
            ->active()
            ->orderBy('variant_name')
            ->get();

        if ($variants->isEmpty()) {
            return 'control';
        }

        // Validate traffic percentages sum to 100
        $totalTraffic = $variants->sum('traffic_percentage');
        if ($totalTraffic !== 100) {
            Log::warning('A/B test traffic percentages do not sum to 100', [
                'test_name' => $testName,
                'total_traffic' => $totalTraffic,
            ]);
            return 'control';
        }

        // Use tenant ID for consistent hash-based assignment
        $hash = crc32($tenant->id . $testName) % 100;
        $cumulativePercentage = 0;

        foreach ($variants as $variant) {
            $cumulativePercentage += $variant->traffic_percentage;
            if ($hash < $cumulativePercentage) {
                // Assign tenant to this variant
                ABTestAssignment::create([
                    'tenant_id' => $tenant->id,
                    'test_name' => $testName,
                    'variant_name' => $variant->variant_name,
                    'assigned_at' => now(),
                ]);

                Log::info('Tenant assigned to A/B test variant', [
                    'tenant_id' => $tenant->id,
                    'test_name' => $testName,
                    'variant' => $variant->variant_name,
                ]);

                return $variant->variant_name;
            }
        }

        // Fallback to control
        return 'control';
    }

    /**
     * Create a new A/B test with variants.
     */
    public function createABTest(
        string $testName,
        array $variants,
        \DateTime $startDate,
        ?\DateTime $endDate = null
    ): bool {
        // Validate traffic percentages
        $totalTraffic = array_sum(array_column($variants, 'traffic_percentage'));
        if ($totalTraffic !== 100) {
            throw new \InvalidArgumentException('Traffic percentages must sum to 100');
        }

        try {
            foreach ($variants as $variantData) {
                ABTestVariant::create([
                    'test_name' => $testName,
                    'variant_name' => $variantData['name'],
                    'configuration' => $variantData['configuration'] ?? [],
                    'traffic_percentage' => $variantData['traffic_percentage'],
                    'is_active' => true,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'success_metrics' => $variantData['success_metrics'] ?? ['conversion_rate'],
                ]);
            }

            Log::info('A/B test created', [
                'test_name' => $testName,
                'variants' => count($variants),
                'start_date' => $startDate,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create A/B test', [
                'test_name' => $testName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * End an A/B test and determine the winner.
     */
    public function endABTest(string $testName): array
    {
        $variants = ABTestVariant::forTest($testName)->get();
        
        if ($variants->isEmpty()) {
            throw new \InvalidArgumentException("A/B test '{$testName}' not found");
        }

        // Deactivate all variants
        ABTestVariant::forTest($testName)->update([
            'is_active' => false,
            'end_date' => now(),
        ]);

        // Calculate results for each variant
        $results = [];
        foreach ($variants as $variant) {
            $results[] = [
                'variant_name' => $variant->variant_name,
                'assignments' => $variant->getAssignmentCount(),
                'conversion_rate' => $variant->getConversionRate(),
                'click_through_rate' => $variant->getClickThroughRate(),
                'statistical_significance' => $variant->getStatisticalSignificance(),
            ];
        }

        // Determine winner (highest conversion rate with statistical significance)
        $winner = $this->determineWinner($results);

        Log::info('A/B test ended', [
            'test_name' => $testName,
            'winner' => $winner['variant_name'] ?? 'No clear winner',
            'results' => $results,
        ]);

        return [
            'test_name' => $testName,
            'ended_at' => now(),
            'winner' => $winner,
            'results' => $results,
        ];
    }

    /**
     * Determine the winning variant from test results.
     */
    private function determineWinner(array $results): ?array
    {
        $significantVariants = array_filter($results, function ($result) {
            return $result['statistical_significance']['significant'] ?? false;
        });

        if (empty($significantVariants)) {
            return null; // No statistically significant winner
        }

        // Find variant with highest conversion rate among significant ones
        usort($significantVariants, function ($a, $b) {
            return $b['conversion_rate'] <=> $a['conversion_rate'];
        });

        return $significantVariants[0];
    }

    /**
     * Get detailed test results for analysis.
     */
    public function getTestResults(string $testName): array
    {
        $variants = ABTestVariant::forTest($testName)->get();
        
        if ($variants->isEmpty()) {
            throw new \InvalidArgumentException("A/B test '{$testName}' not found");
        }

        $isActive = $variants->first()->isRunning();
        $results = [];

        foreach ($variants as $variant) {
            $results[] = [
                'variant_name' => $variant->variant_name,
                'traffic_percentage' => $variant->traffic_percentage,
                'assignments' => $variant->getAssignmentCount(),
                'conversion_rate' => $variant->getConversionRate(),
                'click_through_rate' => $variant->getClickThroughRate(),
                'statistical_significance' => $variant->getStatisticalSignificance(),
                'configuration' => $variant->configuration,
            ];
        }

        return [
            'test_name' => $testName,
            'is_active' => $isActive,
            'start_date' => $variants->first()->start_date,
            'end_date' => $variants->first()->end_date,
            'variants' => $results,
            'winner' => $isActive ? null : $this->determineWinner($results),
        ];
    }

    /**
     * Get all active A/B tests.
     */
    public function getActiveTests(): array
    {
        $activeTests = ABTestVariant::active()
            ->select('test_name')
            ->distinct()
            ->pluck('test_name');

        $tests = [];
        foreach ($activeTests as $testName) {
            $tests[] = $this->getTestResults($testName);
        }

        return $tests;
    }

    /**
     * Check if a tenant should be excluded from A/B testing.
     */
    public function shouldExcludeTenant(Tenant $tenant): bool
    {
        // Exclude internal/admin tenants
        if ($tenant->getConfig('internal_tenant', false)) {
            return true;
        }

        // Exclude tenants on specific plans
        $excludedPlans = ['enterprise']; // Enterprise customers get consistent experience
        $currentPlan = $tenant->currentPlan();
        if ($currentPlan && in_array($currentPlan->slug, $excludedPlans)) {
            return true;
        }

        // Exclude new tenants (less than 7 days old)
        if ($tenant->created_at->diffInDays(now()) < 7) {
            return true;
        }

        return false;
    }

    /**
     * Get tenant's A/B test assignments.
     */
    public function getTenantAssignments(Tenant $tenant): array
    {
        return ABTestAssignment::where('tenant_id', $tenant->id)
            ->get()
            ->map(function ($assignment) {
                return [
                    'test_name' => $assignment->test_name,
                    'variant_name' => $assignment->variant_name,
                    'assigned_at' => $assignment->assigned_at,
                ];
            })
            ->toArray();
    }
}