<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

if (!is_post()) {
    redirect(app_url('books/index.php'));
}

require_csrf();

$idInput = post_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('books/index.php?error=invalid'));
}

$bookId = (int)$idInput;

if (!$conn->begin_transaction()) {
    error_log('Book deletion transaction could not start: ' . $conn->error);
    redirect(app_url('books/index.php?error=save'));
}

$lookup = $conn->prepare('SELECT id FROM books WHERE id = ? FOR UPDATE');

if (!$lookup) {
    error_log('Book deletion lookup preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('books/index.php?error=save'));
}

$lookup->bind_param('i', $bookId);

if (!$lookup->execute()) {
    error_log('Book deletion lookup failed: ' . $lookup->error);
    $lookup->close();
    $conn->rollback();
    redirect(app_url('books/index.php?error=save'));
}

$book = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$book) {
    $conn->rollback();
    redirect(app_url('books/index.php?error=notfound'));
}

$copyLookup = $conn->prepare('SELECT id FROM book_copies WHERE book_id = ? FOR UPDATE');

if (!$copyLookup) {
    error_log('Book copy lookup preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('books/index.php?error=save'));
}

$copyLookup->bind_param('i', $bookId);

if (!$copyLookup->execute()) {
    error_log('Book copy lookup failed: ' . $copyLookup->error);
    $copyLookup->close();
    $conn->rollback();
    redirect(app_url('books/index.php?error=save'));
}

$copyResult = $copyLookup->get_result();
$copyResult->fetch_all(MYSQLI_ASSOC);
$copyLookup->close();

$historyCheck = $conn->prepare("
    SELECT COUNT(*) AS history_count
    FROM loans l
    INNER JOIN book_copies bc ON bc.id = l.copy_id
    WHERE bc.book_id = ?
");

if (!$historyCheck) {
    error_log('Book loan-history check preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('books/index.php?error=save'));
}

$historyCheck->bind_param('i', $bookId);

if (!$historyCheck->execute()) {
    error_log('Book loan-history check failed: ' . $historyCheck->error);
    $historyCheck->close();
    $conn->rollback();
    redirect(app_url('books/index.php?error=save'));
}

$historyRow = $historyCheck->get_result()->fetch_assoc();
$historyCheck->close();
$historyCount = $historyRow ? (int)$historyRow['history_count'] : 0;

if ($historyCount > 0) {
    $conn->rollback();
    redirect(app_url('books/index.php?error=history'));
}

$deleteCopies = $conn->prepare('DELETE FROM book_copies WHERE book_id = ?');

if (!$deleteCopies) {
    error_log('Book copy deletion preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('books/index.php?error=save'));
}

$deleteCopies->bind_param('i', $bookId);

if (!$deleteCopies->execute()) {
    error_log('Book copy deletion failed: ' . $deleteCopies->error);
    $deleteCopies->close();
    $conn->rollback();
    redirect(app_url('books/index.php?error=save'));
}

$deleteCopies->close();

$deleteBook = $conn->prepare('DELETE FROM books WHERE id = ?');

if (!$deleteBook) {
    error_log('Book deletion preparation failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('books/index.php?error=save'));
}

$deleteBook->bind_param('i', $bookId);

if (!$deleteBook->execute()) {
    error_log('Book deletion failed: ' . $deleteBook->error);
    $deleteBook->close();
    $conn->rollback();
    redirect(app_url('books/index.php?error=save'));
}

$deleteBook->close();

if (!$conn->commit()) {
    error_log('Book deletion commit failed: ' . $conn->error);
    $conn->rollback();
    redirect(app_url('books/index.php?error=save'));
}

redirect(app_url('books/index.php?notice=deleted'));
