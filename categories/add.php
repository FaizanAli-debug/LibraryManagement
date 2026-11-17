<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$error = '';
$name = '';
$description = '';

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
            $check = $conn->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');

            if (!$check) {
                error_log('Category duplicate-name check preparation failed: ' . $conn->error);
                $error = 'The category could not be created. Please try again.';
            } else {
                $check->bind_param('s', $name);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error = 'A category with this name already exists.';
                }

                $check->close();
            }
        }

        if ($error === '') {
            $descriptionValue = $description === '' ? null : $description;
            $stmt = $conn->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');

            if (!$stmt) {
                error_log('Category creation preparation failed: ' . $conn->error);
                $error = 'The category could not be created. Please try again.';
            } else {
                $stmt->bind_param('ss', $name, $descriptionValue);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirect(app_url('categories/index.php?notice=created'));
                }

                error_log('Category creation failed: ' . $stmt->error);
                $error = 'The category could not be created. The name may already be in use.';
                $stmt->close();
            }
        }
    }
}

$pageTitle = 'Add Category';
$activeNav = 'categories';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Book Management</div>
        <h1>Add Category</h1>
        <p>Create a category that can be assigned to books in the collection.</p>
    </div>
    <a class="btn btn-secondary" href="<?php echo e(app_url('categories/index.php')); ?>">Back to Categories</a>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2>Category Details</h2>
            <p class="panel-intro">Category names must be unique.</p>
        </div>
    </div>

    <form method="post" action="<?php echo e(app_url('categories/add.php')); ?>">
        <?php echo csrf_field(); ?>

        <div class="form-grid">
            <div class="form-group form-group-full">
                <label for="name">Category Name</label>
                <input class="form-control" type="text" id="name" name="name"
                       value="<?php echo e($name); ?>" maxlength="100"
                       placeholder="For example: Computer Science" required>
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
            <button type="submit" class="btn btn-primary">Create Category</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
