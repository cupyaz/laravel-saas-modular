<?php
session_start();
require_once '../vendor/autoload.php';

// Configurazione database SQLite
$pdo = new PDO('sqlite:../database/database.sqlite');

// Create login attempts table per rate limiting
$pdo->exec("
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL,
        ip_address TEXT NOT NULL,
        success INTEGER DEFAULT 0,
        attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Create sessions table per remember me
$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_token TEXT UNIQUE NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id)
    )
");

$errors = [];
$success = false;

// Funzione per verificare rate limiting
function checkRateLimit($pdo, $email, $ip) {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM login_attempts 
        WHERE (email = ? OR ip_address = ?) 
        AND success = 0 
        AND attempted_at > datetime("now", "-60 minutes")
    ');
    $stmt->execute([$email, $ip]);
    return $stmt->fetchColumn() < 5; // Max 5 tentativi falliti per ora
}

// Funzione per logare tentativo di login
function logLoginAttempt($pdo, $email, $ip, $success = false) {
    $stmt = $pdo->prepare('
        INSERT INTO login_attempts (email, ip_address, success, attempted_at)
        VALUES (?, ?, ?, datetime("now"))
    ');
    $stmt->execute([$email, $ip, $success ? 1 : 0]);
}

// Funzione per creare remember token
function createRememberToken($pdo, $userId) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt = $pdo->prepare('
        INSERT INTO user_sessions (user_id, session_token, expires_at)
        VALUES (?, ?, ?)
    ');
    $stmt->execute([$userId, $token, $expires]);
    
    return $token;
}

// Check for existing remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $stmt = $pdo->prepare('
        SELECT u.*, us.user_id 
        FROM user_sessions us
        JOIN users u ON u.id = us.user_id  
        WHERE us.session_token = ? AND us.expires_at > datetime("now")
    ');
    $stmt->execute([$_COOKIE['remember_token']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();
        header('Location: dashboard.php');
        exit;
    }
}

// Check session timeout (2 ore)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 7200)) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

// Se già loggato, redirect a dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validazione input
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please provide a valid email address.';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    }
    
    // Rate limiting check
    if (!checkRateLimit($pdo, $email, $ip_address)) {
        $errors['general'] = 'Too many failed attempts. Please try again in 1 hour.';
    }
    
    // Se non ci sono errori di validazione, prova il login
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['login_time'] = time();
            
            // Log successful attempt
            logLoginAttempt($pdo, $email, $ip_address, true);
            
            // Handle remember me
            if ($remember) {
                $token = createRememberToken($pdo, $user['id']);
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/'); // 30 giorni
            }
            
            // Redirect to intended page or dashboard
            $redirect = $_GET['redirect'] ?? 'dashboard.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            // Login failed
            logLoginAttempt($pdo, $email, $ip_address, false);
            $errors['general'] = 'Invalid email or password.';
        }
    }
}

function old($field) {
    return $_POST[$field] ?? '';
}

function error($field, $errors) {
    return isset($errors[$field]) ? $errors[$field] : '';
}

// Check for timeout message
$timeout_message = isset($_GET['timeout']) ? 'Your session has expired. Please login again.' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel SaaS - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Sign in to your account
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Or
                <a href="register.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                    create a new account
                </a>
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                
                <?php if ($timeout_message): ?>
                    <div class="mb-6 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded">
                        <p><?= htmlspecialchars($timeout_message) ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    
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

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password
                        </label>
                        <div class="mt-1">
                            <input id="password" name="password" type="password" required
                                   class="appearance-none block w-full px-3 py-2 border <?= error('password', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <?php if (error('password', $errors)): ?>
                            <div class="text-red-600 text-sm mt-1"><?= error('password', $errors) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Remember me & Forgot password -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember" name="remember" type="checkbox"
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-900">
                                Remember me
                            </label>
                        </div>

                        <div class="text-sm">
                            <a href="forgot-password.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                                Forgot your password?
                            </a>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button type="submit" 
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Sign in
                        </button>
                    </div>

                    <?php if (isset($errors['general'])): ?>
                        <div class="text-red-600 text-sm text-center"><?= $errors['general'] ?></div>
                    <?php endif; ?>
                </form>

                <!-- Social Login Section -->
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300" />
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">Or continue with</span>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <a href="auth/google.php" 
                           class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <svg class="h-5 w-5" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                            <span class="ml-2">Google</span>
                        </a>

                        <a href="auth/facebook.php" 
                           class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                            <span class="ml-2">Facebook</span>
                        </a>
                    </div>
                </div>

                <!-- Features -->
                <div class="mt-6 border-t pt-6">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">🔐 US-002 Security Features:</h3>
                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                        <div>• Rate limiting (5 attempts/hour)</div>
                        <div>• Remember me (30 days)</div>
                        <div>• Session timeout (2 hours)</div>
                        <div>• Security logging</div>
                        <div>• Social login support</div>
                        <div>• Mobile responsive</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>