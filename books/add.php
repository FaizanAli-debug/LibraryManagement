<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$error = '';
$title = '';
$isbn = '';
$authorId = 0;
$categoryId = 0;
$publisher = '';
$yearInput = '';
$publicationYear = null;

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
                $error = 'The book could not be created. Please try again.';
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
                $error = 'The book could not be created. Please try again.';
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
            $isbnCheck = $conn->prepare('SELECT id FROM books WHERE isbn = ? LIMIT 1');

            if (!$isbnCheck) {
                error_log('Book ISBN validation preparation failed: ' . $conn->error);
                $error = 'The book could not be created. Please try again.';
            } else {
                $isbnCheck->bind_param('s', $isbn);
                $isbnCheck->execute();
                $isbnCheck->store_result();

                if ($isbnCheck->num_rows > 0) {
                    $error = 'A book with this ISBN already exists.';
                }

                $isbnCheck->close();
            }
        }

        if ($error === '') {
            $publisherValue = $publisher === '' ? null : $publisher;
            $stmt = $conn->prepare("
                INSERT INTO books (title, isbn, author_id, category_id, publisher, publication_year)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                error_log('Book creation preparation failed: ' . $conn->error);
                $error = 'The book could not be created. Please try again.';
            } else {
                $stmt->bind_param('ssiisi', $title, $isbn, $authorId, $categoryId, $publisherValue, $publicationYear);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirect(app_url('books/index.php?notice=created'));
                }

                error_log('Book creation failed: ' . $stmt->error);
                $error = 'The book could not be created. The ISBN may already be in use.';
                $stmt->close();
            }
        }
    }
}

$pageTitle = 'Add Book';
$activeNav = 'books';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Book Management</div>
        <h1>Add Book</h1>
        <p>Create a new bibliographic record for the library collection.</p>
    </div>
    <a class="btn btn-secondary" href="<?php echo e(app_url('books/index.php')); ?>">Back to Books</a>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2>Book Details</h2>
            <p class="panel-intro">Physical copies are managed separately. New titles start with zero copies.</p>
        </div>
    </div>

    <form method="post" action="<?php echo e(app_url('books/add.php')); ?>">
        <?php echo csrf_field(); ?>

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
            <button type="submit" class="btn btn-primary">Create Book</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
