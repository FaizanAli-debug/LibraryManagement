<?php
require_once __DIR__ . '/../includes/auth.php';

require_admin();

if (!is_post()) {
    redirect(app_url('members/index.php'));
}

require_csrf();

$idInput = post_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('members/index.php?error=invalid'));
}

$memberId = (int)$idInput;

if (!$conn->begin_transaction()) {
    error_log('Member deletion transaction could not start: ' . $conn->error);
    redirect(app_url('members/index.php?error=save'));
}

$lookup = $conn->prepare('SELECT id FROM members WHERE id = ? FOR UPDATE');

if (!$lookup) {
    error_log('Member deletion lookup preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('members/index.php?error=save'));
}

$lookup->bind_param('i', $memberId);

if (!$lookup->execute()) {
    error_log('Member deletion lookup failed: ' . $lookup->error);
    $lookup->close();
    $conn->rollback();
    redirect(app_url('members/index.php?error=save'));
}

$member = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$member) {
    $conn->rollback();
    redirect(app_url('members/index.php?error=notfound'));
}

$historyCheck = $conn->prepare('SELECT COUNT(*) AS history_count FROM loans WHERE member_id = ?');

if (!$historyCheck) {
    error_log('Member history check preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('members/index.php?error=save'));
}

$historyCheck->bind_param('i', $memberId);

if (!$historyCheck->execute()) {
    error_log('Member history check failed: ' . $historyCheck->error);
    $historyCheck->close();
    $conn->rollback();
    redirect(app_url('members/index.php?error=save'));
}

$historyRow = $historyCheck->get_result()->fetch_assoc();
$historyCheck->close();
$historyCount = $historyRow ? (int)$historyRow['history_count'] : 0;

if ($historyCount > 0) {
    $conn->rollback();
    redirect(app_url('members/index.php?error=history'));
}

$deleteMember = $conn->prepare('DELETE FROM members WHERE id = ?');

if (!$deleteMember) {
    error_log('Member deletion preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('members/index.php?error=save'));
}

$deleteMember->bind_param('i', $memberId);

if (!$deleteMember->execute()) {
    $deleteError = $deleteMember->errno;
    $deleteMessage = $deleteMember->error;
    $deleteMember->close();
    $conn->rollback();

    if ($deleteError === 1451) {
        redirect(app_url('members/index.php?error=history'));
    }

    error_log('Member deletion failed: ' . $deleteMessage);
    redirect(app_url('members/index.php?error=save'));
}

$deleteMember->close();

if (!$conn->commit()) {
    error_log('Member deletion commit failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('members/index.php?error=save'));
}

redirect(app_url('members/index.php?notice=deleted'));
