
<?php
require_once "config/db.php";

$search = trim($_GET['search'] ?? '');
$search = substr($search, 0, 200);
$categoryId = (int)($_GET['category'] ?? 0);
$availability = trim($_GET['availability'] ?? '');

if (!in_array($availability, ['available', 'unavailable'], true)) {
    $availability = '';
}

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(title LIKE ? OR author_name LIKE ? OR isbn LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($categoryId > 0) {
    $where[] = "category_id = ?";
    $params[] = $categoryId;
    $types .= 'i';
}

$innerSql = "
    SELECT
        b.id,
        b.title,
        b.isbn,
        b.publisher,
        b.publication_year,
        b.category_id,
        a.name AS author_name,
        c.name AS category_name,
        (
            SELECT COUNT(*)
            FROM book_copies bc
            WHERE bc.book_id = b.id
        ) AS total_copies,
        (
            SELECT COUNT(*)
            FROM book_copies bc
            WHERE bc.book_id = b.id
              AND NOT EXISTS (
                  SELECT 1
                  FROM loans l
                  WHERE l.copy_id = bc.id
                    AND l.returned_at IS NULL
              )
        ) AS available_copies
    FROM books b
    LEFT JOIN authors a ON a.id = b.author_id
    LEFT JOIN categories c ON c.id = b.category_id
";

$sql = "SELECT * FROM (" . $innerSql . ") AS catalog_books";

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

if ($availability === 'available') {
    $sql .= " AND available_copies > 0";
} elseif ($availability === 'unavailable') {
    $sql .= " AND total_copies > 0 AND available_copies = 0";
}

$sql .= " ORDER BY id DESC";

$books = [];
$loadError = '';

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $loadError = 'Unable to load the catalog right now.';
} else {
    if ($where) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $loadError = 'Unable to load the catalog right now.';
    } else {
        $result = $stmt->get_result();

        while ($result && ($row = $result->fetch_assoc())) {
            $books[] = $row;
        }
    }

    $stmt->close();
}

$categories = $conn->query("SELECT id, name FROM categories ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Collection | Grand Library</title>
<link rel="stylesheet" href="assets/tera.css">
</head>
<body>

<nav class="navbar">
    <div class="logo">Grand<span>Library.</span></div>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="catalog.php">Collection</a>
        <a href="index.php#about">About</a>
        <a href="login.php" class="nav-btn">Staff Login</a>
    </div>
</nav>

<section class="catalog-section">
    <div class="catalog-heading">
        <span class="eyebrow">DISCOVER YOUR NEXT READ</span>
        <h1>Our Book Collection</h1>
        <p>Explore our collection of knowledge and stories.</p>
    </div>

    <form method="GET" class="catalog-filter">
        <input type="text" name="search"
               placeholder="Search title, author or ISBN..."
               value="<?php echo htmlspecialchars($search); ?>">

        <select name="category">
            <option value="0">All Categories</option>
            <?php if ($categories): ?>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?php echo (int)$cat['id']; ?>"
                        <?php echo $categoryId === (int)$cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>

        <select name="availability">
            <option value="">All Availability</option>
            <option value="available" <?php echo $availability === 'available' ? 'selected' : ''; ?>>Available</option>
            <option value="unavailable" <?php echo $availability === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
        </select>

        <button type="submit">Search Books</button>
        <a href="catalog.php" class="reset-btn">Reset</a>
    </form>

    <div class="catalog-grid">
        <?php if ($loadError !== ''): ?>
            <p class="catalog-empty"><?php echo htmlspecialchars($loadError); ?></p>
        <?php elseif (count($books) > 0): ?>
            <?php foreach ($books as $book): ?>
                <?php
                $availableCopies = (int)$book['available_copies'];
                $totalCopies = (int)$book['total_copies'];

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
                ?>
                <article class="catalog-card">
                    <div class="catalog-cover">
                        <span>BOOK COLLECTION</span>
                        <div class="cover-icon">▤</div>
                    </div>

                    <div class="catalog-info">
                        <span class="book-tag">
                            <?php echo htmlspecialchars($book['category_name'] ?? 'General'); ?>
                        </span>

                        <h3><?php echo htmlspecialchars($book['title']); ?></h3>

                        <p>By <?php echo htmlspecialchars($book['author_name'] ?? 'Unknown'); ?></p>
                        <p>Publisher: <?php echo htmlspecialchars($book['publisher'] ?? 'N/A'); ?></p>
                        <?php if (!empty($book['publication_year'])): ?>
                            <p class="catalog-year">Published: <?php echo (int)$book['publication_year']; ?></p>
                        <?php endif; ?>
                        <p>ISBN: <?php echo htmlspecialchars($book['isbn'] ?? 'N/A'); ?></p>

                        <div class="catalog-availability">
                            <div class="detail-badges">
                                <span class="availability-badge <?php echo $availabilityClass; ?>"><?php echo $availabilityLabel; ?></span>
                                <span class="copy-totals"><?php echo $availableCopies; ?> of <?php echo $totalCopies; ?> available</span>
                            </div>
                            <span class="copy-totals"><?php echo $totalCopies; ?> total copies</span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="catalog-empty">No books found. Try another search.</p>
        <?php endif; ?>
    </div>
</section>

<footer>
    <div class="logo" style="color:white">Grand<span>Library.</span></div>
    <p>© 2026 Grand Library</p>
</footer>

</body>
</html>
