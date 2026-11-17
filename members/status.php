<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

if (!is_post()) {
    redirect(app_url('members/index.php'));
}

require_csrf();

$idInput = post_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('members/index.php?error=invalid'));
}

$memberId = (int)$idInput;
$returnTo = post_value('return_to');
$lookup = $conn->prepare('SELECT id, status FROM members WHERE id = ? LIMIT 1');

if (!$lookup) {
    error_log('Member status lookup preparation failed: ' . $conn->error);
    redirect(app_url('members/index.php?error=save'));
}

$lookup->bind_param('i', $memberId);

if (!$lookup->execute()) {
    error_log('Member status lookup failed: ' . $lookup->error);
    $lookup->close();
    redirect(app_url('members/index.php?error=save'));
}

$member = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$member) {
    redirect(app_url('members/index.php?error=notfound'));
}

$newStatus = strcasecmp((string)$member['status'], 'Active') === 0 ? 'Inactive' : 'Active';
$stmt = $conn->prepare('UPDATE members SET status = ? WHERE id = ?');

if (!$stmt) {
    error_log('Member status update preparation failed: ' . $conn->error);
    redirect(app_url('members/index.php?error=save'));
}

$stmt->bind_param('si', $newStatus, $memberId);

if (!$stmt->execute()) {
    error_log('Member status update failed: ' . $stmt->error);
    $stmt->close();
    redirect(app_url('members/index.php?error=save'));
}

$stmt->close();

if ($returnTo === 'view') {
    redirect(app_url('members/view.php?id=' . $memberId . '&notice=status'));
}

redirect(app_url('members/index.php?notice=status'));
