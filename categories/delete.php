<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

if (!is_post()) {
    redirect(app_url('categories/index.php'));
}

require_csrf();

$idInput = post_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('categories/index.php?error=invalid'));
}

$categoryId = (int)$idInput;
$lookup = $conn->prepare("
    SELECT c.id,
           (SELECT COUNT(*) FROM books b WHERE b.category_id = c.id) AS book_count
    FROM categories c
    WHERE c.id = ?
    LIMIT 1
");

if (!$lookup) {
    error_log('Category delete lookup preparation failed: ' . $conn->error);
    redirect(app_url('categories/index.php?error=save'));
}

$lookup->bind_param('i', $categoryId);
$lookup->execute();
$category = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$category) {
    redirect(app_url('categories/index.php?error=notfound'));
}

if ((int)$category['book_count'] > 0) {
    redirect(app_url('categories/index.php?error=inuse'));
}

$stmt = $conn->prepare('DELETE FROM categories WHERE id = ?');

if (!$stmt) {
    error_log('Category deletion preparation failed: ' . $conn->error);
    redirect(app_url('categories/index.php?error=save'));
}

$stmt->bind_param('i', $categoryId);

if (!$stmt->execute()) {
    error_log('Category deletion failed: ' . $stmt->error);
    $stmt->close();
    redirect(app_url('categories/index.php?error=save'));
}

$stmt->close();
redirect(app_url('categories/index.php?notice=deleted'));
