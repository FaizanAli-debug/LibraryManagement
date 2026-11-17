<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$idInput = is_post() ? post_value('id') : get_value('id');

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
    error_log('Author edit lookup preparation failed: ' . $conn->error);
    redirect(app_url('authors/index.php?error=save'));
}

$lookup->bind_param('i', $authorId);
$lookup->execute();
$author = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$author) {
    redirect(app_url('authors/index.php?error=notfound'));
}

$error = '';
$name = (string)$author['name'];

if (is_post()) {
    if (!csrf_verify()) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $name = post_value('name');
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);

        if ($nameLength < 1 || $nameLength > 150) {
            $error = 'Enter an author name between 1 and 150 characters.';
        } else {
            $check = $conn->prepare('SELECT id FROM authors WHERE name = ? AND id <> ? LIMIT 1');

            if (!$check) {
                error_log('Author edit duplicate-name check preparation failed: ' . $conn->error);
                $error = 'The author could not be updated. Please try again.';
            } else {
                $check->bind_param('si', $name, $authorId);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error = 'Another author already uses this name.';
                }

                $check->close();
            }
        }

        if ($error === '') {
            $stmt = $conn->prepare('UPDATE authors SET name = ? WHERE id = ?');

            if (!$stmt) {
                error_log('Author update preparation failed: ' . $conn->error);
                $error = 'The author could not be updated. Please try again.';
            } else {
                $stmt->bind_param('si', $name, $authorId);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirect(app_url('authors/index.php?notice=updated'));
                }

                error_log('Author update failed: ' . $stmt->error);
                $error = 'The author could not be updated. The name may already be in use.';
                $stmt->close();
            }
        }
    }
}

$bookCount = (int)$author['book_count'];
$pageTitle = 'Edit Author';
$activeNav = 'authors';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Book Management</div>
        <h1>Edit Author</h1>
        <p>Update the author name. Linked book records are updated automatically.</p>
    </div>
    <a class="btn btn-secondary" href="<?php echo e(app_url('authors/index.php')); ?>">Back to Authors</a>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2><?php echo e($author['name']); ?></h2>
            <p class="panel-intro">
                <?php echo $bookCount === 1 ? 'Linked to 1 book.' : 'Linked to ' . number_format($bookCount) . ' books.'; ?>
                Authors linked to books cannot be deleted.
            </p>
        </div>
        <div class="detail-badges">
            <span class="count-pill"><?php echo number_format($bookCount); ?> titles</span>
        </div>
    </div>

    <form method="post" action="<?php echo e(app_url('authors/edit.php')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$author['id']; ?>">

        <div class="form-grid">
            <div class="form-group form-group-full">
                <label for="name">Author Name</label>
                <input class="form-control" type="text" id="name" name="name"
                       value="<?php echo e($name); ?>" maxlength="150"
                       placeholder="Author name" required>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?php echo e(app_url('authors/index.php')); ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
