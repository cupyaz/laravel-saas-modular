<?php
session_start();
require_once '../vendor/autoload.php';

// Configurazione database SQLite
$pdo = new PDO('sqlite:../database/database.sqlite');

// Create subscription plans table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS subscription_plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT NOT NULL,
        price_monthly DECIMAL(8,2) NOT NULL DEFAULT 0,
        price_yearly DECIMAL(8,2) NOT NULL DEFAULT 0,
        features TEXT NOT NULL, -- JSON encoded features
        limits_data TEXT NULL, -- JSON encoded limits (storage, users, etc)
        is_popular INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        trial_days INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Create user subscriptions table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        plan_id INTEGER NOT NULL,
        status TEXT DEFAULT 'active', -- active, cancelled, expired, trial
        billing_cycle TEXT DEFAULT 'monthly', -- monthly, yearly
        current_period_start DATETIME NOT NULL,
        current_period_end DATETIME NOT NULL,
        trial_end DATETIME NULL,
        cancelled_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id),
        FOREIGN KEY (plan_id) REFERENCES subscription_plans (id)
    )
");

// Seed subscription plans if empty
$stmt = $pdo->query('SELECT COUNT(*) FROM subscription_plans');
if ($stmt->fetchColumn() == 0) {
    // Create default plans
    $plans = [
        [
            'name' => 'Free',
            'description' => 'Perfect for getting started',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'features' => json_encode([
                'Up to 5 projects',
                'Basic dashboard',
                'Email support',
                'Core features access',
                'Mobile app access'
            ]),
            'limits_data' => json_encode([
                'projects' => 5,
                'storage_gb' => 1,
                'api_calls_per_month' => 1000,
                'team_members' => 1
            ]),
            'is_popular' => 0,
            'trial_days' => 0
        ],
        [
            'name' => 'Pro',
            'description' => 'For growing businesses and teams',
            'price_monthly' => 19.99,
            'price_yearly' => 199.99, // 2 months free
            'features' => json_encode([
                'Unlimited projects',
                'Advanced analytics',
                'Priority email support',
                'API access',
                'Custom integrations',
                'Team collaboration',
                'Advanced reporting'
            ]),
            'limits_data' => json_encode([
                'projects' => -1, // unlimited
                'storage_gb' => 50,
                'api_calls_per_month' => 50000,
                'team_members' => 10
            ]),
            'is_popular' => 1,
            'trial_days' => 14
        ],
        [
            'name' => 'Enterprise',
            'description' => 'For large organizations with advanced needs',
            'price_monthly' => 49.99,
            'price_yearly' => 499.99, // 2 months free
            'features' => json_encode([
                'Everything in Pro',
                'Unlimited team members',
                'Advanced security features',
                'Custom branding',
                '24/7 phone support',
                'Dedicated account manager',
                'SSO integration',
                'Advanced compliance tools',
                'Custom workflows'
            ]),
            'limits_data' => json_encode([
                'projects' => -1,
                'storage_gb' => 500,
                'api_calls_per_month' => -1, // unlimited
                'team_members' => -1 // unlimited
            ]),
            'is_popular' => 0,
            'trial_days' => 30
        ]
    ];
    
    $stmt = $pdo->prepare('
        INSERT INTO subscription_plans (name, description, price_monthly, price_yearly, features, limits_data, is_popular, trial_days)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    
    foreach ($plans as $plan) {
        $stmt->execute([
            $plan['name'], $plan['description'], $plan['price_monthly'], $plan['price_yearly'],
            $plan['features'], $plan['limits_data'], $plan['is_popular'], $plan['trial_days']
        ]);
    }
}

// Get all active plans
$stmt = $pdo->query('SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly ASC');
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current user and subscription if logged in
$current_user = null;
$current_subscription = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current subscription
    $stmt = $pdo->prepare('
        SELECT us.*, sp.name as plan_name 
        FROM user_subscriptions us 
        LEFT JOIN subscription_plans sp ON us.plan_id = sp.id 
        WHERE us.user_id = ? AND us.status IN ("active", "trial") 
        ORDER BY us.created_at DESC 
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $current_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
}

$billing_cycle = $_GET['billing'] ?? 'monthly';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel SaaS - Subscription Plans</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <!-- Navigation -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-xl font-semibold text-gray-900">Laravel SaaS</a>
                    <span class="text-sm text-gray-500">→</span>
                    <span class="text-lg font-medium text-gray-700">Subscription Plans</span>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if ($current_user): ?>
                        <a href="dashboard.php" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                        <span class="text-sm text-gray-700">Welcome, <?= htmlspecialchars($current_user['name']) ?>!</span>
                        <a href="dashboard.php?logout=1" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm">
                            Logout
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-600 hover:text-gray-900">Login</a>
                        <a href="register.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded text-sm">
                            Sign Up
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Choose Your Plan</h1>
            <p class="text-xl text-gray-600 mb-8">Select the perfect plan for your needs and start growing your business</p>
            
            <!-- Current Plan Display -->
            <?php if ($current_subscription): ?>
                <div class="inline-block bg-green-100 border border-green-400 text-green-700 px-6 py-3 rounded-lg mb-6">
                    <strong>✅ Current Plan: <?= htmlspecialchars($current_subscription['plan_name']) ?></strong>
                    <span class="text-sm ml-2">(<?= ucfirst($current_subscription['billing_cycle']) ?>)</span>
                </div>
            <?php endif; ?>
            
            <!-- Billing Cycle Toggle -->
            <div class="flex justify-center mb-8">
                <div class="bg-gray-100 p-1 rounded-lg flex">
                    <a href="?billing=monthly" 
                       class="px-6 py-2 rounded-md text-sm font-medium transition-colors <?= $billing_cycle === 'monthly' ? 'bg-white text-gray-900 shadow' : 'text-gray-500 hover:text-gray-900' ?>">
                        Monthly
                    </a>
                    <a href="?billing=yearly" 
                       class="px-6 py-2 rounded-md text-sm font-medium transition-colors <?= $billing_cycle === 'yearly' ? 'bg-white text-gray-900 shadow' : 'text-gray-500 hover:text-gray-900' ?>">
                        Yearly
                        <span class="ml-1 text-xs text-green-600 font-bold">Save 17%</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Plans Grid -->
        <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto">
            <?php foreach ($plans as $plan): ?>
                <?php 
                $features = json_decode($plan['features'], true);
                $limits = json_decode($plan['limits_data'], true);
                $price = $billing_cycle === 'yearly' ? $plan['price_yearly'] : $plan['price_monthly'];
                $is_current_plan = $current_subscription && $current_subscription['plan_id'] == $plan['id'];
                $is_free = $plan['price_monthly'] == 0;
                ?>
                
                <div class="relative bg-white rounded-2xl shadow-lg overflow-hidden <?= $plan['is_popular'] ? 'ring-2 ring-indigo-500' : '' ?>">
                    
                    <?php if ($plan['is_popular']): ?>
                        <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
                            <span class="bg-indigo-500 text-white px-4 py-1 rounded-full text-sm font-medium">Most Popular</span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($is_current_plan): ?>
                        <div class="absolute top-0 right-0 bg-green-500 text-white px-3 py-1 rounded-bl-lg text-sm font-medium">
                            Current Plan
                        </div>
                    <?php endif; ?>
                    
                    <div class="p-8">
                        <!-- Plan Header -->
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($plan['name']) ?></h3>
                            <p class="text-gray-600 mb-6"><?= htmlspecialchars($plan['description']) ?></p>
                            
                            <div class="mb-6">
                                <?php if ($is_free): ?>
                                    <span class="text-5xl font-bold text-gray-900">Free</span>
                                <?php else: ?>
                                    <span class="text-5xl font-bold text-gray-900">$<?= number_format($price, 0) ?></span>
                                    <span class="text-gray-500 ml-2">
                                        / <?= $billing_cycle === 'yearly' ? 'year' : 'month' ?>
                                    </span>
                                    <?php if ($billing_cycle === 'yearly' && $plan['price_monthly'] > 0): ?>
                                        <div class="text-sm text-green-600 mt-1">
                                            Save $<?= number_format(($plan['price_monthly'] * 12) - $plan['price_yearly'], 0) ?> per year
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($plan['trial_days'] > 0 && !$is_current_plan && !$is_free): ?>
                                <div class="text-sm text-indigo-600 mb-4">
                                    🎉 <?= $plan['trial_days'] ?>-day free trial
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Features List -->
                        <ul class="space-y-4 mb-8">
                            <?php foreach ($features as $feature): ?>
                                <li class="flex items-start">
                                    <span class="text-green-500 mr-3 mt-0.5">✓</span>
                                    <span class="text-gray-700"><?= htmlspecialchars($feature) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <!-- Limits Display -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <h4 class="font-medium text-gray-900 mb-2">Plan Limits:</h4>
                            <div class="space-y-1 text-sm text-gray-600">
                                <?php foreach ($limits as $key => $value): ?>
                                    <div class="flex justify-between">
                                        <span><?= ucfirst(str_replace('_', ' ', $key)) ?>:</span>
                                        <span class="font-medium">
                                            <?= $value == -1 ? 'Unlimited' : ($key === 'storage_gb' ? $value . ' GB' : number_format($value)) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Action Button -->
                        <div class="text-center">
                            <?php if ($is_current_plan): ?>
                                <button class="w-full bg-gray-100 text-gray-500 py-3 px-6 rounded-lg font-medium cursor-not-allowed">
                                    Current Plan
                                </button>
                            <?php elseif (!$current_user): ?>
                                <a href="register.php" class="w-full block bg-indigo-600 hover:bg-indigo-700 text-white py-3 px-6 rounded-lg font-medium transition-colors">
                                    Get Started
                                </a>
                            <?php elseif ($is_free): ?>
                                <a href="subscription.php?action=downgrade&plan=<?= $plan['id'] ?>" 
                                   class="w-full block bg-gray-600 hover:bg-gray-700 text-white py-3 px-6 rounded-lg font-medium transition-colors">
                                    Downgrade to Free
                                </a>
                            <?php else: ?>
                                <a href="subscription.php?action=upgrade&plan=<?= $plan['id'] ?>&billing=<?= $billing_cycle ?>" 
                                   class="w-full block bg-indigo-600 hover:bg-indigo-700 text-white py-3 px-6 rounded-lg font-medium transition-colors">
                                    <?= $plan['trial_days'] > 0 ? 'Start Free Trial' : 'Upgrade Now' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- FAQ Section -->
        <div class="mt-16 max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-8">Frequently Asked Questions</h2>
            <div class="grid md:grid-cols-2 gap-8">
                <div class="bg-white rounded-lg p-6 shadow">
                    <h3 class="font-semibold text-gray-900 mb-2">Can I change plans anytime?</h3>
                    <p class="text-gray-600 text-sm">Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately for upgrades, or at the end of your current billing cycle for downgrades.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow">
                    <h3 class="font-semibold text-gray-900 mb-2">What happens during the free trial?</h3>
                    <p class="text-gray-600 text-sm">You get full access to all plan features during the trial period. You can cancel anytime during the trial without being charged.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow">
                    <h3 class="font-semibold text-gray-900 mb-2">Do you offer refunds?</h3>
                    <p class="text-gray-600 text-sm">Yes, we offer a 30-day money-back guarantee for all paid plans. Contact our support team if you're not satisfied.</p>
                </div>
                <div class="bg-white rounded-lg p-6 shadow">
                    <h3 class="font-semibold text-gray-900 mb-2">Is there a setup fee?</h3>
                    <p class="text-gray-600 text-sm">No setup fees, ever. The price you see is exactly what you'll pay, with no hidden charges or additional costs.</p>
                </div>
            </div>
        </div>
        
        <!-- US-005 Features Display -->
        <div class="mt-12 bg-blue-50 border border-blue-200 rounded-lg p-8">
            <h3 class="text-lg font-medium text-blue-900 mb-4">✅ US-005: Subscription Plan Selection Features</h3>
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <ul class="space-y-2 text-sm text-blue-800">
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Clear feature comparison for all plans
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Monthly/yearly pricing with savings display
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Current plan highlighting
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Mobile-responsive design
                        </li>
                    </ul>
                </div>
                <div>
                    <ul class="space-y-2 text-sm text-blue-800">
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Free tier limitations clearly displayed
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Trial periods with upgrade prompts
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Upgrade/downgrade flow ready
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Plan limits and features comparison
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>