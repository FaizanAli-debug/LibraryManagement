<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$noticeMap = [
    'created' => 'Author created successfully.',
    'updated' => 'Author updated successfully.',
    'deleted' => 'Author deleted successfully.',
];

$errorMap = [
    'notfound' => 'The requested author could not be found.',
    'inuse' => 'This author cannot be deleted because one or more books are linked to them.',
    'invalid' => 'The request was invalid. Please try again.',
    'save' => 'The change could not be saved. Please try again.',
];

$notice = $noticeMap[get_value('notice')] ?? '';
$error = $errorMap[get_value('error')] ?? '';
$search = get_value('search');

if (function_exists('mb_substr')) {
    $search = mb_substr($search, 0, 150);
} else {
    $search = substr($search, 0, 150);
}

$sql = "
    SELECT
        a.id,
        a.name,
        (SELECT COUNT(*) FROM books b WHERE b.author_id = a.id) AS book_count,
        (SELECT COUNT(*)
         FROM book_copies bc
         INNER JOIN books b ON b.id = bc.book_id
         WHERE b.author_id = a.id) AS copy_count
    FROM authors a
";

if ($search !== '') {
    $sql .= ' WHERE a.name LIKE ?';
}

$sql .= ' ORDER BY a.name ASC, a.id ASC';

$authors = [];
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log('Author list preparation failed: ' . $conn->error);
    $error = 'Authors could not be loaded right now. Please try again later.';
} else {
    if ($search !== '') {
        $like = '%' . $search . '%';
        $stmt->bind_param('s', $like);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $authors[] = $row;
        }
    } else {
        error_log('Author list query failed: ' . $stmt->error);
        $error = 'Authors could not be loaded right now. Please try again later.';
    }

    $stmt->close();
}

$totalTitles = 0;
$totalCopies = 0;

foreach ($authors as $authorRow) {
    $totalTitles += (int)$authorRow['book_count'];
    $totalCopies += (int)$authorRow['copy_count'];
}

$scopeLabel = $search !== '' ? 'Current results' : 'All authors';

$pageTitle = 'Authors';
$activeNav = 'authors';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Book Management</div>
        <h1>Authors</h1>
        <p>Maintain the authors linked to titles in the library collection.</p>
    </div>
    <a class="btn btn-primary" href="<?php echo e(app_url('authors/add.php')); ?>">Add Author</a>
</section>

<?php if ($notice !== ''): ?>
    <div class="alert alert-success" role="status"><?php echo e($notice); ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="stat-grid copy-stats" aria-label="Author statistics">
    <article class="stat-card">
        <div class="stat-label">Authors Shown</div>
        <div class="stat-value"><?php echo number_format(count($authors)); ?></div>
        <div class="stat-note"><?php echo e($scopeLabel); ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Linked Titles</div>
        <div class="stat-value"><?php echo number_format($totalTitles); ?></div>
        <div class="stat-note">Books by these authors</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Physical Copies</div>
        <div class="stat-value"><?php echo number_format($totalCopies); ?></div>
        <div class="stat-note">Copies of linked titles</div>
    </article>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Author Directory</h2>
            <p class="panel-intro">Authors linked to books are protected from deletion.</p>
        </div>
        <span class="count-pill"><?php echo number_format(count($authors)); ?> shown</span>
    </div>

    <form method="get" action="<?php echo e(app_url('authors/index.php')); ?>" class="staff-filter">
        <div class="filter-field">
            <label for="search">Search Authors</label>
            <input class="form-control" type="search" id="search" name="search"
                   value="<?php echo e($search); ?>" maxlength="150" placeholder="Search by author name">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Search</button>
            <a class="btn btn-secondary" href="<?php echo e(app_url('authors/index.php')); ?>">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" aria-label="Book authors">
            <thead>
                <tr>
                    <th scope="col">Author</th>
                    <th scope="col">Titles</th>
                    <th scope="col">Copies</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($authors) === 0): ?>
                    <tr>
                        <td colspan="4" class="empty-cell">No authors found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($authors as $author): ?>
                        <?php
                        $bookCount = (int)$author['book_count'];
                        $copyCount = (int)$author['copy_count'];
                        $deleteConfirm = 'Delete ' . $author['name'] . '? This action cannot be undone.';
                        ?>
                        <tr>
                            <td>
                                <a class="book-title" href="<?php echo e(app_url('authors/view.php?id=' . (int)$author['id'])); ?>">
                                    <?php echo e($author['name']); ?>
                                </a>
                                <span class="book-subtext">Author ID #<?php echo (int)$author['id']; ?></span>
                            </td>
                            <td>
                                <span class="copy-count<?php echo $bookCount === 0 ? ' zero' : ''; ?>">
                                    <?php echo number_format($bookCount); ?>
                                </span>
                            </td>
                            <td>
                                <span class="copy-count<?php echo $copyCount === 0 ? ' zero' : ''; ?>">
                                    <?php echo number_format($copyCount); ?>
                                </span>
                            </td>
                            <td class="table-actions">
                                <a class="btn btn-secondary btn-small"
                                   href="<?php echo e(app_url('authors/view.php?id=' . (int)$author['id'])); ?>">View</a>
                                <a class="btn btn-secondary btn-small"
                                   href="<?php echo e(app_url('authors/edit.php?id=' . (int)$author['id'])); ?>">Edit</a>

                                <?php if ($bookCount === 0): ?>
                                    <form method="post" action="<?php echo e(app_url('authors/delete.php')); ?>"
                                          class="inline-form" data-confirm="<?php echo e($deleteConfirm); ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$author['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="protected-label" title="Delete or reassign the linked books before removing this author">
                                        In use
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
