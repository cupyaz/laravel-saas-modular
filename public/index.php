<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel SaaS Modular - Authentication System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="max-w-6xl mx-auto py-12 px-4">
        
        <div class="text-center mb-12">
            <h1 class="text-5xl font-bold text-gray-900 mb-4">🚀 Laravel SaaS Modular</h1>
            <p class="text-xl text-gray-600 mb-8">Complete User Authentication System</p>
            
            <div class="flex flex-wrap justify-center gap-4 mb-8">
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded-lg">
                    <strong>✅ US-001: User Registration</strong> - Completata
                </div>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded-lg">
                    <strong>✅ US-002: User Authentication</strong> - Completata
                </div>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded-lg">
                    <strong>✅ US-003: Profile Management</strong> - Completata
                </div>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded-lg">
                    <strong>✅ US-005: Subscription Plans</strong> - Completata
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-6 gap-6 mb-12">
            
            <a href="register.php" class="group">
                <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow border-l-4 border-blue-500">
                    <div class="text-center">
                        <div class="text-3xl mb-3">📝</div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Register</h3>
                        <p class="text-sm text-gray-600">Create a new account</p>
                    </div>
                </div>
            </a>
            
            <a href="login.php" class="group">
                <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow border-l-4 border-green-500">
                    <div class="text-center">
                        <div class="text-3xl mb-3">🔐</div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Login</h3>
                        <p class="text-sm text-gray-600">Secure authentication</p>
                    </div>
                </div>
            </a>
            
            <a href="<?= isset($_SESSION['user_id']) ? 'profile.php' : 'login.php' ?>" class="group">
                <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow border-l-4 border-purple-500">
                    <div class="text-center">
                        <div class="text-3xl mb-3">👤</div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Profile</h3>
                        <p class="text-sm text-gray-600"><?= isset($_SESSION['user_id']) ? 'Manage profile' : 'Login required' ?></p>
                    </div>
                </div>
            </a>
            
            <a href="plans.php" class="group">
                <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow border-l-4 border-indigo-600">
                    <div class="text-center">
                        <div class="text-3xl mb-3">💎</div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Plans</h3>
                        <p class="text-sm text-gray-600">Subscription options</p>
                    </div>
                </div>
            </a>
            
            <a href="forgot-password.php" class="group">
                <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow border-l-4 border-yellow-500">
                    <div class="text-center">
                        <div class="text-3xl mb-3">🔑</div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Reset Password</h3>
                        <p class="text-sm text-gray-600">Password recovery</p>
                    </div>
                </div>
            </a>
            
            <a href="<?= isset($_SESSION['user_id']) ? 'dashboard.php' : 'login.php' ?>" class="group">
                <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow border-l-4 border-slate-500">
                    <div class="text-center">
                        <div class="text-3xl mb-3">📊</div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Dashboard</h3>
                        <p class="text-sm text-gray-600"><?= isset($_SESSION['user_id']) ? 'Your dashboard' : 'Login required' ?></p>
                    </div>
                </div>
            </a>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-8">
            <h2 class="text-2xl font-bold text-blue-900 mb-6">🧪 Sistema Completamente Funzionante</h2>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <h3 class="font-semibold text-blue-900 mb-3">✅ US-001 Features:</h3>
                    <ul class="space-y-1 text-sm text-blue-800">
                        <li>• Email validation & uniqueness</li>
                        <li>• Password security requirements</li>
                        <li>• GDPR compliance checkbox</li>
                        <li>• Mobile responsive design</li>
                        <li>• Real-time form validation</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-blue-900 mb-3">✅ US-002 Features:</h3>
                    <ul class="space-y-1 text-sm text-blue-800">
                        <li>• Secure login with remember me</li>
                        <li>• Rate limiting (5 attempts/hour)</li>
                        <li>• Session timeout (2 hours)</li>
                        <li>• Password reset with tokens</li>
                        <li>• Security audit logging</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-blue-900 mb-3">✅ US-003 Features:</h3>
                    <ul class="space-y-1 text-sm text-blue-800">
                        <li>• Profile info management</li>
                        <li>• Email change with verification</li>
                        <li>• Password change security</li>
                        <li>• Privacy settings control</li>
                        <li>• Audit logging system</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-blue-900 mb-3">✅ US-005 Features:</h3>
                    <ul class="space-y-1 text-sm text-blue-800">
                        <li>• Plan comparison interface</li>
                        <li>• Monthly/yearly billing options</li>
                        <li>• Usage limits tracking</li>
                        <li>• Upgrade/downgrade flows</li>
                        <li>• Freemium tier enforcement</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>