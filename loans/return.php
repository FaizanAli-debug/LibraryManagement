<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

if (!is_post()) {
    redirect(app_url('loans/index.php'));
}

require_csrf();

$idInput = post_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('loans/index.php?error=invalid'));
}

$loanId = (int)$idInput;

if (!$conn->begin_transaction()) {
    error_log('Loan return transaction could not start: ' . $conn->error);
    redirect(app_url('loans/index.php?error=save'));
}

$lookup = $conn->prepare('SELECT id, returned_at FROM loans WHERE id = ? FOR UPDATE');

if (!$lookup) {
    error_log('Loan return lookup preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('loans/index.php?error=save'));
}

$lookup->bind_param('i', $loanId);

if (!$lookup->execute()) {
    error_log('Loan return lookup failed: ' . $lookup->error);
    $lookup->close();
    $conn->rollback();
    redirect(app_url('loans/index.php?error=save'));
}

$loan = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$loan) {
    $conn->rollback();
    redirect(app_url('loans/index.php?error=notfound'));
}

if ($loan['returned_at'] !== null) {
    $conn->rollback();
    redirect(app_url('loans/index.php?error=returned'));
}

$update = $conn->prepare('UPDATE loans SET returned_at = CURDATE() WHERE id = ? AND returned_at IS NULL');

if (!$update) {
    error_log('Loan return update preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('loans/index.php?error=save'));
}

$update->bind_param('i', $loanId);

if (!$update->execute()) {
    error_log('Loan return update failed: ' . $update->error);
    $update->close();
    $conn->rollback();
    redirect(app_url('loans/index.php?error=save'));
}

$updatedRows = $update->affected_rows;
$update->close();

if ($updatedRows !== 1) {
    $conn->rollback();
    redirect(app_url('loans/index.php?error=returned'));
}

if (!$conn->commit()) {
    error_log('Loan return commit failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('loans/index.php?error=save'));
}

redirect(app_url('loans/index.php?notice=returned'));
