<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$idInput = is_post() ? post_value('id') : get_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('books/index.php?error=invalid'));
}

$bookId = (int)$idInput;
$lookup = $conn->prepare("
    SELECT b.id, b.title, b.isbn, b.author_id, b.category_id, b.publisher, b.publication_year,
           (SELECT COUNT(*) FROM book_copies bc WHERE bc.book_id = b.id) AS total_copies,
           (SELECT COUNT(*)
            FROM book_copies bc
            WHERE bc.book_id = b.id
              AND NOT EXISTS (
                  SELECT 1 FROM loans l
                  WHERE l.copy_id = bc.id AND l.returned_at IS NULL
              )) AS available_copies
    FROM books b
    WHERE b.id = ?
    LIMIT 1
");

if (!$lookup) {
    error_log('Book edit lookup preparation failed: ' . $conn->error);
    redirect(app_url('books/index.php?error=save'));
}

$lookup->bind_param('i', $bookId);
$lookup->execute();
$book = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$book) {
    redirect(app_url('books/index.php?error=notfound'));
}

$error = '';
$title = (string)$book['title'];
$isbn = (string)$book['isbn'];
$authorId = (int)$book['author_id'];
$categoryId = (int)$book['category_id'];
$publisher = (string)($book['publisher'] ?? '');
$yearInput = $book['publication_year'] !== null ? (string)$book['publication_year'] : '';
$publicationYear = $book['publication_year'] !== null ? (int)$book['publication_year'] : null;

$authorOptions = [];
$authorResult = $conn->query('SELECT id, name FROM authors ORDER BY name ASC');

if ($authorResult) {
    while ($row = $authorResult->fetch_assoc()) {
        $authorOptions[] = $row;
    }

    $authorResult->free();
} else {
    $error = 'Authors could not be loaded right now. Please try again later.';
}

$categoryOptions = [];
$categoryResult = $conn->query('SELECT id, name FROM categories ORDER BY name ASC');

if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categoryOptions[] = $row;
    }

    $categoryResult->free();
} else {
    $error = 'Categories could not be loaded right now. Please try again later.';
}

if (is_post()) {
    if (!csrf_verify()) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $title = post_value('title');
        $isbn = post_value('isbn');
        $authorInput = post_value('author_id');
        $categoryInput = post_value('category_id');
        $publisher = post_value('publisher');
        $yearInput = post_value('publication_year');

        $authorId = ctype_digit($authorInput) ? (int)$authorInput : 0;
        $categoryId = ctype_digit($categoryInput) ? (int)$categoryInput : 0;
        $titleLength = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
        $publisherLength = function_exists('mb_strlen') ? mb_strlen($publisher) : strlen($publisher);
        $maxYear = (int)date('Y') + 1;
        $publicationYear = null;

        if ($titleLength < 1 || $titleLength > 200) {
            $error = 'Enter a book title between 1 and 200 characters.';
        } elseif (strlen($isbn) < 1 || strlen($isbn) > 20) {
            $error = 'Enter an ISBN between 1 and 20 characters.';
        } elseif ($authorId <= 0) {
            $error = 'Select a valid author.';
        } elseif ($categoryId <= 0) {
            $error = 'Select a valid category.';
        } elseif ($publisherLength > 150) {
            $error = 'The publisher name must not exceed 150 characters.';
        } elseif ($yearInput !== '') {
            $yearValue = filter_var($yearInput, FILTER_VALIDATE_INT);

            if ($yearValue === false || $yearValue < 1000 || $yearValue > $maxYear) {
                $error = 'Enter a publication year between 1000 and ' . $maxYear . '.';
            } else {
                $publicationYear = $yearValue;
            }
        }

        if ($error === '') {
            $authorCheck = $conn->prepare('SELECT id FROM authors WHERE id = ? LIMIT 1');

            if (!$authorCheck) {
                error_log('Book author validation preparation failed: ' . $conn->error);
                $error = 'The book could not be updated. Please try again.';
            } else {
                $authorCheck->bind_param('i', $authorId);
                $authorCheck->execute();
                $authorCheck->store_result();

                if ($authorCheck->num_rows === 0) {
                    $error = 'Select a valid author.';
                }

                $authorCheck->close();
            }
        }

        if ($error === '') {
            $categoryCheck = $conn->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');

            if (!$categoryCheck) {
                error_log('Book category validation preparation failed: ' . $conn->error);
                $error = 'The book could not be updated. Please try again.';
            } else {
                $categoryCheck->bind_param('i', $categoryId);
                $categoryCheck->execute();
                $categoryCheck->store_result();

                if ($categoryCheck->num_rows === 0) {
                    $error = 'Select a valid category.';
                }

                $categoryCheck->close();
            }
        }

        if ($error === '') {
            $isbnCheck = $conn->prepare('SELECT id FROM books WHERE isbn = ? AND id <> ? LIMIT 1');

            if (!$isbnCheck) {
                error_log('Book ISBN validation preparation failed: ' . $conn->error);
                $error = 'The book could not be updated. Please try again.';
            } else {
                $isbnCheck->bind_param('si', $isbn, $bookId);
                $isbnCheck->execute();
                $isbnCheck->store_result();

                if ($isbnCheck->num_rows > 0) {
                    $error = 'Another book already uses this ISBN.';
                }

                $isbnCheck->close();
            }
        }

        if ($error === '') {
            $publisherValue = $publisher === '' ? null : $publisher;
            $stmt = $conn->prepare("
                UPDATE books
                SET title = ?, isbn = ?, author_id = ?, category_id = ?, publisher = ?, publication_year = ?
                WHERE id = ?
            ");

            if (!$stmt) {
                error_log('Book update preparation failed: ' . $conn->error);
                $error = 'The book could not be updated. Please try again.';
            } else {
                $stmt->bind_param('ssiisii', $title, $isbn, $authorId, $categoryId, $publisherValue, $publicationYear, $bookId);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirect(app_url('books/index.php?notice=updated'));
                }

                error_log('Book update failed: ' . $stmt->error);
                $error = 'The book could not be updated. The ISBN may already be in use.';
                $stmt->close();
            }
        }
    }
}

