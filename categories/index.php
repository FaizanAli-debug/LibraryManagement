<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$noticeMap = [
    'created' => 'Category created successfully.',
    'updated' => 'Category updated successfully.',
    'deleted' => 'Category deleted successfully.',
];

$errorMap = [
    'notfound' => 'The requested category could not be found.',
    'inuse' => 'This category cannot be deleted because it is linked to one or more books.',
    'invalid' => 'The request was invalid. Please try again.',
    'save' => 'The change could not be saved. Please try again.',
];

$notice = $noticeMap[get_value('notice')] ?? '';
$error = $errorMap[get_value('error')] ?? '';
$search = get_value('search');

if (function_exists('mb_substr')) {
    $search = mb_substr($search, 0, 100);
} else {
    $search = substr($search, 0, 100);
}

$sql = "
    SELECT c.id, c.name, c.description, COUNT(b.id) AS book_count
    FROM categories c
    LEFT JOIN books b ON b.category_id = c.id
";

if ($search !== '') {
    $sql .= ' WHERE c.name LIKE ?';
}

$sql .= ' GROUP BY c.id, c.name, c.description ORDER BY c.name ASC';

$categories = [];
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log('Category list preparation failed: ' . $conn->error);
    $error = 'Categories could not be loaded right now. Please try again later.';
} else {
    if ($search !== '') {
        $like = '%' . $search . '%';
        $stmt->bind_param('s', $like);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    } else {
        error_log('Category list query failed: ' . $stmt->error);
        $error = 'Categories could not be loaded right now. Please try again later.';
    }

    $stmt->close();
}

$pageTitle = 'Categories';
$activeNav = 'categories';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Book Management</div>
        <h1>Categories</h1>
        <p>Organize the collection by creating and maintaining book categories.</p>
    </div>
    <a class="btn btn-primary" href="<?php echo e(app_url('categories/add.php')); ?>">Add Category</a>
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
            <h2>Book Categories</h2>
            <p class="panel-intro">Categories linked to books are protected from deletion.</p>
        </div>
        <span class="count-pill"><?php echo number_format(count($categories)); ?> shown</span>
    </div>

    <form method="get" action="<?php echo e(app_url('categories/index.php')); ?>" class="staff-filter">
        <div class="filter-field">
            <label for="search">Search Categories</label>
            <input class="form-control" type="search" id="search" name="search"
                   value="<?php echo e($search); ?>" maxlength="100" placeholder="Search by category name">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Search</button>
            <a class="btn btn-secondary" href="<?php echo e(app_url('categories/index.php')); ?>">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" aria-label="Book categories">
            <thead>
                <tr>
                    <th scope="col">Category</th>
                    <th scope="col">Description</th>
                    <th scope="col">Books</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($categories) === 0): ?>
                    <tr>
                        <td colspan="4" class="empty-cell">No categories found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <?php $bookCount = (int)$category['book_count']; ?>
                        <tr>
                            <td><span class="book-title"><?php echo e($category['name']); ?></span></td>
                            <td>
                                <?php if ((string)$category['description'] !== ''): ?>
                                    <span class="category-description"><?php echo e($category['description']); ?></span>
                                <?php else: ?>
                                    <span class="table-note">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="copy-count<?php echo $bookCount === 0 ? ' zero' : ''; ?>">
                                    <?php echo number_format($bookCount); ?>
                                </span>
                            </td>
                            <td class="table-actions">
                                <a class="btn btn-secondary btn-small"
                                   href="<?php echo e(app_url('categories/edit.php?id=' . (int)$category['id'])); ?>">Edit</a>

                                <?php if ($bookCount === 0): ?>
                                    <form method="post" action="<?php echo e(app_url('categories/delete.php')); ?>"
                                          class="inline-form"
                                          data-confirm="Delete this category? This action cannot be undone.">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$category['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="protected-label" title="Delete the linked books before removing this category">
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
