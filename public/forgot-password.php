<?php
session_start();
require_once '../vendor/autoload.php';

// Configurazione database SQLite
$pdo = new PDO('sqlite:../database/database.sqlite');

// Create password reset tokens table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL,
        token TEXT UNIQUE NOT NULL,
        expires_at DATETIME NOT NULL,
        used INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$errors = [];
$success = false;
$step = $_GET['step'] ?? 'request';

// Rate limiting per reset requests
function checkResetRateLimit($pdo, $email, $ip) {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM password_reset_tokens 
        WHERE email = ? 
        AND created_at > datetime("now", "-60 minutes")
    ');
    $stmt->execute([$email]);
    return $stmt->fetchColumn() < 3; // Max 3 reset requests per ora
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if ($step === 'request') {
        // Step 1: Request password reset
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please provide a valid email address.';
        } else {
            // Check if user exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                // Per sicurezza, mostra sempre messaggio di successo anche se email non esiste
                $success = true;
            } else {
                // Check rate limiting
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                if (!checkResetRateLimit($pdo, $email, $ip_address)) {
                    $errors['general'] = 'Too many reset requests. Please try again in 1 hour.';
                } else {
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    // Invalidate old tokens
                    $stmt = $pdo->prepare('UPDATE password_reset_tokens SET used = 1 WHERE email = ?');
                    $stmt->execute([$email]);
                    
                    // Create new token
                    $stmt = $pdo->prepare('
                        INSERT INTO password_reset_tokens (email, token, expires_at)
                        VALUES (?, ?, ?)
                    ');
                    $stmt->execute([$email, $token, $expires]);
                    
                    // In una vera applicazione, qui si invierebbe l'email
                    // Per demo, salviamo il token in sessione
                    $_SESSION['demo_reset_token'] = $token;
                    $_SESSION['demo_reset_email'] = $email;
                    
                    $success = true;
                }
            }
        }
        
    } elseif ($step === 'reset') {
        // Step 2: Reset password with token
        $token = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirmation = $_POST['password_confirmation'] ?? '';
        
        if (empty($token)) {
            $errors['token'] = 'Invalid reset token.';
        }
        
        if (empty($password) || strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
            $errors['password'] = 'Password must contain uppercase, lowercase, numbers and symbols.';
        } elseif ($password !== $password_confirmation) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }
        
        if (empty($errors)) {
            // Verify token
            $stmt = $pdo->prepare('
                SELECT email FROM password_reset_tokens 
                WHERE token = ? AND used = 0 AND expires_at > datetime("now")
            ');
            $stmt->execute([$token]);
            $reset_request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reset_request) {
                $errors['general'] = 'Invalid or expired reset token.';
            } else {
                // Update user password
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$hashed_password, $reset_request['email']]);
                
                // Mark token as used
                $stmt = $pdo->prepare('UPDATE password_reset_tokens SET used = 1 WHERE token = ?');
                $stmt->execute([$token]);
                
                // Clear any remember me tokens for this user
                $stmt = $pdo->prepare('
                    DELETE FROM user_sessions 
                    WHERE user_id = (SELECT id FROM users WHERE email = ?)
                ');
                $stmt->execute([$reset_request['email']]);
                
                $success = true;
            }
        }
    }
}

function old($field) {
    return $_POST[$field] ?? '';
}

function error($field, $errors) {
    return isset($errors[$field]) ? $errors[$field] : '';
}

// Get demo token se disponibile
$demo_token = isset($_GET['token']) ? $_GET['token'] : ($_SESSION['demo_reset_token'] ?? '');
$demo_email = $_SESSION['demo_reset_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel SaaS - Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                <?= $step === 'reset' ? 'Reset your password' : 'Forgot your password?' ?>
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Or
                <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                    return to login
                </a>
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                
                <?php if ($success && $step === 'request'): ?>
                    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        <h3 class="font-semibold">✅ Reset Email Sent!</h3>
                        <p class="mt-1">If your email exists in our system, you'll receive password reset instructions.</p>
                        
                        <?php if ($demo_token): ?>
                            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded">
                                <p class="text-sm font-medium text-blue-800">🚀 Demo Mode:</p>
                                <p class="text-sm text-blue-700 mt-1">Click the link below to simulate the email reset link:</p>
                                <a href="?step=reset&token=<?= htmlspecialchars($demo_token) ?>" 
                                   class="inline-block mt-2 px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Reset Password (Demo Link)
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <a href="login.php" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Back to Login
                    </a>
                    
                <?php elseif ($success && $step === 'reset'): ?>
                    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        <h3 class="font-semibold">✅ Password Reset Successful!</h3>
                        <p class="mt-1">Your password has been updated. You can now login with your new password.</p>
                    </div>
                    
                    <a href="login.php" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Login Now
                    </a>
                    
                <?php elseif ($step === 'reset'): ?>
                    <!-- Password Reset Form -->
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($demo_token) ?>">
                        
                        <?php if ($demo_email): ?>
                            <div class="p-3 bg-blue-50 border border-blue-200 rounded text-sm">
                                <p><strong>Resetting password for:</strong> <?= htmlspecialchars($demo_email) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- New Password -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                New Password
                            </label>
                            <div class="mt-1">
                                <input id="password" name="password" type="password" required
                                       class="appearance-none block w-full px-3 py-2 border <?= error('password', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                Must be at least 8 characters with mixed case, numbers, and symbols
                            </p>
                            <?php if (error('password', $errors)): ?>
                                <div class="text-red-600 text-sm mt-1"><?= error('password', $errors) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Confirm New Password -->
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                                Confirm New Password
                            </label>
                            <div class="mt-1">
                                <input id="password_confirmation" name="password_confirmation" type="password" required
                                       class="appearance-none block w-full px-3 py-2 border <?= error('password_confirmation', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <?php if (error('password_confirmation', $errors)): ?>
                                <div class="text-red-600 text-sm mt-1"><?= error('password_confirmation', $errors) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Submit Button -->
                        <div>
                            <button type="submit" 
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Reset Password
                            </button>
                        </div>

                        <?php if (isset($errors['general'])): ?>
                            <div class="text-red-600 text-sm text-center"><?= $errors['general'] ?></div>
                        <?php endif; ?>
                    </form>
                    
                <?php else: ?>
                    <!-- Request Reset Form -->
                    <form method="POST" class="space-y-6">
                        
                        <div>
                            <p class="text-sm text-gray-600 mb-4">
                                Enter your email address and we'll send you a link to reset your password.
                            </p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">
                                Email Address
                            </label>
                            <div class="mt-1">
                                <input id="email" name="email" type="email" required
                                       class="appearance-none block w-full px-3 py-2 border <?= error('email', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                       value="<?= htmlspecialchars(old('email')) ?>">
                            </div>
                            <?php if (error('email', $errors)): ?>
                                <div class="text-red-600 text-sm mt-1"><?= error('email', $errors) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Submit Button -->
                        <div>
                            <button type="submit" 
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Send Reset Link
                            </button>
                        </div>

                        <?php if (isset($errors['general'])): ?>
                            <div class="text-red-600 text-sm text-center"><?= $errors['general'] ?></div>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <!-- Security Features -->
                <div class="mt-6 border-t pt-6">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">🔒 Security Features:</h3>
                    <div class="space-y-1 text-xs text-gray-600">
                        <div>• Tokens expire in 24 hours</div>
                        <div>• Rate limiting: 3 requests per hour</div>
                        <div>• Previous tokens are invalidated</div>
                        <div>• Remember me tokens are cleared</div>
                        <div>• Secure token generation (64 chars)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>