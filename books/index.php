<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$noticeMap = [
    'created' => 'Book created successfully.',
    'updated' => 'Book updated successfully.',
    'deleted' => 'Book deleted successfully.',
];

$errorMap = [
    'notfound' => 'The requested book could not be found.',
    'history' => 'This book cannot be deleted because one or more copies have loan history.',
    'invalid' => 'The request was invalid. Please try again.',
    'save' => 'The change could not be saved. Please try again.',
];

$notice = $noticeMap[get_value('notice')] ?? '';
$error = $errorMap[get_value('error')] ?? '';
$search = get_value('search');
$categoryInput = get_value('category');
$availability = get_value('availability');

if (function_exists('mb_substr')) {
    $search = mb_substr($search, 0, 200);
} else {
    $search = substr($search, 0, 200);
}

$categoryId = ctype_digit($categoryInput) ? (int)$categoryInput : 0;

if (!in_array($availability, ['available', 'unavailable'], true)) {
    $availability = '';
}

$innerSql = "
    SELECT
        b.id,
        b.title,
        b.isbn,
        b.publisher,
        b.publication_year,
        a.name AS author_name,
        c.name AS category_name,
        (SELECT COUNT(*) FROM book_copies bc WHERE bc.book_id = b.id) AS total_copies,
        (SELECT COUNT(*)
         FROM book_copies bc
         WHERE bc.book_id = b.id
           AND NOT EXISTS (
               SELECT 1 FROM loans l
               WHERE l.copy_id = bc.id AND l.returned_at IS NULL
           )) AS available_copies,
        (SELECT COUNT(*)
         FROM book_copies bc
         WHERE bc.book_id = b.id
           AND EXISTS (
               SELECT 1 FROM loans l
               WHERE l.copy_id = bc.id
           )) AS history_copies
    FROM books b
    LEFT JOIN authors a ON a.id = b.author_id
    LEFT JOIN categories c ON c.id = b.category_id
";

$conditions = [];
$types = '';
$params = [];

if ($search !== '') {
    $conditions[] = '(b.title LIKE ? OR b.isbn LIKE ? OR a.name LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($categoryId > 0) {
    $conditions[] = 'b.category_id = ?';
    $types .= 'i';
    $params[] = $categoryId;
}

if (count($conditions) > 0) {
    $innerSql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql = 'SELECT * FROM (' . $innerSql . ') AS book_list';

if ($availability === 'available') {
    $sql .= ' WHERE available_copies > 0';
} elseif ($availability === 'unavailable') {
    $sql .= ' WHERE available_copies = 0';
}

$sql .= ' ORDER BY title ASC';

$books = [];
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log('Book list preparation failed: ' . $conn->error);
    $error = 'Books could not be loaded right now. Please try again later.';
} else {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
    } else {
        error_log('Book list query failed: ' . $stmt->error);
        $error = 'Books could not be loaded right now. Please try again later.';
    }

    $stmt->close();
}

$categoryOptions = [];
$categoryResult = $conn->query('SELECT id, name FROM categories ORDER BY name ASC');

if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categoryOptions[] = $row;
    }

    $categoryResult->free();
}

$pageTitle = 'Books';
$activeNav = 'books';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Book Management</div>
        <h1>Books</h1>
        <p>Manage book titles, search the collection and review availability.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?php echo e(app_url('categories/index.php')); ?>">Categories</a>
        <a class="btn btn-primary" href="<?php echo e(app_url('books/add.php')); ?>">Add Book</a>
    </div>
</section>

