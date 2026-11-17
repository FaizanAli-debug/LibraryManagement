<?php
require_once __DIR__ . '/../includes/auth.php';

require_admin();

if (!is_post()) {
    redirect(app_url('staff/index.php'));
}

require_csrf();

$idInput = post_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('staff/index.php?error=invalid'));
}

$staffId = (int)$idInput;
$lookup = $conn->prepare('SELECT role, status FROM users WHERE id = ? LIMIT 1');

if (!$lookup) {
    error_log('Staff status lookup preparation failed: ' . $conn->error);
    redirect(app_url('staff/index.php?error=save'));
}

$lookup->bind_param('i', $staffId);
$lookup->execute();
$staffMember = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$staffMember) {
    redirect(app_url('staff/index.php?error=notfound'));
}

if ($staffMember['role'] !== 'Librarian') {
    redirect(app_url('staff/index.php?error=protected'));
}

$newStatus = strcasecmp((string)$staffMember['status'], 'Active') === 0 ? 'Inactive' : 'Active';
$stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'Librarian'");

if (!$stmt) {
    error_log('Staff status update preparation failed: ' . $conn->error);
    redirect(app_url('staff/index.php?error=save'));
}

$stmt->bind_param('si', $newStatus, $staffId);

if (!$stmt->execute()) {
    error_log('Staff status update failed: ' . $stmt->error);
    $stmt->close();
    redirect(app_url('staff/index.php?error=save'));
}

$stmt->close();
redirect(app_url('staff/index.php?notice=status'));
