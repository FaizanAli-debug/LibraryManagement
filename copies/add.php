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

function next_copy_barcode(mysqli $conn): ?string
{
    $result = $conn->query("
        SELECT MAX(CAST(SUBSTRING(barcode, 5) AS UNSIGNED)) AS max_number
        FROM book_copies
        WHERE barcode REGEXP '^LIB-[0-9]+$'
    ");

    if (!$result) {
        return null;
    }

    $row = $result->fetch_assoc();
    $result->free();
    $nextNumber = (int)($row['max_number'] ?? 0) + 1;

    return 'LIB-' . str_pad((string)$nextNumber, 3, '0', STR_PAD_LEFT);
}

$error = '';
$bookInput = get_value('book_id');
$bookId = ctype_digit($bookInput) ? (int)$bookInput : 0;
$barcode = '';

$bookOptions = [];
$bookResult = $conn->query('SELECT id, title, isbn FROM books ORDER BY title ASC');

if ($bookResult) {
    while ($row = $bookResult->fetch_assoc()) {
        $bookOptions[] = $row;
    }

    $bookResult->free();
} else {
    error_log('Copy add book options failed: ' . $conn->error);
    $error = 'Books could not be loaded right now. Please try again later.';
}

$suggestedBarcode = next_copy_barcode($conn);

if (is_post()) {
    if (!csrf_verify()) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $bookInput = post_value('book_id');
        $barcodeInput = post_value('barcode');
        $bookId = ctype_digit($bookInput) ? (int)$bookInput : 0;
        $barcode = normalize_copy_barcode($barcodeInput);
        $autoGenerate = $barcode === '';

        if ($bookId <= 0) {
            $error = 'Select a valid book.';
        } elseif (!$autoGenerate && !is_valid_copy_barcode($barcode)) {
            $error = 'Enter a valid LIB- barcode using letters, numbers and hyphens only.';
        }

        if ($error === '') {
            $bookCheck = $conn->prepare('SELECT id FROM books WHERE id = ? LIMIT 1');

            if (!$bookCheck) {
                error_log('Copy add book validation preparation failed: ' . $conn->error);
                $error = 'The physical copy could not be created. Please try again.';
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

        if ($error === '' && !$autoGenerate) {
            $barcodeCheck = $conn->prepare('SELECT id FROM book_copies WHERE barcode = ? LIMIT 1');

            if (!$barcodeCheck) {
                error_log('Copy add barcode validation preparation failed: ' . $conn->error);
                $error = 'The physical copy could not be created. Please try again.';
            } else {
                $barcodeCheck->bind_param('s', $barcode);
                $barcodeCheck->execute();
                $barcodeCheck->store_result();

                if ($barcodeCheck->num_rows > 0) {
                    $error = 'A physical copy with this barcode already exists.';
                }

                $barcodeCheck->close();
            }
        }

        if ($error === '') {
            $attempts = $autoGenerate ? 5 : 1;

            for ($attempt = 0; $attempt < $attempts; $attempt++) {
                if ($autoGenerate) {
                    $barcode = next_copy_barcode($conn);

                    if ($barcode === null) {
                        error_log('Copy barcode generation failed.');
                        $error = 'A unique barcode could not be generated. Please try again.';
                        break;
                    }
                }

                $stmt = $conn->prepare('INSERT INTO book_copies (book_id, barcode) VALUES (?, ?)');

                if (!$stmt) {
                    error_log('Copy creation preparation failed: ' . $conn->error);
                    $error = 'The physical copy could not be created. Please try again.';
                    break;
                }

                $stmt->bind_param('is', $bookId, $barcode);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirect(app_url('copies/index.php?book_id=' . $bookId . '&notice=created'));
                }

                $insertError = $stmt->errno;
                $insertMessage = $stmt->error;
                $stmt->close();

                if ($insertError === 1062) {
                    if ($autoGenerate) {
                        continue;
                    }

                    $error = 'A physical copy with this barcode already exists.';
                    break;
                }

                error_log('Copy creation failed: ' . $insertMessage);
                $error = 'The physical copy could not be created. Please try again.';
                break;
            }

            if ($error === '' && !$autoGenerate) {
                $error = 'The physical copy could not be created. Please try again.';
            } elseif ($error === '' && $autoGenerate) {
                $error = 'A unique barcode could not be generated. Please try again.';
            }
        }
    }
}

$cancelUrl = app_url('copies/index.php');

if ($bookId > 0) {
    $cancelUrl = app_url('copies/index.php?book_id=' . $bookId);
}

$pageTitle = 'Add Physical Copy';
$activeNav = 'copies';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Copy Management</div>
        <h1>Add Physical Copy</h1>
        <p>Register a new physical copy for an existing book title.</p>
    </div>
    <a class="btn btn-secondary" href="<?php echo e($cancelUrl); ?>">Back to Copies</a>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2>Copy Details</h2>
            <p class="panel-intro">Each physical copy must have a unique barcode. Availability is calculated from loans.</p>
        </div>
    </div>

    <form method="post" action="<?php echo e(app_url('copies/add.php')); ?>">
        <?php echo csrf_field(); ?>

        <div class="form-grid">
            <div class="form-group form-group-full">
                <label for="book_id">Book</label>
                <select class="form-control" id="book_id" name="book_id" required>
                    <option value="">Select a book</option>
                    <?php foreach ($bookOptions as $book): ?>
                        <option value="<?php echo (int)$book['id']; ?>"
                            <?php echo $bookId === (int)$book['id'] ? 'selected' : ''; ?>>
                            <?php echo e($book['title']); ?> (<?php echo e($book['isbn']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group form-group-full">
                <label for="barcode">Barcode <span class="table-note">(optional)</span></label>
                <input class="form-control" type="text" id="barcode" name="barcode"
                       value="<?php echo e($barcode); ?>" maxlength="50"
                       placeholder="Leave blank to generate <?php echo e($suggestedBarcode ?? 'LIB-031'); ?>">
                <div class="form-help">
                    Leave blank to generate the next LIB- barcode automatically.
                    Existing convention: <?php echo e($suggestedBarcode ?? 'LIB-031'); ?>.
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?php echo e($cancelUrl); ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Copy</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
