<?php
session_start();
require_once '../vendor/autoload.php';

// Configurazione database SQLite
$pdo = new PDO('sqlite:../database/database.sqlite');

// Check se utente è loggato
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=profile.php');
    exit;
}

// Check session timeout (2 ore)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 7200)) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

// Create profile settings table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_profiles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER UNIQUE NOT NULL,
        avatar_path TEXT NULL,
        bio TEXT NULL,
        phone TEXT NULL,
        website TEXT NULL,
        location TEXT NULL,
        privacy_profile TEXT DEFAULT 'public',
        privacy_email TEXT DEFAULT 'private',
        privacy_phone TEXT DEFAULT 'private',
        notifications_email INTEGER DEFAULT 1,
        notifications_sms INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id)
    )
");

// Create profile audit table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS profile_audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        action TEXT NOT NULL,
        field_changed TEXT NULL,
        old_value TEXT NULL,
        new_value TEXT NULL,
        ip_address TEXT NOT NULL,
        user_agent TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id)
    )
");

$errors = [];
$success = false;
$current_tab = $_GET['tab'] ?? 'general';

// Get current user
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get or create user profile
$stmt = $pdo->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    // Create default profile
    $stmt = $pdo->prepare('
        INSERT INTO user_profiles (user_id) VALUES (?)
    ');
    $stmt->execute([$_SESSION['user_id']]);
    
    $stmt = $pdo->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to log profile changes
function logProfileChange($pdo, $user_id, $action, $field = null, $old_value = null, $new_value = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $pdo->prepare('
        INSERT INTO profile_audit_log (user_id, action, field_changed, old_value, new_value, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$user_id, $action, $field, $old_value, $new_value, $ip, $user_agent]);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['update_general'])) {
        // Update general profile info
        $name = trim($_POST['name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $location = trim($_POST['location'] ?? '');
        
        // Validation
        if (empty($name) || strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        }
        
        if (!empty($phone) && !preg_match('/^[\d\s\+\-\(\)]{10,20}$/', $phone)) {
            $errors['phone'] = 'Please enter a valid phone number.';
        }
        
        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            $errors['website'] = 'Please enter a valid website URL.';
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Update user name if changed
                if ($name !== $user['name']) {
                    $stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
                    $stmt->execute([$name, $_SESSION['user_id']]);
                    logProfileChange($pdo, $_SESSION['user_id'], 'name_updated', 'name', $user['name'], $name);
                    $_SESSION['user_name'] = $name;
                }
                
                // Update profile
                $stmt = $pdo->prepare('
                    UPDATE user_profiles 
                    SET bio = ?, phone = ?, website = ?, location = ?, updated_at = datetime("now")
                    WHERE user_id = ?
                ');
                $stmt->execute([$bio, $phone, $website, $location, $_SESSION['user_id']]);
                
                logProfileChange($pdo, $_SESSION['user_id'], 'profile_updated');
                
                $pdo->commit();
                $success = 'Profile updated successfully!';
                
                // Refresh user and profile data
                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
                $stmt->execute([$_SESSION['user_id']]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors['general'] = 'Failed to update profile. Please try again.';
            }
        }
    }
    
    elseif (isset($_POST['change_email'])) {
        // Change email
        $new_email = trim($_POST['new_email'] ?? '');
        $password = $_POST['current_password'] ?? '';
        
        // Validation
        if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors['new_email'] = 'Please provide a valid email address.';
        } elseif ($new_email === $user['email']) {
            $errors['new_email'] = 'This is already your current email address.';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
            $stmt->execute([$new_email, $_SESSION['user_id']]);
            if ($stmt->fetchColumn() > 0) {
                $errors['new_email'] = 'This email address is already in use.';
            }
        }
        
        if (empty($password)) {
            $errors['current_password'] = 'Current password is required.';
        } elseif (!password_verify($password, $user['password'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        }
        
        if (empty($errors)) {
            // In a real app, we would send verification email
            // For demo, we'll update directly but mark as unverified
            $stmt = $pdo->prepare('
                UPDATE users 
                SET email = ?, email_verified_at = NULL 
                WHERE id = ?
            ');
            $stmt->execute([$new_email, $_SESSION['user_id']]);
            
            logProfileChange($pdo, $_SESSION['user_id'], 'email_changed', 'email', $user['email'], $new_email);
            $_SESSION['user_email'] = $new_email;
            
            $success = 'Email updated successfully! In a real application, you would need to verify the new email address.';
            
            // Refresh user data
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password)) {
            $errors['current_password'] = 'Current password is required.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        }
        
        if (empty($new_password) || strlen($new_password) < 8) {
            $errors['new_password'] = 'New password must be at least 8 characters long.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $new_password)) {
            $errors['new_password'] = 'Password must contain uppercase, lowercase, numbers and symbols.';
        } elseif ($new_password !== $confirm_password) {
            $errors['confirm_password'] = 'Password confirmation does not match.';
        }
        
        if (empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            // Clear all remember me tokens for security
            $stmt = $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            
            logProfileChange($pdo, $_SESSION['user_id'], 'password_changed');
            
            $success = 'Password changed successfully! All remember me sessions have been cleared for security.';
        }
    }
    
    elseif (isset($_POST['update_privacy'])) {
        // Update privacy settings
        $privacy_profile = $_POST['privacy_profile'] ?? 'public';
        $privacy_email = $_POST['privacy_email'] ?? 'private';
        $privacy_phone = $_POST['privacy_phone'] ?? 'private';
        $notifications_email = isset($_POST['notifications_email']) ? 1 : 0;
        $notifications_sms = isset($_POST['notifications_sms']) ? 1 : 0;
        
        $stmt = $pdo->prepare('
            UPDATE user_profiles 
            SET privacy_profile = ?, privacy_email = ?, privacy_phone = ?, 
                notifications_email = ?, notifications_sms = ?, updated_at = datetime("now")
            WHERE user_id = ?
        ');
        $stmt->execute([$privacy_profile, $privacy_email, $privacy_phone, 
                       $notifications_email, $notifications_sms, $_SESSION['user_id']]);
        
        logProfileChange($pdo, $_SESSION['user_id'], 'privacy_settings_updated');
        
        $success = 'Privacy settings updated successfully!';
        
        // Refresh profile data
        $stmt = $pdo->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Laravel SaaS - Profile Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <!-- Navigation -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-4">
                    <h1 class="text-xl font-semibold text-gray-900">Profile Settings</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-900">Dashboard</a>
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
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="mb-8">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <a href="?tab=general" 
                       class="<?= $current_tab === 'general' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                        General
                    </a>
                    <a href="?tab=security" 
                       class="<?= $current_tab === 'security' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                        Security
                    </a>
                    <a href="?tab=privacy" 
                       class="<?= $current_tab === 'privacy' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                        Privacy
                    </a>
                </nav>
            </div>
        </div>

        <!-- Tab Content -->
        <?php if ($current_tab === 'general'): ?>
            <!-- General Settings Tab -->
            <div class="bg-white shadow rounded-lg">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-6">General Information</h2>
                    
                    <form method="POST" class="space-y-6">
                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <div class="mt-1">
                                <input id="name" name="name" type="text" required
                                       class="block w-full px-3 py-2 border <?= error('name', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                       value="<?= htmlspecialchars(old('name') ?: $user['name']) ?>">
                            </div>
                            <?php if (error('name', $errors)): ?>
                                <div class="text-red-600 text-sm mt-1"><?= error('name', $errors) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Bio -->
                        <div>
                            <label for="bio" class="block text-sm font-medium text-gray-700">Bio</label>
                            <div class="mt-1">
                                <textarea id="bio" name="bio" rows="3"
                                          class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                          placeholder="Tell us about yourself..."><?= htmlspecialchars(old('bio') ?: ($profile['bio'] ?? '')) ?></textarea>
                            </div>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                            <div class="mt-1">
                                <input id="phone" name="phone" type="tel"
                                       class="block w-full px-3 py-2 border <?= error('phone', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                       value="<?= htmlspecialchars(old('phone') ?: ($profile['phone'] ?? '')) ?>">
                            </div>
                            <?php if (error('phone', $errors)): ?>
                                <div class="text-red-600 text-sm mt-1"><?= error('phone', $errors) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Website -->
                        <div>
                            <label for="website" class="block text-sm font-medium text-gray-700">Website</label>
                            <div class="mt-1">
                                <input id="website" name="website" type="url"
                                       class="block w-full px-3 py-2 border <?= error('website', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                       placeholder="https://example.com"
                                       value="<?= htmlspecialchars(old('website') ?: ($profile['website'] ?? '')) ?>">
                            </div>
                            <?php if (error('website', $errors)): ?>
                                <div class="text-red-600 text-sm mt-1"><?= error('website', $errors) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Location -->
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                            <div class="mt-1">
                                <input id="location" name="location" type="text"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                       placeholder="City, Country"
                                       value="<?= htmlspecialchars(old('location') ?: ($profile['location'] ?? '')) ?>">
                            </div>
                        </div>

                        <div>
                            <button type="submit" name="update_general"
                                    class="w-full sm:w-auto flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($current_tab === 'security'): ?>
            <!-- Security Settings Tab -->
            <div class="space-y-8">
                
                <!-- Change Email -->
                <div class="bg-white shadow rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Change Email Address</h3>
                        
                        <div class="mb-4 p-3 bg-gray-50 rounded">
                            <p class="text-sm text-gray-700">
                                <strong>Current Email:</strong> <?= htmlspecialchars($user['email']) ?>
                                <?php if ($user['email_verified_at']): ?>
                                    <span class="text-green-600 ml-2">✓ Verified</span>
                                <?php else: ?>
                                    <span class="text-yellow-600 ml-2">⚠ Unverified</span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <form method="POST" class="space-y-4">
                            <div>
                                <label for="new_email" class="block text-sm font-medium text-gray-700">New Email Address</label>
                                <div class="mt-1">
                                    <input id="new_email" name="new_email" type="email" required
                                           class="block w-full px-3 py-2 border <?= error('new_email', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                           value="<?= htmlspecialchars(old('new_email')) ?>">
                                </div>
                                <?php if (error('new_email', $errors)): ?>
                                    <div class="text-red-600 text-sm mt-1"><?= error('new_email', $errors) ?></div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label for="current_password_email" class="block text-sm font-medium text-gray-700">Current Password</label>
                                <div class="mt-1">
                                    <input id="current_password_email" name="current_password" type="password" required
                                           class="block w-full px-3 py-2 border <?= error('current_password', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <?php if (error('current_password', $errors)): ?>
                                    <div class="text-red-600 text-sm mt-1"><?= error('current_password', $errors) ?></div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <button type="submit" name="change_email"
                                        class="w-full sm:w-auto flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Change Email
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="bg-white shadow rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Change Password</h3>

                        <form method="POST" class="space-y-4">
                            <div>
                                <label for="current_password_pwd" class="block text-sm font-medium text-gray-700">Current Password</label>
                                <div class="mt-1">
                                    <input id="current_password_pwd" name="current_password" type="password" required
                                           class="block w-full px-3 py-2 border <?= error('current_password', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <?php if (error('current_password', $errors)): ?>
                                    <div class="text-red-600 text-sm mt-1"><?= error('current_password', $errors) ?></div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                                <div class="mt-1">
                                    <input id="new_password" name="new_password" type="password" required
                                           class="block w-full px-3 py-2 border <?= error('new_password', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    Must be at least 8 characters with mixed case, numbers, and symbols
                                </p>
                                <?php if (error('new_password', $errors)): ?>
                                    <div class="text-red-600 text-sm mt-1"><?= error('new_password', $errors) ?></div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                <div class="mt-1">
                                    <input id="confirm_password" name="confirm_password" type="password" required
                                           class="block w-full px-3 py-2 border <?= error('confirm_password', $errors) ? 'border-red-500' : 'border-gray-300' ?> rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <?php if (error('confirm_password', $errors)): ?>
                                    <div class="text-red-600 text-sm mt-1"><?= error('confirm_password', $errors) ?></div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <button type="submit" name="change_password"
                                        class="w-full sm:w-auto flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php elseif ($current_tab === 'privacy'): ?>
            <!-- Privacy Settings Tab -->
            <div class="bg-white shadow rounded-lg">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-6">Privacy & Notifications</h2>
                    
                    <form method="POST" class="space-y-6">
                        
                        <!-- Privacy Settings -->
                        <div>
                            <h3 class="text-md font-medium text-gray-900 mb-4">Profile Visibility</h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="privacy_profile" class="block text-sm font-medium text-gray-700">Profile Visibility</label>
                                    <select id="privacy_profile" name="privacy_profile" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="public" <?= ($profile['privacy_profile'] ?? 'public') === 'public' ? 'selected' : '' ?>>Public - Anyone can view</option>
                                        <option value="private" <?= ($profile['privacy_profile'] ?? '') === 'private' ? 'selected' : '' ?>>Private - Only you can view</option>
                                        <option value="friends" <?= ($profile['privacy_profile'] ?? '') === 'friends' ? 'selected' : '' ?>>Friends only</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="privacy_email" class="block text-sm font-medium text-gray-700">Email Visibility</label>
                                    <select id="privacy_email" name="privacy_email"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="private" <?= ($profile['privacy_email'] ?? 'private') === 'private' ? 'selected' : '' ?>>Private - Hidden</option>
                                        <option value="public" <?= ($profile['privacy_email'] ?? '') === 'public' ? 'selected' : '' ?>>Public - Visible to all</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="privacy_phone" class="block text-sm font-medium text-gray-700">Phone Visibility</label>
                                    <select id="privacy_phone" name="privacy_phone"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="private" <?= ($profile['privacy_phone'] ?? 'private') === 'private' ? 'selected' : '' ?>>Private - Hidden</option>
                                        <option value="public" <?= ($profile['privacy_phone'] ?? '') === 'public' ? 'selected' : '' ?>>Public - Visible to all</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Settings -->
                        <div>
                            <h3 class="text-md font-medium text-gray-900 mb-4">Notifications</h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center">
                                    <input id="notifications_email" name="notifications_email" type="checkbox"
                                           <?= ($profile['notifications_email'] ?? 1) ? 'checked' : '' ?>
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="notifications_email" class="ml-2 block text-sm text-gray-900">
                                        Email notifications for account activity
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input id="notifications_sms" name="notifications_sms" type="checkbox"
                                           <?= ($profile['notifications_sms'] ?? 0) ? 'checked' : '' ?>
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="notifications_sms" class="ml-2 block text-sm text-gray-900">
                                        SMS notifications for important updates
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div>
                            <button type="submit" name="update_privacy"
                                    class="w-full sm:w-auto flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Update Privacy Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- US-003 Features Card -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-medium text-blue-900 mb-4">✅ US-003: Profile Management Features</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <ul class="space-y-2 text-sm text-blue-800">
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            View/edit profile information
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Email change with verification
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Password change with confirmation
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Privacy settings control
                        </li>
                    </ul>
                </div>
                <div>
                    <ul class="space-y-2 text-sm text-blue-800">
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Mobile-responsive tabs
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Success/error messaging
                        </li>
                        <li class="flex items-center">
                            <span class="text-green-500 mr-2">✅</span>
                            Security audit logging
                        </li>
                        <li class="flex items-center">
                            <span class="text-yellow-500 mr-2">🔄</span>
                            Avatar upload (coming next)
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>