<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$noticeMap = [
    'created' => 'Physical copy created successfully.',
    'updated' => 'Physical copy updated successfully.',
    'deleted' => 'Physical copy deleted successfully.',
];

$errorMap = [
    'notfound' => 'The requested physical copy could not be found.',
    'history' => 'This copy cannot be deleted because it has loan history.',
    'invalid' => 'The request was invalid. Please try again.',
    'save' => 'The change could not be saved. Please try again.',
    'booknotfound' => 'The selected book could not be found.',
];

$notice = $noticeMap[get_value('notice')] ?? '';
$error = $errorMap[get_value('error')] ?? '';
$search = get_value('search');
$bookFilterInput = get_value('book_id');

if (function_exists('mb_substr')) {
    $search = mb_substr($search, 0, 200);
} else {
    $search = substr($search, 0, 200);
}

$bookFilterId = ctype_digit($bookFilterInput) ? (int)$bookFilterInput : 0;

if ($bookFilterInput !== '' && !ctype_digit($bookFilterInput)) {
    $error = 'Select a valid book filter.';
    $bookFilterId = 0;
}

$selectedBook = null;

if ($bookFilterId > 0) {
    $bookStmt = $conn->prepare('SELECT id, title, isbn FROM books WHERE id = ? LIMIT 1');

    if (!$bookStmt) {
        error_log('Copy book filter preparation failed: ' . $conn->error);
        $error = 'Physical copies could not be loaded right now. Please try again later.';
        $bookFilterId = 0;
    } else {
        $bookStmt->bind_param('i', $bookFilterId);

        if ($bookStmt->execute()) {
            $selectedBook = $bookStmt->get_result()->fetch_assoc();
        } else {
            error_log('Copy book filter query failed: ' . $bookStmt->error);
            $error = 'Physical copies could not be loaded right now. Please try again later.';
        }

        $bookStmt->close();

        if ($selectedBook === null) {
            $error = 'The selected book could not be found.';
            $bookFilterId = 0;
        }
    }
}

$conditions = [];
$types = '';
$params = [];

if ($search !== '') {
    $conditions[] = '(bc.barcode LIKE ? OR b.title LIKE ? OR b.isbn LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($bookFilterId > 0) {
    $conditions[] = 'bc.book_id = ?';
    $types .= 'i';
    $params[] = $bookFilterId;
}

$sql = "
    SELECT
        bc.id,
        bc.book_id,
        bc.barcode,
        b.title,
        b.isbn,
        a.name AS author_name,
        c.name AS category_name,
        EXISTS (
            SELECT 1
            FROM loans l
            WHERE l.copy_id = bc.id
              AND l.returned_at IS NULL
        ) AS is_issued,
        (
            SELECT COUNT(*)
            FROM loans l
            WHERE l.copy_id = bc.id
        ) AS loan_count
    FROM book_copies bc
    INNER JOIN books b ON b.id = bc.book_id
    LEFT JOIN authors a ON a.id = b.author_id
    LEFT JOIN categories c ON c.id = b.category_id
";

if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY b.title ASC, bc.barcode ASC';

$copies = [];
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log('Copy list preparation failed: ' . $conn->error);
    $error = 'Physical copies could not be loaded right now. Please try again later.';
} else {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $copies[] = $row;
        }
    } else {
        error_log('Copy list query failed: ' . $stmt->error);
        $error = 'Physical copies could not be loaded right now. Please try again later.';
    }

    $stmt->close();
}

$bookOptions = [];
$bookResult = $conn->query('SELECT id, title, isbn FROM books ORDER BY title ASC');

if ($bookResult) {
    while ($row = $bookResult->fetch_assoc()) {
        $bookOptions[] = $row;
    }

    $bookResult->free();
} else {
    error_log('Copy book filter options failed: ' . $conn->error);
}

$totalShown = count($copies);
$availableShown = 0;
$issuedShown = 0;

foreach ($copies as $copy) {
    if ((int)$copy['is_issued'] === 1) {
        $issuedShown++;
    } else {
        $availableShown++;
    }
}

$addCopyUrl = app_url('copies/add.php');

if ($bookFilterId > 0) {
    $addCopyUrl .= '?book_id=' . $bookFilterId;
}

$scopeLabel = ($search !== '' || $bookFilterId > 0) ? 'Current results' : 'All inventory';

$pageTitle = 'Physical Copies';
$activeNav = 'copies';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Copy Management</div>
        <h1>Physical Copies</h1>
        <p>Manage copy barcodes, book assignments and item availability.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?php echo e(app_url('books/index.php')); ?>">Books</a>
        <a class="btn btn-primary" href="<?php echo e($addCopyUrl); ?>">Add Copy</a>
    </div>