<?php if ($notice !== ''): ?>
    <div class="alert alert-success" role="status"><?php echo e($notice); ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Book Collection</h2>
            <p class="panel-intro">Availability is calculated from physical copies and active loans.</p>
        </div>
        <span class="count-pill"><?php echo number_format(count($books)); ?> shown</span>
    </div>

    <form method="get" action="<?php echo e(app_url('books/index.php')); ?>" class="staff-filter">
        <div class="filter-field">
            <label for="search">Search Books</label>
            <input class="form-control" type="search" id="search" name="search"
                   value="<?php echo e($search); ?>" maxlength="200"
                   placeholder="Title, author or ISBN">
        </div>

        <div class="filter-field">
            <label for="category">Category</label>
            <select class="form-control" id="category" name="category">
                <option value="0">All categories</option>
                <?php foreach ($categoryOptions as $category): ?>
                    <option value="<?php echo (int)$category['id']; ?>"
                        <?php echo $categoryId === (int)$category['id'] ? 'selected' : ''; ?>>
                        <?php echo e($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="availability">Availability</label>
            <select class="form-control" id="availability" name="availability">
                <option value="">All availability</option>
                <option value="available" <?php echo $availability === 'available' ? 'selected' : ''; ?>>Available</option>
                <option value="unavailable" <?php echo $availability === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Search</button>
            <a class="btn btn-secondary" href="<?php echo e(app_url('books/index.php')); ?>">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" aria-label="Books">
            <thead>
                <tr>
                    <th scope="col">Book</th>
                    <th scope="col">Author</th>
                    <th scope="col">Category</th>
                    <th scope="col">Publisher / Year</th>
                    <th scope="col">Copies</th>
                    <th scope="col">Availability</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($books) === 0): ?>
                    <tr>
                        <td colspan="7" class="empty-cell">No books found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($books as $book): ?>
                        <?php
                        $totalCopies = (int)$book['total_copies'];
                        $availableCopies = (int)$book['available_copies'];
                        $historyCopies = (int)$book['history_copies'];

                        if ($totalCopies === 0) {
                            $availabilityLabel = 'No copies';
                            $availabilityClass = 'availability-none';
                        } elseif ($availableCopies === 0) {
                            $availabilityLabel = 'Unavailable';
                            $availabilityClass = 'availability-unavailable';
                        } else {
                            $availabilityLabel = 'Available';
                            $availabilityClass = 'availability-available';
                        }

                        if ($totalCopies === 0) {
                            $deleteConfirm = 'Delete this book? This action cannot be undone.';
                        } else {
                            $deleteConfirm = 'Delete this book and its ' . $totalCopies
                                . ' physical copy record(s)? This action cannot be undone.';
                        }
                        ?>
                        <tr>
                            <td>
                                <span class="book-title"><?php echo e($book['title']); ?></span>
                                <span class="book-subtext">ISBN: <?php echo e($book['isbn']); ?></span>
                            </td>
                            <td><?php echo e($book['author_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo e($book['category_name'] ?? 'Uncategorized'); ?></td>
                            <td>
                                <?php echo e($book['publisher'] !== null && $book['publisher'] !== '' ? $book['publisher'] : 'N/A'); ?>
                                <span class="book-subtext">
                                    <?php echo $book['publication_year'] !== null ? (int)$book['publication_year'] : 'Year unknown'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="copy-count<?php echo $totalCopies === 0 ? ' zero' : ''; ?>">
                                    <?php echo number_format($totalCopies); ?>
                                </span>
                                <span class="book-subtext">physical copies</span>
                            </td>
                            <td>
                                <div class="detail-badges">
                                    <span class="availability-badge <?php echo e($availabilityClass); ?>">
                                        <?php echo e($availabilityLabel); ?>
                                    </span>
                                    <span class="table-note">
                                        <?php echo number_format($availableCopies); ?> of
                                        <?php echo number_format($totalCopies); ?> available
                                    </span>
                                </div>
                            </td>
                            <td class="table-actions">
                                <a class="btn btn-secondary btn-small"
                                   href="<?php echo e(app_url('copies/index.php?book_id=' . (int)$book['id'])); ?>">Copies</a>
                                <a class="btn btn-secondary btn-small"
                                   href="<?php echo e(app_url('books/edit.php?id=' . (int)$book['id'])); ?>">Edit</a>

                                <?php if ($historyCopies === 0): ?>
                                    <form method="post" action="<?php echo e(app_url('books/delete.php')); ?>"
                                          class="inline-form" data-confirm="<?php echo e($deleteConfirm); ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$book['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="protected-label" title="Books with loan history cannot be deleted">
                                        Loan history
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
