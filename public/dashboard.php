<?php
session_start();
require_once '../vendor/autoload.php';

// Configurazione database SQLite
$pdo = new PDO('sqlite:../database/database.sqlite');

// Check se utente è loggato
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=dashboard.php');
    exit;
}

// Check session timeout (2 ore)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 7200)) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

// Get user info
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get user's login history
$stmt = $pdo->prepare('
    SELECT * FROM login_attempts 
    WHERE email = ? AND success = 1 
    ORDER BY attempted_at DESC 
    LIMIT 5
');
$stmt->execute([$user['email']]);
$login_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle logout
if (isset($_GET['logout'])) {
    // Remove remember token if exists
    if (isset($_COOKIE['remember_token'])) {
        $stmt = $pdo->prepare('DELETE FROM user_sessions WHERE session_token = ?');
        $stmt->execute([$_COOKIE['remember_token']]);
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    session_destroy();
    header('Location: login.php?message=logged_out');
    exit;
}

// Calculate session info
$session_duration = isset($_SESSION['login_time']) ? time() - $_SESSION['login_time'] : 0;
$session_remaining = max(0, 7200 - $session_duration); // 2 ore = 7200 secondi
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel SaaS - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <!-- Navigation -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">Laravel SaaS Dashboard</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">Welcome, <?= htmlspecialchars($user['name']) ?>!</span>
                    <a href="?logout=1" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Success Message -->
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <h2 class="font-bold text-lg">✅ Login Successful!</h2>
            <p>Welcome to your dashboard. US-002 authentication system is working perfectly.</p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <!-- User Profile Card -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">👤 Your Profile</h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Name:</dt>
                            <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Email:</dt>
                            <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['email']) ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Registered:</dt>
                            <dd class="text-sm font-medium text-gray-900"><?= date('M d, Y', strtotime($user['created_at'])) ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Email Verified:</dt>
                            <dd class="text-sm font-medium text-gray-900">
                                <?= $user['email_verified_at'] ? '✅ Yes' : '❌ No' ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Session Info Card -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">⏰ Session Info</h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Login Time:</dt>
                            <dd class="text-sm font-medium text-gray-900">
                                <?= date('H:i:s', $_SESSION['login_time']) ?>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Duration:</dt>
                            <dd class="text-sm font-medium text-gray-900">
                                <?= gmdate('H:i:s', $session_duration) ?>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Remaining:</dt>
                            <dd class="text-sm font-medium text-gray-900" id="session-remaining">
                                <?= gmdate('H:i:s', $session_remaining) ?>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Remember Me:</dt>
                            <dd class="text-sm font-medium text-gray-900">
                                <?= isset($_COOKIE['remember_token']) ? '✅ Active' : '❌ No' ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- US-002 Features Card -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">🔐 US-002 Features</h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Email/Password Login
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Remember Me (30 days)
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Rate Limiting (5/hour)
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Session Timeout (2h)
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Security Logging
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Mobile Responsive
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Social Login UI
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Login History -->
            <div class="bg-white overflow-hidden shadow rounded-lg md:col-span-2 lg:col-span-3">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">📊 Recent Login History</h3>
                    <?php if (empty($login_history)): ?>
                        <p class="text-gray-500 text-sm">No previous login history found.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($login_history as $login): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= date('M d, Y H:i:s', strtotime($login['attempted_at'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars($login['ip_address']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Success
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Navigation Links -->
        <div class="mt-8 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">🔗 Navigation</h3>
            <div class="grid grid-cols-3 md:grid-cols-7 gap-3">
                <a href="profile.php" class="text-center py-2 px-3 bg-purple-50 hover:bg-purple-100 rounded-lg text-purple-700 font-medium text-sm">
                    👤 Profile
                </a>
                <a href="plans.php" class="text-center py-2 px-3 bg-indigo-50 hover:bg-indigo-100 rounded-lg text-indigo-700 font-medium text-sm">
                    💎 Plans
                </a>
                <a href="usage.php" class="text-center py-2 px-3 bg-teal-50 hover:bg-teal-100 rounded-lg text-teal-700 font-medium text-sm">
                    📊 Usage
                </a>
                <a href="register.php" class="text-center py-2 px-3 bg-blue-50 hover:bg-blue-100 rounded-lg text-blue-700 font-medium text-sm">
                    📝 Register
                </a>
                <a href="login.php" class="text-center py-2 px-3 bg-green-50 hover:bg-green-100 rounded-lg text-green-700 font-medium text-sm">
                    🔐 Login
                </a>
                <a href="forgot-password.php" class="text-center py-2 px-3 bg-yellow-50 hover:bg-yellow-100 rounded-lg text-yellow-700 font-medium text-sm">
                    🔑 Reset
                </a>
                <a href="?logout=1" class="text-center py-2 px-3 bg-red-50 hover:bg-red-100 rounded-lg text-red-700 font-medium text-sm">
                    🚪 Logout
                </a>
            </div>
        </div>
    </div>

    <script>
        // Update session remaining time every second
        let remaining = <?= $session_remaining ?>;
        const remainingElement = document.getElementById('session-remaining');
        
        setInterval(() => {
            if (remaining > 0) {
                remaining--;
                const hours = Math.floor(remaining / 3600);
                const minutes = Math.floor((remaining % 3600) / 60);
                const seconds = remaining % 60;
                remainingElement.textContent = 
                    String(hours).padStart(2, '0') + ':' +
                    String(minutes).padStart(2, '0') + ':' +
                    String(seconds).padStart(2, '0');
            } else {
                remainingElement.textContent = 'Session expired';
                remainingElement.className += ' text-red-600';
            }
        }, 1000);
    </script>
</body>
</html>