</section>

<?php if ($notice !== ''): ?>
    <div class="alert alert-success" role="status"><?php echo e($notice); ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="stat-grid copy-stats" aria-label="Physical copy statistics">
    <article class="stat-card">
        <div class="stat-label">Total Copies</div>
        <div class="stat-value"><?php echo number_format($totalShown); ?></div>
        <div class="stat-note"><?php echo e($scopeLabel); ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Available</div>
        <div class="stat-value"><?php echo number_format($availableShown); ?></div>
        <div class="stat-note">No active loan</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Issued</div>
        <div class="stat-value"><?php echo number_format($issuedShown); ?></div>
        <div class="stat-note">Active loan</div>
    </article>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>
                <?php if ($selectedBook !== null): ?>
                    Copies for <?php echo e($selectedBook['title']); ?>
                <?php else: ?>
                    Copy Inventory
                <?php endif; ?>
            </h2>
            <p class="panel-intro">
                <?php if ($selectedBook !== null): ?>
                    Showing physical copies linked to ISBN <?php echo e($selectedBook['isbn']); ?>.
                <?php else: ?>
                    Availability is calculated from active loans.
                <?php endif; ?>
            </p>
        </div>
        <span class="count-pill"><?php echo number_format($totalShown); ?> shown</span>
    </div>

    <form method="get" action="<?php echo e(app_url('copies/index.php')); ?>" class="staff-filter">
        <div class="filter-field">
            <label for="search">Search Copies</label>
            <input class="form-control" type="search" id="search" name="search"
                   value="<?php echo e($search); ?>" maxlength="200"
                   placeholder="Barcode, book title or ISBN">
        </div>

        <div class="filter-field">
            <label for="book_id">Book</label>
            <select class="form-control" id="book_id" name="book_id">
                <option value="0">All books</option>
                <?php foreach ($bookOptions as $book): ?>
                    <option value="<?php echo (int)$book['id']; ?>"
                        <?php echo $bookFilterId === (int)$book['id'] ? 'selected' : ''; ?>>
                        <?php echo e($book['title']); ?> (<?php echo e($book['isbn']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Search</button>
            <a class="btn btn-secondary" href="<?php echo e(app_url('copies/index.php')); ?>">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" aria-label="Physical copies">
            <thead>
                <tr>
                    <th scope="col">Barcode</th>
                    <th scope="col">Book</th>
                    <th scope="col">Author</th>
                    <th scope="col">Category</th>
                    <th scope="col">ISBN</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($totalShown === 0): ?>
                    <tr>
                        <td colspan="7" class="empty-cell">No physical copies found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($copies as $copy): ?>
                        <?php
                        $isIssued = (int)$copy['is_issued'] === 1;
                        $loanCount = (int)$copy['loan_count'];
                        $statusLabel = $isIssued ? 'Issued' : 'Available';
                        $statusClass = $isIssued ? 'availability-unavailable' : 'availability-available';
                        $deleteConfirm = 'Delete copy ' . $copy['barcode'] . '? This action cannot be undone.';
                        ?>
                        <tr>
                            <td>
                                <span class="copy-barcode"><?php echo e($copy['barcode']); ?></span>
                                <span class="book-subtext">Copy ID #<?php echo (int)$copy['id']; ?></span>
                            </td>
                            <td><span class="book-title"><?php echo e($copy['title']); ?></span></td>
                            <td><?php echo e($copy['author_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo e($copy['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><?php echo e($copy['isbn']); ?></td>
                            <td>
                                <div class="detail-badges">
                                    <span class="availability-badge <?php echo e($statusClass); ?>">
                                        <?php echo e($statusLabel); ?>
                                    </span>
                                </div>
                                <span class="table-note">
                                    <?php if ($loanCount > 0): ?>
                                        <?php echo number_format($loanCount); ?> loan record<?php echo $loanCount === 1 ? '' : 's'; ?>
                                    <?php else: ?>
                                        No loan history
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="table-actions">
                                <a class="btn btn-secondary btn-small"
                                   href="<?php echo e(app_url('copies/edit.php?id=' . (int)$copy['id'])); ?>">Edit</a>

                                <?php if ($loanCount === 0): ?>
                                    <form method="post" action="<?php echo e(app_url('copies/delete.php')); ?>"
                                          class="inline-form" data-confirm="<?php echo e($deleteConfirm); ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$copy['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="protected-label" title="Copies with loan history cannot be deleted">
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
