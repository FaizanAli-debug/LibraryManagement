<?php
require_once __DIR__ . '/includes/auth.php';

$userCount = db_value($conn, 'SELECT COUNT(*) FROM users');
$error = '';
$success = '';
$name = '';
$email = '';

if (is_post()) {
    if ($userCount === null) {
        $error = 'Unable to verify staff accounts right now. Please try again later.';
    } elseif ($userCount > 0) {
        $error = 'Setup is already complete. Public admin creation is closed.';
    } elseif (!csrf_verify()) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $name = post_value('name');
        $email = post_value('email');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $password = is_string($password) ? $password : '';
        $confirmPassword = is_string($confirmPassword) ? $confirmPassword : '';
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);

        if ($nameLength < 2 || $nameLength > 100) {
            $error = 'Enter an admin name between 2 and 100 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'The password confirmation does not match.';
        } else {
            $currentCount = db_value($conn, 'SELECT COUNT(*) FROM users');

            if ($currentCount !== 0) {
                $error = 'Setup is already complete. Public admin creation is closed.';
            } else {
                $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');

                if ($check) {
                    $check->bind_param('s', $email);
                    $check->execute();
                    $check->store_result();

                    if ($check->num_rows > 0) {
                        $error = 'An account with this email already exists.';
                    }

                    $check->close();
                }

                if ($error === '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'Admin';
                    $stmt = $conn->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');

                    if (!$hash) {
                        $error = 'The admin account could not be created. Please try again.';
                    } elseif (!$stmt) {
                        error_log('Admin setup preparation failed: ' . $conn->error);
                        $error = 'The admin account could not be created. Please try again.';
                    } else {
                        $stmt->bind_param('ssss', $name, $email, $hash, $role);

                        if ($stmt->execute()) {
                            $success = 'Admin account created successfully. You can now sign in.';
                            $userCount = 1;
                            $name = '';
                            $email = '';
                        } else {
                            error_log('Admin setup insert failed: ' . $stmt->error);
                            $error = 'The admin account could not be created. The email may already be in use.';
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>First Admin Setup | Grand Library</title>
    <link rel="stylesheet" href="<?php echo e(app_url('assets/tera.css')); ?>">
</head>
<body class="auth-page">
<main class="auth-card">
    <div class="logo">Grand<span>Library.</span></div>
    <h1>First Admin Setup</h1>
    <p class="auth-subtitle">Create the first Administrator account. This page closes automatically once an account exists.</p>

    <?php if ($error !== ''): ?>
        <div class="auth-error" role="alert"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="auth-success" role="status"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($userCount === null): ?>
        <p class="form-note">The staff account check could not be completed. Please try again later.</p>
        <div class="auth-meta">
            <a class="auth-link" href="<?php echo e(app_url('login.php')); ?>">Go to Staff Login</a>
        </div>
    <?php elseif ($userCount > 0): ?>
        <?php if ($success === ''): ?>
            <div class="setup-state">
                <strong>Setup complete.</strong>
                <p>An Administrator account already exists. Public admin creation is disabled.</p>
            </div>
        <?php endif; ?>
        <div class="auth-meta">
            <a class="auth-link" href="<?php echo e(app_url('login.php')); ?>">Continue to Staff Login</a>
        </div>
    <?php else: ?>
        <form method="post" action="<?php echo e(app_url('admin.php')); ?>">
            <?php echo csrf_field(); ?>

            <label for="name">Admin Name</label>
            <input type="text" id="name" name="name" value="<?php echo e($name); ?>"
                   maxlength="100" placeholder="Library Administrator" autocomplete="name" required>

            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo e($email); ?>"
                   maxlength="150" placeholder="admin@example.com" autocomplete="email" required>

            <label for="setupPassword">Password</label>
            <div class="password-wrap">
                <input type="password" id="setupPassword" name="password"
                       placeholder="Minimum 8 characters" autocomplete="new-password" required>
                <button type="button" class="password-toggle" data-toggle-password="#setupPassword"
                        aria-controls="setupPassword" aria-pressed="false">Show</button>
            </div>

            <label for="confirmPassword">Confirm Password</label>
            <div class="password-wrap">
                <input type="password" id="confirmPassword" name="confirm_password"
                       placeholder="Re-enter password" autocomplete="new-password" required>
                <button type="button" class="password-toggle" data-toggle-password="#confirmPassword"
                        aria-controls="confirmPassword" aria-pressed="false">Show</button>
            </div>

            <button type="submit" class="auth-submit">Create Admin Account</button>
        </form>

        <div class="auth-meta">
            <a class="auth-link" href="<?php echo e(app_url('login.php')); ?>">Already set up? Staff Login</a>
            <a class="auth-link" href="<?php echo e(app_url('index.php')); ?>">&larr; Back to Library</a>
        </div>
    <?php endif; ?>
</main>
<script src="<?php echo e(app_url('assets/mera.js')); ?>"></script>
</body>
</html>
