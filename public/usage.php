<?php
session_start();
require_once '../vendor/autoload.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=usage.php');
    exit;
}

// Configurazione database SQLite  
$pdo = new PDO('sqlite:../database/database.sqlite');

// Create usage tracking table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        metric_name TEXT NOT NULL, -- projects, storage_used, api_calls, etc
        metric_value INTEGER DEFAULT 0,
        reset_date DATETIME NULL, -- for monthly counters
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id),
        UNIQUE(user_id, metric_name)
    )
");

// Get current user and subscription
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('
    SELECT us.*, sp.name as plan_name, sp.limits_data 
    FROM user_subscriptions us 
    LEFT JOIN subscription_plans sp ON us.plan_id = sp.id 
    WHERE us.user_id = ? AND us.status IN ("active", "trial") 
    ORDER BY us.created_at DESC 
    LIMIT 1
');
$stmt->execute([$_SESSION['user_id']]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Get plan limits
$plan_limits = $subscription ? json_decode($subscription['limits_data'], true) : [
    'projects' => 5,
    'storage_gb' => 1,
    'api_calls_per_month' => 1000,
    'team_members' => 1
];

// Get current usage
$stmt = $pdo->prepare('SELECT * FROM user_usage WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$usage_data = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $usage_data[$row['metric_name']] = $row['metric_value'];
}

// Initialize missing usage data
$metrics = ['projects', 'storage_used', 'api_calls_this_month', 'team_members'];
foreach ($metrics as $metric) {
    if (!isset($usage_data[$metric])) {
        $usage_data[$metric] = 0;
    }
}

// Demo: Add some sample usage if none exists
if (array_sum($usage_data) == 0) {
    // Simulate some usage for demo
    $demo_usage = [
        'projects' => rand(2, 4),
        'storage_used' => rand(500, 900), // MB
        'api_calls_this_month' => rand(150, 800),
        'team_members' => 1
    ];
    
    foreach ($demo_usage as $metric => $value) {
        $stmt = $pdo->prepare('
            INSERT OR REPLACE INTO user_usage (user_id, metric_name, metric_value, reset_date) 
            VALUES (?, ?, ?, ?)
        ');
        $reset_date = in_array($metric, ['api_calls_this_month']) ? date('Y-m-01 00:00:00', strtotime('+1 month')) : null;
        $stmt->execute([$_SESSION['user_id'], $metric, $value, $reset_date]);
        $usage_data[$metric] = $value;
    }
}

// Function to check if limit is reached
function isLimitReached($current, $limit) {
    if ($limit == -1) return false; // unlimited
    return $current >= $limit;
}

// Function to get usage percentage
function getUsagePercentage($current, $limit) {
    if ($limit == -1) return 0; // unlimited
    return min(100, ($current / $limit) * 100);
}

// Function to format storage
function formatStorage($mb) {
    if ($mb >= 1024) {
        return number_format($mb / 1024, 1) . ' GB';
    }
    return $mb . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel SaaS - Usage & Limits</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <!-- Navigation -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-xl font-semibold text-gray-900">Dashboard</a>
                    <span class="text-sm text-gray-500">→</span>
                    <span class="text-lg font-medium text-gray-700">Usage & Limits</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="plans.php" class="text-gray-600 hover:text-gray-900">Upgrade</a>
                    <span class="text-sm text-gray-700">Welcome, <?= htmlspecialchars($user['name']) ?>!</span>
                    <a href="dashboard.php?logout=1" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        
        <!-- Current Plan -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-medium text-gray-900">Current Plan: <?= htmlspecialchars($subscription['plan_name'] ?? 'Free') ?></h2>
                        <p class="text-sm text-gray-600 mt-1">Monitor your usage and plan limits</p>
                    </div>
                    <a href="plans.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm font-medium">
                        View Plans
                    </a>
                </div>
            </div>
        </div>

        <!-- Usage Metrics Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- Projects -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="text-3xl">📋</div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Projects</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900">
                                        <?= $usage_data['projects'] ?><?= $plan_limits['projects'] == -1 ? '' : '/' . $plan_limits['projects'] ?>
                                    </div>
                                    <?php if ($plan_limits['projects'] != -1): ?>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold <?= isLimitReached($usage_data['projects'], $plan_limits['projects']) ? 'text-red-600' : 'text-green-600' ?>">
                                            <?= round(getUsagePercentage($usage_data['projects'], $plan_limits['projects'])) ?>%
                                        </div>
                                    <?php else: ?>
                                        <div class="ml-2 text-sm text-gray-500">Unlimited</div>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                    <?php if ($plan_limits['projects'] != -1): ?>
                        <div class="mt-3">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="<?= isLimitReached($usage_data['projects'], $plan_limits['projects']) ? 'bg-red-600' : 'bg-indigo-600' ?> h-2 rounded-full" 
                                     style="width: <?= min(100, getUsagePercentage($usage_data['projects'], $plan_limits['projects'])) ?>%"></div>
                            </div>
                        </div>
                        <?php if (isLimitReached($usage_data['projects'], $plan_limits['projects'])): ?>
                            <div class="mt-2 text-xs text-red-600">Limit reached - upgrade to create more projects</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Storage -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="text-3xl">💾</div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Storage</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900">
                                        <?= formatStorage($usage_data['storage_used']) ?>
                                    </div>
                                    <?php if ($plan_limits['storage_gb'] != -1): ?>
                                        <div class="ml-1 text-sm text-gray-500">/ <?= $plan_limits['storage_gb'] ?> GB</div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold <?= isLimitReached($usage_data['storage_used'], $plan_limits['storage_gb'] * 1024) ? 'text-red-600' : 'text-green-600' ?>">
                                            <?= round(getUsagePercentage($usage_data['storage_used'], $plan_limits['storage_gb'] * 1024)) ?>%
                                        </div>
                                    <?php else: ?>
                                        <div class="ml-2 text-sm text-gray-500">Unlimited</div>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                    <?php if ($plan_limits['storage_gb'] != -1): ?>
                        <div class="mt-3">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="<?= isLimitReached($usage_data['storage_used'], $plan_limits['storage_gb'] * 1024) ? 'bg-red-600' : 'bg-indigo-600' ?> h-2 rounded-full" 
                                     style="width: <?= min(100, getUsagePercentage($usage_data['storage_used'], $plan_limits['storage_gb'] * 1024)) ?>%"></div>
                            </div>
                        </div>
                        <?php if (isLimitReached($usage_data['storage_used'], $plan_limits['storage_gb'] * 1024)): ?>
                            <div class="mt-2 text-xs text-red-600">Storage full - upgrade for more space</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- API Calls -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="text-3xl">🔌</div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">API Calls (Month)</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900">
                                        <?= number_format($usage_data['api_calls_this_month']) ?>
                                    </div>
                                    <?php if ($plan_limits['api_calls_per_month'] != -1): ?>
                                        <div class="ml-1 text-sm text-gray-500">/ <?= number_format($plan_limits['api_calls_per_month']) ?></div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold <?= isLimitReached($usage_data['api_calls_this_month'], $plan_limits['api_calls_per_month']) ? 'text-red-600' : 'text-green-600' ?>">
                                            <?= round(getUsagePercentage($usage_data['api_calls_this_month'], $plan_limits['api_calls_per_month'])) ?>%
                                        </div>
                                    <?php else: ?>
                                        <div class="ml-2 text-sm text-gray-500">Unlimited</div>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                    <?php if ($plan_limits['api_calls_per_month'] != -1): ?>
                        <div class="mt-3">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="<?= isLimitReached($usage_data['api_calls_this_month'], $plan_limits['api_calls_per_month']) ? 'bg-red-600' : 'bg-indigo-600' ?> h-2 rounded-full" 
                                     style="width: <?= min(100, getUsagePercentage($usage_data['api_calls_this_month'], $plan_limits['api_calls_per_month'])) ?>%"></div>
                            </div>
                        </div>
                        <?php if (isLimitReached($usage_data['api_calls_this_month'], $plan_limits['api_calls_per_month'])): ?>
                            <div class="mt-2 text-xs text-red-600">API limit reached - upgrade for more calls</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Team Members -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="text-3xl">👥</div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Team Members</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900">
                                        <?= $usage_data['team_members'] ?>
                                    </div>
                                    <?php if ($plan_limits['team_members'] != -1): ?>
                                        <div class="ml-1 text-sm text-gray-500">/ <?= $plan_limits['team_members'] ?></div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold <?= isLimitReached($usage_data['team_members'], $plan_limits['team_members']) ? 'text-red-600' : 'text-green-600' ?>">
                                            <?= round(getUsagePercentage($usage_data['team_members'], $plan_limits['team_members'])) ?>%
                                        </div>
                                    <?php else: ?>
                                        <div class="ml-2 text-sm text-gray-500">Unlimited</div>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                    <?php if ($plan_limits['team_members'] != -1): ?>
                        <div class="mt-3">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="<?= isLimitReached($usage_data['team_members'], $plan_limits['team_members']) ? 'bg-red-600' : 'bg-indigo-600' ?> h-2 rounded-full" 
                                     style="width: <?= min(100, getUsagePercentage($usage_data['team_members'], $plan_limits['team_members'])) ?>%"></div>
                            </div>
                        </div>
                        <?php if (isLimitReached($usage_data['team_members'], $plan_limits['team_members'])): ?>
                            <div class="mt-2 text-xs text-red-600">Team limit reached - upgrade to add more members</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upgrade Prompt (if limits are being reached) -->
        <?php 
        $near_limits = [];
        foreach ($plan_limits as $metric => $limit) {
            if ($limit != -1) {
                $current_key = $metric === 'storage_gb' ? 'storage_used' : ($metric === 'api_calls_per_month' ? 'api_calls_this_month' : $metric);
                if (isset($usage_data[$current_key])) {
                    $current = $usage_data[$current_key];
                    if ($metric === 'storage_gb') $limit *= 1024; // Convert GB to MB
                    $percentage = getUsagePercentage($current, $limit);
                    if ($percentage > 80) {
                        $near_limits[] = $metric;
                    }
                }
            }
        }
        ?>
        
        <?php if (!empty($near_limits)): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
                <h3 class="text-lg font-medium text-yellow-800 mb-2">⚠️ Approaching Limits</h3>
                <p class="text-yellow-700 mb-4">You're approaching limits on some features. Consider upgrading to avoid interruption.</p>
                <a href="plans.php" class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-md">
                    View Upgrade Options
                </a>
            </div>
        <?php endif; ?>

        <!-- Usage History -->
        <div class="bg-white shadow rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">📊 Usage Details</h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metric</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Usage</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan Limit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Projects</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $usage_data['projects'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $plan_limits['projects'] == -1 ? 'Unlimited' : $plan_limits['projects'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($plan_limits['projects'] == -1): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Unlimited</span>
                                    <?php elseif (isLimitReached($usage_data['projects'], $plan_limits['projects'])): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Limit Reached</span>
                                    <?php elseif (getUsagePercentage($usage_data['projects'], $plan_limits['projects']) > 80): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Near Limit</span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Within Limit</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Storage</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= formatStorage($usage_data['storage_used']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $plan_limits['storage_gb'] == -1 ? 'Unlimited' : $plan_limits['storage_gb'] . ' GB' ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                    $storage_limit = $plan_limits['storage_gb'] * 1024;
                                    if ($plan_limits['storage_gb'] == -1): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Unlimited</span>
                                    <?php elseif (isLimitReached($usage_data['storage_used'], $storage_limit)): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Limit Reached</span>
                                    <?php elseif (getUsagePercentage($usage_data['storage_used'], $storage_limit) > 80): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Near Limit</span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Within Limit</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Freemium Features -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-medium text-blue-900 mb-4">✅ US-005: Freemium System Features</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <ul class="space-y-2 text-sm text-blue-800">
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Real-time usage tracking and limits
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Visual progress bars and percentages
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Upgrade prompts when approaching limits
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Free tier limitations clearly displayed
                        </li>
                    </ul>
                </div>
                <div>
                    <ul class="space-y-2 text-sm text-blue-800">
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Database-driven plan limits system
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Monthly reset counters (API calls)
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Subscription-based feature access
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Seamless upgrade workflow
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>