<?php
require_once '../vendor/autoload.php';

// Configurazione database SQLite semplice
$pdo = new PDO('sqlite:../database/database.sqlite');

// Create users table se non esiste
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        email_verified_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validazione input
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirmation = $_POST['password_confirmation'] ?? '';
    $gdpr_consent = isset($_POST['gdpr_consent']);

    // Validazioni
    if (empty($name) || strlen($name) < 2) {
        $errors['name'] = 'Full name is required and must be at least 2 characters.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please provide a valid email address.';
    } else {
        // Verifica email esistente
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'This email address is already registered.';
        }
    }

    if (empty($password) || strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
        $errors['password'] = 'Password must contain uppercase, lowercase, numbers and symbols.';
    } elseif ($password !== $password_confirmation) {
        $errors['password_confirmation'] = 'Password confirmation does not match.';
    }

    if (!$gdpr_consent) {
        $errors['gdpr_consent'] = 'You must accept the privacy policy and terms of service.';
    }

    // Se non ci sono errori, crea l'utente
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('
                INSERT INTO users (name, email, password, created_at, updated_at) 
                VALUES (?, ?, ?, datetime("now"), datetime("now"))
            ');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $success = true;
        } catch (Exception $e) {
            $errors['general'] = 'Registration failed. Please try again.';
        }
    }
}

function old($field) {
    return $_POST[$field] ?? '';
}

function error($field, $errors) {
    return isset($errors[$field]) ? $errors[$field] : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel SaaS - Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Create your account
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Laravel SaaS Modular - User Registration System
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                
                <?php if ($success): ?>
                    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        <h3 class="font-semibold">✅ Registration Successful!</h3>
                        <p class="mt-1">Your account has been created. In a real application, you would receive a verification email.</p>
                        <div class="mt-3 text-sm">
                            <strong>Registered:</strong> <?= htmlspecialchars($name) ?> (<?= htmlspecialchars($email) ?>)
                        </div>
                    </div>
                <?php else: ?>

                <form method="POST" class="space-y-6">
                    
                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">
                            Full Name
                        </label>
                        <div class="mt-1">
                            <input id="name" name="name" type="text" required 
                                   class="appearance-none block w-full px-3 py-2 border <?= error('name', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                   value="<?= htmlspecialchars(old('name')) ?>">
                        </div>
                        <?php if (error('name', $errors)): ?>
                            <div class="text-red-600 text-sm mt-1"><?= error('name', $errors) ?></div>
                        <?php endif; ?>
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

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password
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

                    <!-- Password Confirmation -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                            Confirm Password
                        </label>
                        <div class="mt-1">
                            <input id="password_confirmation" name="password_confirmation" type="password" required
                                   class="appearance-none block w-full px-3 py-2 border <?= error('password_confirmation', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <?php if (error('password_confirmation', $errors)): ?>
                            <div class="text-red-600 text-sm mt-1"><?= error('password_confirmation', $errors) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- GDPR Consent -->
                    <div class="flex items-center">
                        <input id="gdpr_consent" name="gdpr_consent" type="checkbox" required
                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="gdpr_consent" class="ml-2 block text-sm text-gray-900">
                            I agree to the 
                            <a href="/privacy" target="_blank" class="text-indigo-600 hover:text-indigo-500">Privacy Policy</a>
                            and 
                            <a href="/terms" target="_blank" class="text-indigo-600 hover:text-indigo-500">Terms of Service</a>
                        </label>
                    </div>
                    <?php if (error('gdpr_consent', $errors)): ?>
                        <div class="text-red-600 text-sm"><?= error('gdpr_consent', $errors) ?></div>
                    <?php endif; ?>

                    <!-- Submit Button -->
                    <div>
                        <button type="submit" 
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Create Account
                        </button>
                    </div>

                    <?php if (isset($errors['general'])): ?>
                        <div class="text-red-600 text-sm text-center"><?= $errors['general'] ?></div>
                    <?php endif; ?>
                </form>

                <?php endif; ?>

                <!-- Features -->
                <div class="mt-6 border-t pt-6">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">✅ US-001 Features Implemented:</h3>
                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                        <div>• Email validation</div>
                        <div>• Password security</div>
                        <div>• GDPR compliance</div>
                        <div>• Mobile responsive</div>
                        <div>• Duplicate prevention</div>
                        <div>• Form validation</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>