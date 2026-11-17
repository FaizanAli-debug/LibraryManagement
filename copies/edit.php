<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

function normalize_copy_barcode(string $barcode): string
{
    return strtoupper(trim($barcode));
}

function is_valid_copy_barcode(string $barcode): bool
{
    return preg_match('/^LIB-[A-Z0-9][A-Z0-9-]{0,45}$/', $barcode) === 1;
}

$idInput = is_post() ? post_value('id') : get_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('copies/index.php?error=invalid'));
}

$copyId = (int)$idInput;
$lookup = $conn->prepare("
    SELECT
        bc.id,
        bc.book_id,
        bc.barcode,
        b.title,
        b.isbn,
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
    WHERE bc.id = ?
    LIMIT 1
");

if (!$lookup) {
    error_log('Copy edit lookup preparation failed: ' . $conn->error);
    redirect(app_url('copies/index.php?error=save'));
}

$lookup->bind_param('i', $copyId);

if (!$lookup->execute()) {
    error_log('Copy edit lookup failed: ' . $lookup->error);
    $lookup->close();
    redirect(app_url('copies/index.php?error=save'));
}

$copy = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$copy) {
    redirect(app_url('copies/index.php?error=notfound'));
}

$originalBookId = (int)$copy['book_id'];
$bookId = $originalBookId;
$barcode = (string)$copy['barcode'];
$loanCount = (int)$copy['loan_count'];
$hasHistory = $loanCount > 0;
$isIssued = (int)$copy['is_issued'] === 1;
$error = '';

$bookOptions = [];
$bookResult = $conn->query('SELECT id, title, isbn FROM books ORDER BY title ASC');

if ($bookResult) {
    while ($row = $bookResult->fetch_assoc()) {
        $bookOptions[] = $row;
    }

    $bookResult->free();
} else {
    error_log('Copy edit book options failed: ' . $conn->error);
    $error = 'Books could not be loaded right now. Please try again later.';
}

if (is_post()) {
    if (!csrf_verify()) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $bookInput = post_value('book_id');
        $barcodeInput = post_value('barcode');
        $bookId = ctype_digit($bookInput) ? (int)$bookInput : 0;
        $barcode = normalize_copy_barcode($barcodeInput);

        if ($hasHistory && $bookId !== $originalBookId) {
            $error = 'The book cannot be changed because this copy has loan history.';
            $bookId = $originalBookId;
        } elseif (!$hasHistory && $bookId <= 0) {
            $error = 'Select a valid book.';
        }

        if ($error === '' && ($barcode === '' || !is_valid_copy_barcode($barcode))) {
            $error = 'Enter a valid LIB- barcode using letters, numbers and hyphens only.';
        }

        if ($error === '' && !$hasHistory) {
            $bookCheck = $conn->prepare('SELECT id FROM books WHERE id = ? LIMIT 1');

            if (!$bookCheck) {
                error_log('Copy edit book validation preparation failed: ' . $conn->error);
                $error = 'The physical copy could not be updated. Please try again.';
            } else {
                $bookCheck->bind_param('i', $bookId);
                $bookCheck->execute();
                $bookCheck->store_result();

                if ($bookCheck->num_rows === 0) {
                    $error = 'Select a valid book.';
                }

                $bookCheck->close();
            }
        }

        if ($error === '') {
            $barcodeCheck = $conn->prepare('SELECT id FROM book_copies WHERE barcode = ? AND id <> ? LIMIT 1');

            if (!$barcodeCheck) {
                error_log('Copy edit barcode validation preparation failed: ' . $conn->error);
                $error = 'The physical copy could not be updated. Please try again.';
            } else {
                $barcodeCheck->bind_param('si', $barcode, $copyId);
                $barcodeCheck->execute();
                $barcodeCheck->store_result();

                if ($barcodeCheck->num_rows > 0) {
                    $error = 'Another physical copy already uses this barcode.';
                }

                $barcodeCheck->close();
            }
        }

        if ($error === '') {
            if ($hasHistory) {
                $stmt = $conn->prepare('UPDATE book_copies SET barcode = ? WHERE id = ?');

                if ($stmt) {
                    $stmt->bind_param('si', $barcode, $copyId);
                }
            } else {
                $stmt = $conn->prepare('UPDATE book_copies SET book_id = ?, barcode = ? WHERE id = ?');

                if ($stmt) {
                    $stmt->bind_param('isi', $bookId, $barcode, $copyId);
                }
            }

            if (!$stmt) {
                error_log('Copy update preparation failed: ' . $conn->error);
                $error = 'The physical copy could not be updated. Please try again.';
            } elseif ($stmt->execute()) {
                $stmt->close();
                redirect(app_url('copies/index.php?book_id=' . $bookId . '&notice=updated'));
            } else {
                $updateError = $stmt->errno;
                $updateMessage = $stmt->error;
                $stmt->close();

                if ($updateError === 1062) {
                    $error = 'Another physical copy already uses this barcode.';
                } else {
                    error_log('Copy update failed: ' . $updateMessage);
                    $error = 'The physical copy could not be updated. Please try again.';
                }
            }
        }
    }
}

$statusLabel = $isIssued ? 'Issued' : 'Available';
$statusClass = $isIssued ? 'availability-unavailable' : 'availability-available';
$backUrl = app_url('copies/index.php?book_id=' . $originalBookId);

$pageTitle = 'Edit Physical Copy';
$activeNav = 'copies';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Copy Management</div>
        <h1>Edit Physical Copy</h1>
        <p>Update the copy barcode and, when eligible, its linked book.</p>
    </div>
    <a class="btn btn-secondary" href="<?php echo e($backUrl); ?>">Back to Copies</a>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2><?php echo e($barcode); ?></h2>
            <p class="panel-intro">
                <?php echo e($copy['title']); ?> &middot; ISBN <?php echo e($copy['isbn']); ?>
            </p>
        </div>
        <div class="detail-badges">
            <span class="availability-badge <?php echo e($statusClass); ?>">
                <?php echo e($statusLabel); ?>
            </span>
            <span class="count-pill">
                <?php echo number_format($loanCount); ?> loan record<?php echo $loanCount === 1 ? '' : 's'; ?>
            </span>
        </div>
    </div>

    <form method="post" action="<?php echo e(app_url('copies/edit.php')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$copy['id']; ?>">

        <div class="form-grid">
            <div class="form-group form-group-full">
                <label for="book_id">Book</label>
                <?php if ($hasHistory): ?>
                    <input class="form-control" type="text"
                           value="<?php echo e($copy['title']); ?> (<?php echo e($copy['isbn']); ?>)" disabled>
                    <input type="hidden" name="book_id" value="<?php echo (int)$originalBookId; ?>">
                    <div class="form-help">
                        The linked book cannot be changed because this copy has loan history.
                    </div>
                <?php else: ?>
                    <select class="form-control" id="book_id" name="book_id" required>
                        <option value="">Select a book</option>
                        <?php foreach ($bookOptions as $book): ?>
                            <option value="<?php echo (int)$book['id']; ?>"
                                <?php echo $bookId === (int)$book['id'] ? 'selected' : ''; ?>>
                                <?php echo e($book['title']); ?> (<?php echo e($book['isbn']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-help">
                        The linked book can be changed only while this copy has no loan history.
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group form-group-full">
                <label for="barcode">Barcode</label>
                <input class="form-control" type="text" id="barcode" name="barcode"
                       value="<?php echo e($barcode); ?>" maxlength="50" required>
                <div class="form-help">
                    Barcodes are stored in uppercase and must remain unique.
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?php echo e($backUrl); ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
