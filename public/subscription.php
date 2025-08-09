<?php
session_start();
require_once '../vendor/autoload.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=subscription.php');
    exit;
}

// Configurazione database SQLite
$pdo = new PDO('sqlite:../database/database.sqlite');

$errors = [];
$success = false;
$action = $_GET['action'] ?? '';
$plan_id = $_GET['plan'] ?? '';
$billing_cycle = $_GET['billing'] ?? 'monthly';

// Get current user
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get current subscription
$stmt = $pdo->prepare('
    SELECT us.*, sp.name as plan_name, sp.price_monthly, sp.price_yearly
    FROM user_subscriptions us 
    LEFT JOIN subscription_plans sp ON us.plan_id = sp.id 
    WHERE us.user_id = ? AND us.status IN ("active", "trial") 
    ORDER BY us.created_at DESC 
    LIMIT 1
');
$stmt->execute([$_SESSION['user_id']]);
$current_subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Get selected plan details
$selected_plan = null;
if ($plan_id) {
    $stmt = $pdo->prepare('SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1');
    $stmt->execute([$plan_id]);
    $selected_plan = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle subscription actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action && $selected_plan) {
    
    if ($action === 'upgrade' || $action === 'downgrade') {
        try {
            $pdo->beginTransaction();
            
            // Cancel current subscription if exists
            if ($current_subscription) {
                $stmt = $pdo->prepare('
                    UPDATE user_subscriptions 
                    SET status = "cancelled", cancelled_at = datetime("now") 
                    WHERE id = ?
                ');
                $stmt->execute([$current_subscription['id']]);
            }
            
            // Create new subscription
            $is_trial = $selected_plan['trial_days'] > 0 && $selected_plan['price_monthly'] > 0;
            $status = $is_trial ? 'trial' : 'active';
            
            $start_date = date('Y-m-d H:i:s');
            if ($billing_cycle === 'yearly') {
                $end_date = date('Y-m-d H:i:s', strtotime('+1 year'));
            } else {
                $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
            }
            
            $trial_end = $is_trial ? date('Y-m-d H:i:s', strtotime('+' . $selected_plan['trial_days'] . ' days')) : null;
            
            $stmt = $pdo->prepare('
                INSERT INTO user_subscriptions 
                (user_id, plan_id, status, billing_cycle, current_period_start, current_period_end, trial_end)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $_SESSION['user_id'], 
                $selected_plan['id'], 
                $status, 
                $billing_cycle, 
                $start_date, 
                $end_date, 
                $trial_end
            ]);
            
            $pdo->commit();
            $success = true;
            
            // Refresh current subscription
            $stmt = $pdo->prepare('
                SELECT us.*, sp.name as plan_name, sp.price_monthly, sp.price_yearly
                FROM user_subscriptions us 
                LEFT JOIN subscription_plans sp ON us.plan_id = sp.id 
                WHERE us.user_id = ? AND us.status IN ("active", "trial") 
                ORDER BY us.created_at DESC 
                LIMIT 1
            ');
            $stmt->execute([$_SESSION['user_id']]);
            $current_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['general'] = 'Failed to process subscription change. Please try again.';
        }
    }
}

// If no plan selected and no current subscription, default to free plan
if (!$current_subscription) {
    $stmt = $pdo->prepare('SELECT * FROM subscription_plans WHERE price_monthly = 0 LIMIT 1');
    $stmt->execute();
    $free_plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($free_plan) {
        // Auto-assign free plan
        $stmt = $pdo->prepare('
            INSERT INTO user_subscriptions 
            (user_id, plan_id, status, billing_cycle, current_period_start, current_period_end)
            VALUES (?, ?, "active", "monthly", datetime("now"), datetime("now", "+1 year"))
        ');
        $stmt->execute([$_SESSION['user_id'], $free_plan['id']]);
        
        // Refresh current subscription
        $stmt = $pdo->prepare('
            SELECT us.*, sp.name as plan_name, sp.price_monthly, sp.price_yearly
            FROM user_subscriptions us 
            LEFT JOIN subscription_plans sp ON us.plan_id = sp.id 
            WHERE us.user_id = ? AND us.status IN ("active", "trial") 
            ORDER BY us.created_at DESC 
            LIMIT 1
        ');
        $stmt->execute([$_SESSION['user_id']]);
        $current_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel SaaS - Subscription Management</title>
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
                    <span class="text-lg font-medium text-gray-700">Subscription</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="plans.php" class="text-gray-600 hover:text-gray-900">View Plans</a>
                    <span class="text-sm text-gray-700">Welcome, <?= htmlspecialchars($user['name']) ?>!</span>
                    <a href="dashboard.php?logout=1" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        
        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                <h3 class="font-semibold">✅ Subscription Updated!</h3>
                <p class="mt-1">Your subscription has been successfully updated.</p>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['general'])): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                <?= htmlspecialchars($errors['general']) ?>
            </div>
        <?php endif; ?>

        <!-- Current Subscription -->
        <?php if ($current_subscription): ?>
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-6">Current Subscription</h2>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Plan:</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($current_subscription['plan_name']) ?>
                                        <?php if ($current_subscription['status'] === 'trial'): ?>
                                            <span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Trial</span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Status:</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        <span class="capitalize <?= $current_subscription['status'] === 'active' ? 'text-green-600' : 'text-blue-600' ?>">
                                            <?= $current_subscription['status'] ?>
                                        </span>
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Billing:</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        $<?= number_format($current_subscription['billing_cycle'] === 'yearly' ? $current_subscription['price_yearly'] : $current_subscription['price_monthly'], 2) ?>
                                        / <?= $current_subscription['billing_cycle'] === 'yearly' ? 'year' : 'month' ?>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Current Period:</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        <?= date('M d, Y', strtotime($current_subscription['current_period_start'])) ?> - 
                                        <?= date('M d, Y', strtotime($current_subscription['current_period_end'])) ?>
                                    </dd>
                                </div>
                                <?php if ($current_subscription['trial_end']): ?>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-500">Trial Ends:</dt>
                                        <dd class="text-sm font-medium text-blue-600">
                                            <?= date('M d, Y', strtotime($current_subscription['trial_end'])) ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Next Billing:</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        <?= date('M d, Y', strtotime($current_subscription['current_period_end'])) ?>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Subscription Action -->
        <?php if ($action && $selected_plan): ?>
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-6">
                        <?= ucfirst($action) ?> to <?= htmlspecialchars($selected_plan['name']) ?> Plan
                    </h2>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <h3 class="font-medium text-blue-900 mb-2">Plan Details</h3>
                        <div class="grid md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <p><strong>Plan:</strong> <?= htmlspecialchars($selected_plan['name']) ?></p>
                                <p><strong>Price:</strong> 
                                    $<?= number_format($billing_cycle === 'yearly' ? $selected_plan['price_yearly'] : $selected_plan['price_monthly'], 2) ?>
                                    / <?= $billing_cycle === 'yearly' ? 'year' : 'month' ?>
                                </p>
                                <?php if ($selected_plan['trial_days'] > 0 && $selected_plan['price_monthly'] > 0): ?>
                                    <p><strong>Trial:</strong> <?= $selected_plan['trial_days'] ?> days free</p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p><strong>Features:</strong></p>
                                <?php 
                                $features = json_decode($selected_plan['features'], true);
                                echo '<ul class="text-xs text-gray-600 mt-1">';
                                foreach (array_slice($features, 0, 3) as $feature) {
                                    echo '<li>• ' . htmlspecialchars($feature) . '</li>';
                                }
                                echo '</ul>';
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" class="space-y-6">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <h4 class="font-medium text-yellow-800 mb-2">⚠️ Important Notes:</h4>
                            <ul class="text-sm text-yellow-700 space-y-1">
                                <?php if ($action === 'upgrade'): ?>
                                    <li>• Your new plan will be active immediately</li>
                                    <li>• You'll be charged at the end of your current billing cycle</li>
                                    <?php if ($selected_plan['trial_days'] > 0): ?>
                                        <li>• Free trial includes all premium features</li>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <li>• Downgrade will take effect at the end of your current billing cycle</li>
                                    <li>• You'll retain current features until then</li>
                                    <li>• No refund for remaining time on current plan</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <div class="flex space-x-4">
                            <button type="submit" 
                                    class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-3 px-6 rounded-lg font-medium transition-colors">
                                Confirm <?= ucfirst($action) ?>
                            </button>
                            <a href="plans.php" 
                               class="flex-1 text-center bg-gray-200 hover:bg-gray-300 text-gray-800 py-3 px-6 rounded-lg font-medium transition-colors">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
        <?php else: ?>
            <!-- No Action - Show Options -->
            <div class="bg-white shadow rounded-lg">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-6">Manage Your Subscription</h2>
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <a href="plans.php" 
                           class="block p-4 border border-gray-200 rounded-lg hover:border-indigo-300 transition-colors">
                            <h3 class="font-medium text-gray-900 mb-2">📋 View All Plans</h3>
                            <p class="text-sm text-gray-600">Compare features and pricing for all available plans</p>
                        </a>
                        
                        <a href="profile.php" 
                           class="block p-4 border border-gray-200 rounded-lg hover:border-indigo-300 transition-colors">
                            <h3 class="font-medium text-gray-900 mb-2">👤 Update Profile</h3>
                            <p class="text-sm text-gray-600">Manage your account information and preferences</p>
                        </a>
                        
                        <a href="dashboard.php" 
                           class="block p-4 border border-gray-200 rounded-lg hover:border-indigo-300 transition-colors">
                            <h3 class="font-medium text-gray-900 mb-2">📊 Dashboard</h3>
                            <p class="text-sm text-gray-600">Return to your main dashboard</p>
                        </a>
                        
                        <div class="block p-4 border border-gray-200 rounded-lg bg-gray-50">
                            <h3 class="font-medium text-gray-500 mb-2">💳 Billing History</h3>
                            <p class="text-sm text-gray-500">Coming soon - view your payment history</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Demo Note -->
        <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <h3 class="font-medium text-yellow-800 mb-2">🚀 Demo Mode</h3>
            <p class="text-yellow-700 text-sm">
                This is a demonstration of the subscription system. In a production environment, this would integrate with payment processors like Stripe or PayPal. 
                For now, subscription changes are simulated and no actual payments are processed.
            </p>
        </div>
    </div>
</body>
</html>