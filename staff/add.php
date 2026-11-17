<?php
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$error = '';
$name = '';
$email = '';

if (is_post()) {
    if (!csrf_verify()) {
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
            $error = 'Enter a full name between 2 and 100 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'The password confirmation does not match.';
        } else {
            $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');

            if (!$check) {
                error_log('Staff duplicate-email check preparation failed: ' . $conn->error);
                $error = 'The Librarian account could not be created. Please try again.';
            } else {
                $check->bind_param('s', $email);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error = 'An account with this email address already exists.';
                }

                $check->close();
            }
        }

        if ($error === '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'Librarian';
            $status = 'Active';
            $stmt = $conn->prepare('INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)');

            if (!$hash) {
                $error = 'The Librarian account could not be created. Please try again.';
            } elseif (!$stmt) {
                error_log('Staff creation preparation failed: ' . $conn->error);
                $error = 'The Librarian account could not be created. Please try again.';
            } else {
                $stmt->bind_param('sssss', $name, $email, $hash, $role, $status);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirect(app_url('staff/index.php?notice=created'));
                }

                error_log('Staff creation failed: ' . $stmt->error);
                $error = 'The Librarian account could not be created. The email may already be in use.';
                $stmt->close();
            }
        }
    }
}

$pageTitle = 'Add Librarian';
$activeNav = 'staff';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Administration</div>
        <h1>Add Librarian</h1>
        <p>Create a day-to-day library operations account. New Librarian accounts are active immediately.</p>
    </div>
    <a class="btn btn-secondary" href="<?php echo e(app_url('staff/index.php')); ?>">Back to Staff</a>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2>Librarian Account</h2>
            <p class="panel-intro">This form always creates a Librarian. Administrator accounts cannot be created here.</p>
        </div>
    </div>

    <form method="post" action="<?php echo e(app_url('staff/add.php')); ?>">
        <?php echo csrf_field(); ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input class="form-control" type="text" id="name" name="name"
                       value="<?php echo e($name); ?>" maxlength="100"
                       placeholder="Librarian full name" autocomplete="name" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input class="form-control" type="email" id="email" name="email"
                       value="<?php echo e($email); ?>" maxlength="150"
                       placeholder="librarian@example.com" autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="newPassword">Password</label>
                <div class="password-wrap">
                    <input class="form-control" type="password" id="newPassword" name="password"
                           placeholder="Minimum 8 characters" autocomplete="new-password" required>
                    <button type="button" class="password-toggle" data-toggle-password="#newPassword"
                            aria-controls="newPassword" aria-pressed="false">Show</button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirmPassword">Confirm Password</label>
                <div class="password-wrap">
                    <input class="form-control" type="password" id="confirmPassword" name="confirm_password"
                           placeholder="Re-enter password" autocomplete="new-password" required>
                    <button type="button" class="password-toggle" data-toggle-password="#confirmPassword"
                            aria-controls="confirmPassword" aria-pressed="false">Show</button>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?php echo e(app_url('staff/index.php')); ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Librarian</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
