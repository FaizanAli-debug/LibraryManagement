<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$error = '';
$name = '';

if (is_post()) {
    if (!csrf_verify()) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $name = post_value('name');
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);

        if ($nameLength < 1 || $nameLength > 150) {
            $error = 'Enter an author name between 1 and 150 characters.';
        } else {
            $check = $conn->prepare('SELECT id FROM authors WHERE name = ? LIMIT 1');

            if (!$check) {
                error_log('Author duplicate-name check preparation failed: ' . $conn->error);
                $error = 'The author could not be created. Please try again.';
            } else {
                $check->bind_param('s', $name);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error = 'An author with this name already exists.';
                }

                $check->close();
            }
        }

        if ($error === '') {
            $stmt = $conn->prepare('INSERT INTO authors (name) VALUES (?)');

            if (!$stmt) {
                error_log('Author creation preparation failed: ' . $conn->error);
                $error = 'The author could not be created. Please try again.';
            } else {
                $stmt->bind_param('s', $name);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirect(app_url('authors/index.php?notice=created'));
                }

                error_log('Author creation failed: ' . $stmt->error);
                $error = 'The author could not be created. The name may already be in use.';
                $stmt->close();
            }
        }
    }
}

$pageTitle = 'Add Author';
$activeNav = 'authors';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Book Management</div>
        <h1>Add Author</h1>
        <p>Create an author record that can be assigned to books in the collection.</p>
    </div>
    <a class="btn btn-secondary" href="<?php echo e(app_url('authors/index.php')); ?>">Back to Authors</a>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2>Author Details</h2>
            <p class="panel-intro">Author names must be unique.</p>
        </div>
    </div>

    <form method="post" action="<?php echo e(app_url('authors/add.php')); ?>">
        <?php echo csrf_field(); ?>

        <div class="form-grid">
            <div class="form-group form-group-full">
                <label for="name">Author Name</label>
                <input class="form-control" type="text" id="name" name="name"
                       value="<?php echo e($name); ?>" maxlength="150"
                       placeholder="For example: Jane Austen" required>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?php echo e(app_url('authors/index.php')); ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Author</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
