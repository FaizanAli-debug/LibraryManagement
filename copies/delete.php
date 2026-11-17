<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

if (!is_post()) {
    redirect(app_url('copies/index.php'));
}

require_csrf();

$idInput = post_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('copies/index.php?error=invalid'));
}

$copyId = (int)$idInput;

if (!$conn->begin_transaction()) {
    error_log('Copy deletion transaction could not start: ' . $conn->error);
    redirect(app_url('copies/index.php?error=save'));
}

$lookup = $conn->prepare('SELECT id, book_id, barcode FROM book_copies WHERE id = ? FOR UPDATE');

if (!$lookup) {
    error_log('Copy deletion lookup preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('copies/index.php?error=save'));
}

$lookup->bind_param('i', $copyId);

if (!$lookup->execute()) {
    error_log('Copy deletion lookup failed: ' . $lookup->error);
    $lookup->close();
    $conn->rollback();
    redirect(app_url('copies/index.php?error=save'));
}

$copy = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$copy) {
    $conn->rollback();
    redirect(app_url('copies/index.php?error=notfound'));
}

$bookId = (int)$copy['book_id'];
$bookFilter = '?book_id=' . $bookId;
$historyCheck = $conn->prepare('SELECT COUNT(*) AS history_count FROM loans WHERE copy_id = ?');

if (!$historyCheck) {
    error_log('Copy history check preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('copies/index.php' . $bookFilter . '&error=save'));
}

$historyCheck->bind_param('i', $copyId);

if (!$historyCheck->execute()) {
    error_log('Copy history check failed: ' . $historyCheck->error);
    $historyCheck->close();
    $conn->rollback();
    redirect(app_url('copies/index.php' . $bookFilter . '&error=save'));
}

$historyRow = $historyCheck->get_result()->fetch_assoc();
$historyCheck->close();
$historyCount = $historyRow ? (int)$historyRow['history_count'] : 0;

if ($historyCount > 0) {
    $conn->rollback();
    redirect(app_url('copies/index.php' . $bookFilter . '&error=history'));
}

$deleteCopy = $conn->prepare('DELETE FROM book_copies WHERE id = ?');

if (!$deleteCopy) {
    error_log('Copy deletion preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('copies/index.php' . $bookFilter . '&error=save'));
}

$deleteCopy->bind_param('i', $copyId);

if (!$deleteCopy->execute()) {
    $deleteError = $deleteCopy->errno;
    $deleteMessage = $deleteCopy->error;
    $deleteCopy->close();
    $conn->rollback();

    if ($deleteError === 1451) {
        redirect(app_url('copies/index.php' . $bookFilter . '&error=history'));
    }

    error_log('Copy deletion failed: ' . $deleteMessage);
    redirect(app_url('copies/index.php' . $bookFilter . '&error=save'));
}

$deleteCopy->close();

if (!$conn->commit()) {
    error_log('Copy deletion commit failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('copies/index.php' . $bookFilter . '&error=save'));
}

redirect(app_url('copies/index.php' . $bookFilter . '&notice=deleted'));
