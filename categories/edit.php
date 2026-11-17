<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$idInput = is_post() ? post_value('id') : get_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('categories/index.php?error=invalid'));
}

$categoryId = (int)$idInput;
$lookup = $conn->prepare("
    SELECT c.id, c.name, c.description,
           (SELECT COUNT(*) FROM books b WHERE b.category_id = c.id) AS book_count
    FROM categories c
    WHERE c.id = ?
    LIMIT 1
");

if (!$lookup) {
    error_log('Category edit lookup preparation failed: ' . $conn->error);
    redirect(app_url('categories/index.php?error=save'));
}

$lookup->bind_param('i', $categoryId);
$lookup->execute();
$category = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$category) {
    redirect(app_url('categories/index.php?error=notfound'));
}

$error = '';
$name = (string)$category['name'];
$description = (string)($category['description'] ?? '');

if (is_post()) {
    if (!csrf_verify()) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $name = post_value('name');
        $description = post_value('description');
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        $descriptionLength = function_exists('mb_strlen') ? mb_strlen($description) : strlen($description);

        if ($nameLength < 1 || $nameLength > 100) {
            $error = 'Enter a category name between 1 and 100 characters.';
        } elseif ($descriptionLength > 2000) {
            $error = 'The description must not exceed 2,000 characters.';
        } else {
            $check = $conn->prepare('SELECT id FROM categories WHERE name = ? AND id <> ? LIMIT 1');

            if (!$check) {
                error_log('Category edit duplicate-name check preparation failed: ' . $conn->error);
                $error = 'The category could not be updated. Please try again.';
            } else {
                $check->bind_param('si', $name, $categoryId);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error = 'Another category already uses this name.';
                }

                $check->close();
            }
        }

        if ($error === '') {
            $descriptionValue = $description === '' ? null : $description;
            $stmt = $conn->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');

            if (!$stmt) {
                error_log('Category update preparation failed: ' . $conn->error);
                $error = 'The category could not be updated. Please try again.';
            } else {
                $stmt->bind_param('ssi', $name, $descriptionValue, $categoryId);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirect(app_url('categories/index.php?notice=updated'));
                }

                error_log('Category update failed: ' . $stmt->error);
                $error = 'The category could not be updated. The name may already be in use.';
                $stmt->close();
            }
        }
    }
}

$bookCount = (int)$category['book_count'];
$pageTitle = 'Edit Category';
$activeNav = 'categories';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Book Management</div>
        <h1>Edit Category</h1>
        <p>Update the category name or description. Books using this category are updated automatically.</p>
    </div>
    <a class="btn btn-secondary" href="<?php echo e(app_url('categories/index.php')); ?>">Back to Categories</a>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2><?php echo e($category['name']); ?></h2>
            <p class="panel-intro">
                <?php echo $bookCount === 1 ? 'Linked to 1 book.' : 'Linked to ' . number_format($bookCount) . ' books.'; ?>
                Categories linked to books cannot be deleted.
            </p>
        </div>
        <div class="detail-badges">
            <span class="count-pill"><?php echo number_format($bookCount); ?> books</span>
        </div>
    </div>

    <form method="post" action="<?php echo e(app_url('categories/edit.php')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$category['id']; ?>">

        <div class="form-grid">
            <div class="form-group form-group-full">
                <label for="name">Category Name</label>
                <input class="form-control" type="text" id="name" name="name"
                       value="<?php echo e($name); ?>" maxlength="100"
                       placeholder="Category name" required>
            </div>

            <div class="form-group form-group-full">
                <label for="description">Description <span class="table-note">(optional)</span></label>
                <textarea class="form-control" id="description" name="description"
                          maxlength="2000" placeholder="A short description of this category"><?php echo e($description); ?></textarea>
                <div class="form-help">Up to 2,000 characters.</div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?php echo e(app_url('categories/index.php')); ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