$totalCopies = (int)$book['total_copies'];
$availableCopies = (int)$book['available_copies'];

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

$pageTitle = 'Edit Book';
$activeNav = 'books';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Book Management</div>
        <h1>Edit Book</h1>
        <p>Update the book metadata. Physical copies and availability are managed separately.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?php echo e(app_url('books/index.php')); ?>">Back to Books</a>
        <a class="btn btn-primary" href="<?php echo e(app_url('copies/index.php?book_id=' . (int)$book['id'])); ?>">Manage Copies</a>
    </div>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2><?php echo e($book['title']); ?></h2>
            <p class="panel-intro">
                <?php echo number_format($availableCopies); ?> of <?php echo number_format($totalCopies); ?>
                physical copies available.
            </p>
        </div>
        <div class="detail-badges">
            <span class="availability-badge <?php echo e($availabilityClass); ?>">
                <?php echo e($availabilityLabel); ?>
            </span>
            <span class="count-pill"><?php echo number_format($totalCopies); ?> copies</span>
        </div>
    </div>

    <form method="post" action="<?php echo e(app_url('books/edit.php')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$book['id']; ?>">

        <div class="form-grid">
            <div class="form-group form-group-full">
                <label for="title">Title</label>
                <input class="form-control" type="text" id="title" name="title"
                       value="<?php echo e($title); ?>" maxlength="200"
                       placeholder="Book title" required>
            </div>

            <div class="form-group">
                <label for="isbn">ISBN</label>
                <input class="form-control" type="text" id="isbn" name="isbn"
                       value="<?php echo e($isbn); ?>" maxlength="20"
                       placeholder="Unique ISBN" required>
            </div>

            <div class="form-group">
                <label for="author_id">Author</label>
                <select class="form-control" id="author_id" name="author_id" required>
                    <option value="">Select an author</option>
                    <?php foreach ($authorOptions as $author): ?>
                        <option value="<?php echo (int)$author['id']; ?>"
                            <?php echo $authorId === (int)$author['id'] ? 'selected' : ''; ?>>
                            <?php echo e($author['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="category_id">Category</label>
                <select class="form-control" id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categoryOptions as $category): ?>
                        <option value="<?php echo (int)$category['id']; ?>"
                            <?php echo $categoryId === (int)$category['id'] ? 'selected' : ''; ?>>
                            <?php echo e($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-help">
                    <a href="<?php echo e(app_url('categories/index.php')); ?>">Manage categories</a>
                </div>
            </div>

            <div class="form-group">
                <label for="publisher">Publisher <span class="table-note">(optional)</span></label>
                <input class="form-control" type="text" id="publisher" name="publisher"
                       value="<?php echo e($publisher); ?>" maxlength="150"
                       placeholder="Publisher name">
            </div>

            <div class="form-group">
                <label for="publication_year">Publication Year <span class="table-note">(optional)</span></label>
                <input class="form-control" type="number" id="publication_year" name="publication_year"
                       value="<?php echo e($yearInput); ?>" min="1000" max="<?php echo (int)date('Y') + 1; ?>"
                       step="1" placeholder="For example: <?php echo date('Y'); ?>">
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?php echo e(app_url('books/index.php')); ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
