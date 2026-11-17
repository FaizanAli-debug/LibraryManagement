<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$idInput = get_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('authors/index.php?error=invalid'));
}

$authorId = (int)$idInput;
$lookup = $conn->prepare("
    SELECT a.id, a.name,
           (SELECT COUNT(*) FROM books b WHERE b.author_id = a.id) AS book_count
    FROM authors a
    WHERE a.id = ?
    LIMIT 1
");

if (!$lookup) {
    error_log('Author profile lookup preparation failed: ' . $conn->error);
    redirect(app_url('authors/index.php?error=save'));
}

$lookup->bind_param('i', $authorId);

if (!$lookup->execute()) {
    error_log('Author profile lookup failed: ' . $lookup->error);
    $lookup->close();
    redirect(app_url('authors/index.php?error=save'));
}

$author = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$author) {
    redirect(app_url('authors/index.php?error=notfound'));
}

$error = '';
$books = [];
$bookStmt = $conn->prepare("
    SELECT
        b.id,
        b.title,
        b.isbn,
        b.publisher,
        b.publication_year,
        c.name AS category_name,
        (SELECT COUNT(*) FROM book_copies bc WHERE bc.book_id = b.id) AS copy_count,
        (SELECT COUNT(*)
         FROM book_copies bc
         WHERE bc.book_id = b.id
           AND NOT EXISTS (
               SELECT 1
               FROM loans l
               WHERE l.copy_id = bc.id
                 AND l.returned_at IS NULL
           )) AS available_copies
    FROM books b
    INNER JOIN categories c ON c.id = b.category_id
    WHERE b.author_id = ?
    ORDER BY b.title ASC, b.id ASC
");

if (!$bookStmt) {
    error_log('Author books preparation failed: ' . $conn->error);
    $error = 'Linked books could not be loaded right now. Please try again later.';
} else {
    $bookStmt->bind_param('i', $authorId);

    if ($bookStmt->execute()) {
        $bookResult = $bookStmt->get_result();

        while ($row = $bookResult->fetch_assoc()) {
            $books[] = $row;
        }
    } else {
        error_log('Author books query failed: ' . $bookStmt->error);
        $error = 'Linked books could not be loaded right now. Please try again later.';
    }

    $bookStmt->close();
}

$totalCopies = 0;
$availableCopies = 0;

foreach ($books as $bookRow) {
    $totalCopies += (int)$bookRow['copy_count'];
    $availableCopies += (int)$bookRow['available_copies'];
}

$pageTitle = 'Author Profile';
$activeNav = 'authors';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Author Profile</div>
        <h1><?php echo e($author['name']); ?></h1>
        <p>Author ID #<?php echo (int)$author['id']; ?> &middot; <?php echo number_format((int)$author['book_count']); ?> linked title<?php echo (int)$author['book_count'] === 1 ? '' : 's'; ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?php echo e(app_url('authors/index.php')); ?>">Back to Authors</a>
        <a class="btn btn-secondary" href="<?php echo e(app_url('authors/edit.php?id=' . (int)$author['id'])); ?>">Edit Author</a>
    </div>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="stat-grid copy-stats" aria-label="Author collection statistics">
    <article class="stat-card">
        <div class="stat-label">Book Titles</div>
        <div class="stat-value"><?php echo number_format(count($books)); ?></div>
        <div class="stat-note">Linked to this author</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Physical Copies</div>
        <div class="stat-value"><?php echo number_format($totalCopies); ?></div>
        <div class="stat-note">All copies of linked titles</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Available Copies</div>
        <div class="stat-value"><?php echo number_format($availableCopies); ?></div>
        <div class="stat-note">Ready to borrow now</div>
    </article>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Books by <?php echo e($author['name']); ?></h2>
            <p class="panel-intro">Availability is calculated from active loan records.</p>
        </div>
        <span class="count-pill"><?php echo number_format(count($books)); ?> title<?php echo count($books) === 1 ? '' : 's'; ?></span>
    </div>

    <div class="table-wrap">
        <table class="data-table" aria-label="Books by author">
            <thead>
                <tr>
                    <th scope="col">Book</th>
                    <th scope="col">ISBN</th>
                    <th scope="col">Category</th>
                    <th scope="col">Publisher</th>
                    <th scope="col">Copies</th>
                    <th scope="col">Available</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($books) === 0): ?>
                    <tr>
                        <td colspan="6" class="empty-cell">No books are linked to this author yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($books as $book): ?>
                        <?php
                        $copyCount = (int)$book['copy_count'];
                        $bookAvailable = (int)$book['available_copies'];
                        ?>
                        <tr>
                            <td>
                                <span class="book-title"><?php echo e($book['title']); ?></span>
                                <span class="book-subtext">
                                    <?php echo $book['publication_year'] !== null ? 'Published ' . e($book['publication_year']) : 'Publication year not recorded'; ?>
                                </span>
                            </td>
                            <td><?php echo e($book['isbn']); ?></td>
                            <td><?php echo e($book['category_name']); ?></td>
                            <td>
                                <?php echo $book['publisher'] !== null && $book['publisher'] !== '' ? e($book['publisher']) : '&mdash;'; ?>
                            </td>
                            <td>
                                <span class="copy-count<?php echo $copyCount === 0 ? ' zero' : ''; ?>">
                                    <?php echo number_format($copyCount); ?>
                                </span>
                            </td>
                            <td>
                                <span class="availability-badge <?php echo $bookAvailable > 0 ? 'availability-available' : 'availability-unavailable'; ?>">
                                    <?php echo $bookAvailable > 0 ? number_format($bookAvailable) . ' available' : 'Unavailable'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
