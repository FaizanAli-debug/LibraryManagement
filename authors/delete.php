<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

if (!is_post()) {
    redirect(app_url('authors/index.php'));
}

require_csrf();

$idInput = post_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('authors/index.php?error=invalid'));
}

$authorId = (int)$idInput;
$lookup = $conn->prepare("
    SELECT a.id,
           (SELECT COUNT(*) FROM books b WHERE b.author_id = a.id) AS book_count
    FROM authors a
    WHERE a.id = ?
    LIMIT 1
");

if (!$lookup) {
    error_log('Author delete lookup preparation failed: ' . $conn->error);
    redirect(app_url('authors/index.php?error=save'));
}

$lookup->bind_param('i', $authorId);

if (!$lookup->execute()) {
    error_log('Author delete lookup failed: ' . $lookup->error);
    $lookup->close();
    redirect(app_url('authors/index.php?error=save'));
}

$author = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$author) {
    redirect(app_url('authors/index.php?error=notfound'));
}

if ((int)$author['book_count'] > 0) {
    redirect(app_url('authors/index.php?error=inuse'));
}

$stmt = $conn->prepare('DELETE FROM authors WHERE id = ?');

if (!$stmt) {
    error_log('Author deletion preparation failed: ' . $conn->error);
    redirect(app_url('authors/index.php?error=save'));
}

$stmt->bind_param('i', $authorId);

if (!$stmt->execute()) {
    error_log('Author deletion failed: ' . $stmt->error);
    $stmt->close();
    redirect(app_url('authors/index.php?error=save'));
}

$stmt->close();
redirect(app_url('authors/index.php?notice=deleted'));
