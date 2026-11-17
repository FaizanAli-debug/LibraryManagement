
<?php
require_once "config/db.php";

$books = $conn->query("
    SELECT b.*, a.name AS author_name
    FROM books b
    LEFT JOIN authors a ON b.author_id = a.id
    LIMIT 4
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grand Library | Discover Knowledge</title>
    <link rel="stylesheet" href="assets/tera.css">
</head>
<body>

<nav class="navbar">
    <div class="logo">Grand<span>Library.</span></div>
    <div class="nav-links">
        <a href="#">Home</a>
        <a href="#books">Books</a>
        <a href="#about">About</a>
        <a href="login.php" class="nav-btn">Login</a>
    </div>
</nav>

<section class="hero">
    <div class="hero-content">
        <div class="eyebrow">Welcome to Grand Library</div>
        <h1>Explore the World Through <span>Books.</span></h1>
        <p>Discover timeless stories, expand your knowledge and find your next favourite book in our digital library.</p>
        <a href="#books" class="hero-btn">Explore Collection →</a>
    </div>

    <img class="hero-image"
         src="https://images.unsplash.com/photo-1507842217343-583bb7270b66?w=900"
         alt="Beautiful library">
</section>

<section class="stats">
    <?php
    $bookCount = $conn->query("SELECT COUNT(*) AS total FROM books")->fetch_assoc()['total'];
    $memberCount = $conn->query("SELECT COUNT(*) AS total FROM members")->fetch_assoc()['total'];
    $loanCount = $conn->query("SELECT COUNT(*) AS total FROM loans")->fetch_assoc()['total'];
    ?>
    <div class="stat">
        <h2><?php echo $bookCount; ?>+</h2>
        <p>Books Collection</p>
    </div>
    <div class="stat">
        <h2><?php echo $memberCount; ?>+</h2>
        <p>Registered Members</p>
    </div>
    <div class="stat">
        <h2><?php echo $loanCount; ?></h2>
        <p>Loan Records</p>
    </div>
</section>

<section class="section" id="books">
    <div class="section-heading">
        <div class="eyebrow">Our Collection</div>
        <h2>Featured Books</h2>
    </div>

    <div class="book-grid">
        <?php if ($books && $books->num_rows > 0): ?>
            <?php while ($book = $books->fetch_assoc()): ?>
                <div class="book-card">
                    <img class="book-cover"
                         src="https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=500"
                         alt="Book cover">
                    <div class="book-info">
                        <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                        <p><?php echo htmlspecialchars($book['author_name'] ?? 'Unknown Author'); ?></p>
                        <span class="book-tag">Library Collection</span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No books available yet.</p>
        <?php endif; ?>
    </div>
</section>

<section class="section about" id="about">
    <div>
        <div class="eyebrow">Our Story</div>
        <h2>A Place Where Knowledge Lives.</h2>
        <p>Our library brings readers and knowledge together. Explore our collection, discover new authors and enjoy a better reading experience.</p>
    </div>
    <img class="about-image"
         src="https://images.unsplash.com/photo-1521587760476-6c12a4b040da?w=900"
         alt="Library interior">
</section>

<footer>
    <div class="logo" style="color:white">Grand<span>Library.</span></div>
    <p>© 2026 Grand Library. All rights reserved.</p>
</footer>

</body>
</html>
