<?php
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$idInput = is_post() ? post_value('id') : get_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('staff/index.php?error=invalid'));
}

$staffId = (int)$idInput;
$lookup = $conn->prepare('SELECT id, name, email, role, status, created_at FROM users WHERE id = ? LIMIT 1');

if (!$lookup) {
    error_log('Staff edit lookup preparation failed: ' . $conn->error);
    redirect(app_url('staff/index.php?error=save'));
}

$lookup->bind_param('i', $staffId);
$lookup->execute();
$staffMember = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$staffMember) {
    redirect(app_url('staff/index.php?error=notfound'));
}

$error = '';
$name = (string)$staffMember['name'];
$email = (string)$staffMember['email'];

if (is_post()) {
    if (!csrf_verify()) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $name = post_value('name');
        $email = post_value('email');
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);

        if ($nameLength < 2 || $nameLength > 100) {
            $error = 'Enter a full name between 2 and 100 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $error = 'Enter a valid email address.';
        } else {
            $check = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');

            if (!$check) {
                error_log('Staff edit duplicate-email check preparation failed: ' . $conn->error);
                $error = 'The staff member could not be updated. Please try again.';
            } else {
                $check->bind_param('si', $email, $staffId);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error = 'Another account already uses this email address.';
                }

                $check->close();
            }
        }

        if ($error === '') {
            $stmt = $conn->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');

            if (!$stmt) {
                error_log('Staff update preparation failed: ' . $conn->error);
                $error = 'The staff member could not be updated. Please try again.';
            } else {
                $stmt->bind_param('ssi', $name, $email, $staffId);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirect(app_url('staff/index.php?notice=updated'));
                }

                error_log('Staff update failed: ' . $stmt->error);
                $error = 'The staff member could not be updated. The email may already be in use.';
                $stmt->close();
            }
        }
    }
}

$createdLabel = $staffMember['created_at']
    ? date('M j, Y', strtotime($staffMember['created_at']))
    : '&mdash;';
$isActive = strcasecmp((string)$staffMember['status'], 'Active') === 0;

$pageTitle = 'Edit Staff Member';
$activeNav = 'staff';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Administration</div>
        <h1>Edit Staff Member</h1>
        <p>Update the staff member's name and email address. Roles and account status are managed separately.</p>
    </div>
    <a class="btn btn-secondary" href="<?php echo e(app_url('staff/index.php')); ?>">Back to Staff</a>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2><?php echo e($staffMember['name']); ?></h2>
            <p class="panel-intro">Created <?php echo $createdLabel; ?>.</p>
        </div>
        <div class="detail-badges">
            <span class="role-badge"><?php echo e($staffMember['role']); ?></span>
            <span class="status-badge <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>">
                <?php echo e($staffMember['status']); ?>
            </span>
        </div>
    </div>

    <form method="post" action="<?php echo e(app_url('staff/edit.php')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$staffMember['id']; ?>">

        <div class="form-grid">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input class="form-control" type="text" id="name" name="name"
                       value="<?php echo e($name); ?>" maxlength="100"
                       placeholder="Staff member name" autocomplete="name" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input class="form-control" type="email" id="email" name="email"
                       value="<?php echo e($email); ?>" maxlength="150"
                       placeholder="staff@example.com" autocomplete="email" required>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?php echo e(app_url('staff/index.php')); ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
