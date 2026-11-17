<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(app_url('dashboard.php'));
}

$error = '';
$email = '';
$userCount = db_value($conn, 'SELECT COUNT(*) FROM users');

if (is_post()) {
    $email = post_value('email');
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    if (!csrf_verify()) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } elseif ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email or password.';
    } else {
        $stmt = $conn->prepare('SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1');

        if (!$stmt) {
            error_log('Login lookup preparation failed: ' . $conn->error);
            $error = 'Sign-in is temporarily unavailable. Please try again.';
        } else {
            $stmt->bind_param('s', $email);

            if (!$stmt->execute()) {
                error_log('Login lookup failed: ' . $stmt->error);
                $error = 'Sign-in is temporarily unavailable. Please try again.';
            } else {
                $user = $stmt->get_result()->fetch_assoc();
                $role = $user ? normalize_role((string)$user['role']) : null;

                if ($user && $role !== null && password_verify($password, $user['password'])) {
                    if (strcasecmp((string)$user['status'], 'Active') !== 0) {
                        $error = 'This staff account has been deactivated. Please contact an administrator.';
                    } else {
                        $stmt->close();
                        login_user($user);
                        redirect(app_url('dashboard.php'));
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login | Grand Library</title>
    <link rel="stylesheet" href="<?php echo e(app_url('assets/tera.css')); ?>">
</head>
<body class="auth-page">
<main class="auth-card">
    <div class="logo">Grand<span>Library.</span></div>
    <h1>Staff Login</h1>
    <p class="auth-subtitle">Sign in with your Admin or Librarian account to manage the library.</p>

    <?php if ($error !== ''): ?>
        <div class="auth-error" role="alert"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo e(app_url('login.php')); ?>">
        <?php echo csrf_field(); ?>

        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="<?php echo e($email); ?>"
               placeholder="admin@example.com" autocomplete="username" required>

        <label for="loginPassword">Password</label>
        <div class="password-wrap">
            <input type="password" id="loginPassword" name="password"
                   placeholder="Enter password" autocomplete="current-password" required>
            <button type="button" class="password-toggle" data-toggle-password="#loginPassword"
                    aria-controls="loginPassword" aria-pressed="false">Show</button>
        </div>

        <button type="submit" class="auth-submit">Sign In &rarr;</button>
    </form>

    <div class="auth-meta">
        <a class="auth-link" href="<?php echo e(app_url('index.php')); ?>">&larr; Back to Library</a>
        <?php if ($userCount === 0): ?>
            <a class="auth-link" href="<?php echo e(app_url('admin.php')); ?>">First-time admin setup</a>
        <?php endif; ?>
    </div>
</main>
<script src="<?php echo e(app_url('assets/mera.js')); ?>"></script>
</body>
</html>
