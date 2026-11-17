<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

function report_add_error(string &$error, string $message): void
{
    $error = $error === '' ? $message : $error . ' ' . $message;
}

function report_search_value(string $key, int $maxLength = 200): string
{
    $value = get_value($key);

    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function report_status_filter(string $key, array $allowed, string $label, string &$error): string
{
    $value = get_value($key);

    if ($value === '') {
        return '';
    }

    if (!in_array($value, $allowed, true)) {
        report_add_error($error, 'Select a valid ' . $label . ' filter.');
        return '';
    }

    return $value;
}

function report_date_filter(string $key, string $label, string &$error): string
{
    $value = get_value($key);

    if ($value === '') {
        return '';
    }

    $parsed = DateTime::createFromFormat('!Y-m-d', $value);

    if (!$parsed || $parsed->format('Y-m-d') !== $value) {
        report_add_error($error, 'Enter a valid ' . $label . ' date.');
        return '';
    }

    return $value;
}

function report_date_range_check(string $from, string $to, string $label, string &$error): void
{
    if ($from !== '' && $to !== '' && $from > $to) {
        report_add_error($error, 'The start date for ' . $label . ' cannot be after the end date.');
    }
}

function report_load_rows(
    mysqli $conn,
    string $sql,
    string $types,
    array $params,
    string $label,
    string &$error
): array {
    $rows = [];
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log($label . ' preparation failed: ' . $conn->error);
        report_add_error($error, 'Some report data could not be loaded. Please try again later.');
        return $rows;
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        error_log($label . ' query failed: ' . $stmt->error);
        $stmt->close();
        report_add_error($error, 'Some report data could not be loaded. Please try again later.');
        return $rows;
    }

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();

    return $rows;
}

function report_stat_value(?array $summary, string $key): string
{
    return $summary !== null && isset($summary[$key]) ? number_format((int)$summary[$key]) : '&mdash;';
}

function report_date_label(?string $value): string
{
    return $value !== null && $value !== '' ? date('M j, Y', strtotime($value)) : '&mdash;';
}

function report_loan_state(array $loan): array
{
    if ($loan['returned_at'] !== null) {
        return ['Returned', 'availability-none'];
    }

    if ((string)$loan['due_date'] < date('Y-m-d')) {
        return ['Overdue', 'availability-unavailable'];
    }

    return ['Active', 'availability-available'];
}

$error = '';
$summary = null;
$summaryResult = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM books) AS total_titles,
        (SELECT COUNT(*) FROM book_copies) AS total_copies,
        (SELECT COUNT(*)
         FROM book_copies bc
         WHERE NOT EXISTS (
             SELECT 1
             FROM loans l
             WHERE l.copy_id = bc.id
               AND l.returned_at IS NULL
         )) AS available_copies,
        (SELECT COUNT(*) FROM loans WHERE returned_at IS NULL) AS active_loans,
        (SELECT COUNT(*) FROM loans WHERE returned_at IS NULL AND due_date < CURDATE()) AS overdue_loans,
        (SELECT COUNT(*) FROM members) AS total_members,
        (SELECT COUNT(*) FROM members WHERE status = 'Active') AS active_members,
        (SELECT COUNT(*) FROM members WHERE status = 'Inactive') AS inactive_members,
        (SELECT COUNT(*) FROM loans) AS total_loans,
        (SELECT COUNT(*) FROM loans WHERE returned_at IS NOT NULL) AS returned_loans,
        (SELECT COUNT(*) FROM authors) AS total_authors,
        (SELECT COUNT(*) FROM categories) AS total_categories
");

if ($summaryResult) {
    $summary = $summaryResult->fetch_assoc();
    $summaryResult->free();
} else {
    error_log('Reports summary query failed: ' . $conn->error);
    report_add_error($error, 'Overview statistics could not be loaded. Please try again later.');
}

$invSearch = report_search_value('inv_search');
$invStatus = report_status_filter('inv_status', ['available', 'unavailable'], 'inventory availability', $error);

$invConditions = [];
$invTypes = '';
$invParams = [];

if ($invSearch !== '') {
    $invConditions[] = '(b.title LIKE ? OR b.isbn LIKE ? OR a.name LIKE ? OR c.name LIKE ?)';
    $invLike = '%' . $invSearch . '%';
    $invTypes .= 'ssss';
    $invParams[] = $invLike;
    $invParams[] = $invLike;
    $invParams[] = $invLike;
    $invParams[] = $invLike;
}

$inventorySql = "
    SELECT
        b.id,
        b.title,
        b.isbn,
        a.name AS author_name,
        c.name AS category_name,
        b.publisher,
        b.publication_year,
        COUNT(bc.id) AS copy_count,
        COALESCE(SUM(CASE
            WHEN bc.id IS NOT NULL
             AND NOT EXISTS (
                 SELECT 1
                 FROM loans l
                 WHERE l.copy_id = bc.id
                   AND l.returned_at IS NULL
             )
            THEN 1 ELSE 0
        END), 0) AS available_copies
    FROM books b
    INNER JOIN authors a ON a.id = b.author_id
    INNER JOIN categories c ON c.id = b.category_id
    LEFT JOIN book_copies bc ON bc.book_id = b.id
";

if (count($invConditions) > 0) {
    $inventorySql .= ' WHERE ' . implode(' AND ', $invConditions);
}

$inventorySql .= ' GROUP BY b.id, b.title, b.isbn, a.name, c.name, b.publisher, b.publication_year';

if ($invStatus === 'available') {
    $inventorySql .= ' HAVING available_copies > 0';
} elseif ($invStatus === 'unavailable') {
    $inventorySql .= ' HAVING available_copies = 0';
}

$inventorySql .= ' ORDER BY b.title ASC, b.id ASC';
$inventory = report_load_rows($conn, $inventorySql, $invTypes, $invParams, 'Inventory report', $error);

$memSearch = report_search_value('mem_search');
$memStatus = report_status_filter('mem_status', ['Active', 'Inactive'], 'member status', $error);
$memFrom = report_date_filter('mem_from', 'membership start', $error);
$memTo = report_date_filter('mem_to', 'membership end', $error);
report_date_range_check($memFrom, $memTo, 'the member report', $error);

$memConditions = [];
$memTypes = '';
$memParams = [];

if ($memSearch !== '') {
    $memConditions[] = '(m.name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)';
    $memLike = '%' . $memSearch . '%';
    $memTypes .= 'sss';
    $memParams[] = $memLike;
    $memParams[] = $memLike;
    $memParams[] = $memLike;
}

if ($memStatus !== '') {
    $memConditions[] = 'm.status = ?';
    $memTypes .= 's';
    $memParams[] = $memStatus;
}

if ($memFrom !== '') {
    $memConditions[] = 'm.membership_date >= ?';
    $memTypes .= 's';
    $memParams[] = $memFrom;
}

if ($memTo !== '') {
    $memConditions[] = 'm.membership_date <= ?';
    $memTypes .= 's';
    $memParams[] = $memTo;
}

$memberSql = "
    SELECT
        m.id,
        m.name,
        m.email,
        m.phone,
        m.membership_date,
        m.status,
        COUNT(l.id) AS total_loans,
        COALESCE(SUM(CASE WHEN l.returned_at IS NULL THEN 1 ELSE 0 END), 0) AS active_loans,
        COALESCE(SUM(CASE
            WHEN l.returned_at IS NULL AND l.due_date < CURDATE()
            THEN 1 ELSE 0
        END), 0) AS overdue_loans
    FROM members m
    LEFT JOIN loans l ON l.member_id = m.id
";

if (count($memConditions) > 0) {
    $memberSql .= ' WHERE ' . implode(' AND ', $memConditions);
}

$memberSql .= ' GROUP BY m.id, m.name, m.email, m.phone, m.membership_date, m.status, m.created_at';
$memberSql .= ' ORDER BY m.name ASC, m.id ASC';
$memberReport = report_load_rows($conn, $memberSql, $memTypes, $memParams, 'Member report', $error);

$circSearch = report_search_value('circ_search');
$circStatus = report_status_filter('circ_status', ['active', 'overdue', 'returned'], 'circulation status', $error);
$circFrom = report_date_filter('circ_from', 'issue start', $error);
$circTo = report_date_filter('circ_to', 'issue end', $error);
report_date_range_check($circFrom, $circTo, 'the circulation report', $error);

$circConditions = [];
$circTypes = '';
$circParams = [];

if ($circSearch !== '') {
    $circConditions[] = '(m.name LIKE ? OR m.email LIKE ? OR b.title LIKE ? OR b.isbn LIKE ? OR bc.barcode LIKE ?)';
    $circLike = '%' . $circSearch . '%';
    $circTypes .= 'sssss';
    $circParams[] = $circLike;
    $circParams[] = $circLike;
    $circParams[] = $circLike;
    $circParams[] = $circLike;
    $circParams[] = $circLike;
}

if ($circStatus === 'active') {
    $circConditions[] = 'l.returned_at IS NULL AND l.due_date >= CURDATE()';
} elseif ($circStatus === 'overdue') {
    $circConditions[] = 'l.returned_at IS NULL AND l.due_date < CURDATE()';
} elseif ($circStatus === 'returned') {
    $circConditions[] = 'l.returned_at IS NOT NULL';
}

if ($circFrom !== '') {
    $circConditions[] = 'l.issue_date >= ?';
    $circTypes .= 's';
    $circParams[] = $circFrom;
}

if ($circTo !== '') {
    $circConditions[] = 'l.issue_date <= ?';
    $circTypes .= 's';
    $circParams[] = $circTo;
}

$circulationSql = "
    SELECT
        l.id,
        l.issue_date,
        l.due_date,
        l.returned_at,
        m.name AS member_name,
        m.email AS member_email,
        b.title,
        b.isbn,
        a.name AS author_name,
        bc.barcode,
        u.name AS issued_by_name
    FROM loans l
    INNER JOIN members m ON m.id = l.member_id
    INNER JOIN book_copies bc ON bc.id = l.copy_id
    INNER JOIN books b ON b.id = bc.book_id
    INNER JOIN authors a ON a.id = b.author_id
    LEFT JOIN users u ON u.id = l.issued_by
";

if (count($circConditions) > 0) {
    $circulationSql .= ' WHERE ' . implode(' AND ', $circConditions);
}

$circulationSql .= ' ORDER BY l.issue_date DESC, l.id DESC';
$circulationReport = report_load_rows($conn, $circulationSql, $circTypes, $circParams, 'Circulation report', $error);

$categoryReport = report_load_rows(
    $conn,
    "
    SELECT
        c.id,
        c.name,
        COUNT(DISTINCT b.id) AS title_count,
        COUNT(bc.id) AS copy_count,
        COALESCE(SUM(CASE
            WHEN bc.id IS NOT NULL
             AND NOT EXISTS (
                 SELECT 1
                 FROM loans l
                 WHERE l.copy_id = bc.id
                   AND l.returned_at IS NULL
             )
            THEN 1 ELSE 0
        END), 0) AS available_copies
    FROM categories c
    LEFT JOIN books b ON b.category_id = c.id
    LEFT JOIN book_copies bc ON bc.book_id = b.id
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
    ",
    '',
    [],
    'Category summary',
    $error
);

$topBooks = report_load_rows(
    $conn,
    "
    SELECT
        b.id,
        b.title,
        b.isbn,
        a.name AS author_name,
        COUNT(l.id) AS loan_count
    FROM loans l
    INNER JOIN book_copies bc ON bc.id = l.copy_id
    INNER JOIN books b ON b.id = bc.book_id
    LEFT JOIN authors a ON a.id = b.author_id
    GROUP BY b.id, b.title, b.isbn, a.name
    ORDER BY loan_count DESC, b.title ASC
    LIMIT 5
    ",
    '',
    [],
    'Most borrowed titles',
    $error
);

$pageTitle = 'Reports';
$activeNav = 'reports';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Library Reporting</div>
        <h1>Reports</h1>
        <p>Read-only inventory, member and circulation reporting with filters and print support.</p>
    </div>
    <div class="page-actions report-no-print">
        <a class="btn btn-secondary" href="<?php echo e(app_url('reports/index.php')); ?>">Reset All Filters</a>
        <button type="button" class="btn btn-primary" data-print-report>Print / Save PDF</button>
    </div>
</section>

<div class="report-print-title report-print-only">
    <h1>Grand Library Reports</h1>
    <p>Generated <?php echo e(date('M j, Y g:i A')); ?></p>
</div>

<?php if ($summary === null): ?>
    <div class="alert alert-error" role="alert">Report summary data could not be loaded. Please refresh or try again later.</div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="stat-grid report-stats" aria-label="Library report overview">
    <article class="stat-card">
        <div class="stat-label">Book Titles</div>
        <div class="stat-value"><?php echo report_stat_value($summary, 'total_titles'); ?></div>
        <div class="stat-note">Catalogued titles</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Physical Copies</div>
        <div class="stat-value"><?php echo report_stat_value($summary, 'total_copies'); ?></div>
        <div class="stat-note">All copy records</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Available Copies</div>
        <div class="stat-value"><?php echo report_stat_value($summary, 'available_copies'); ?></div>
        <div class="stat-note">Not currently issued</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Active Loans</div>
        <div class="stat-value"><?php echo report_stat_value($summary, 'active_loans'); ?></div>
        <div class="stat-note">Currently issued</div>
    </article>
    <article class="stat-card<?php echo $summary !== null && (int)$summary['overdue_loans'] > 0 ? ' danger' : ''; ?>">
        <div class="stat-label">Overdue Loans</div>
        <div class="stat-value"><?php echo report_stat_value($summary, 'overdue_loans'); ?></div>
        <div class="stat-note">Past their due date</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Member Records</div>
        <div class="stat-value"><?php echo report_stat_value($summary, 'total_members'); ?></div>
        <div class="stat-note">Registered members</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Active Members</div>
        <div class="stat-value"><?php echo report_stat_value($summary, 'active_members'); ?></div>
        <div class="stat-note">Eligible to borrow</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Inactive Members</div>
        <div class="stat-value"><?php echo report_stat_value($summary, 'inactive_members'); ?></div>
        <div class="stat-note">Borrowing disabled</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Total Loans</div>
        <div class="stat-value"><?php echo report_stat_value($summary, 'total_loans'); ?></div>
        <div class="stat-note">All circulation records</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Returned Loans</div>
        <div class="stat-value"><?php echo report_stat_value($summary, 'returned_loans'); ?></div>
        <div class="stat-note">Completed circulation</div>
    </article>
</section>

<section class="panel report-panel">
    <div class="panel-head">
        <div>
            <h2>Collection by Category</h2>
            <p class="panel-intro">Title and copy totals for every category, with current availability.</p>
        </div>
        <span class="count-pill"><?php echo report_stat_value($summary, 'total_authors'); ?> authors &middot; <?php echo report_stat_value($summary, 'total_categories'); ?> categories</span>
    </div>

    <div class="table-wrap">
        <table class="data-table" aria-label="Collection by category">
            <thead>
                <tr>
                    <th scope="col">Category</th>
                    <th scope="col">Titles</th>
                    <th scope="col">Copies</th>
                    <th scope="col">Available Copies</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($categoryReport) === 0): ?>
                    <tr>
                        <td colspan="4" class="empty-cell">No category data is available.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categoryReport as $category): ?>
                        <tr>
                            <td><span class="book-title"><?php echo e($category['name']); ?></span></td>
                            <td><?php echo number_format((int)$category['title_count']); ?></td>
                            <td><?php echo number_format((int)$category['copy_count']); ?></td>
                            <td>
                                <span class="copy-count<?php echo (int)$category['available_copies'] === 0 ? ' zero' : ''; ?>">
                                    <?php echo number_format((int)$category['available_copies']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel report-panel">
    <div class="panel-head">
        <div>
            <h2>Most Borrowed Titles</h2>
            <p class="panel-intro">The five titles with the highest number of loan records.</p>
        </div>
        <span class="count-pill"><?php echo number_format(count($topBooks)); ?> shown</span>
    </div>

    <div class="table-wrap">
        <table class="data-table" aria-label="Most borrowed titles">
            <thead>
                <tr>
                    <th scope="col">Book</th>
                    <th scope="col">Author</th>
                    <th scope="col">ISBN</th>
                    <th scope="col">Loans</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($topBooks) === 0): ?>
                    <tr>
                        <td colspan="4" class="empty-cell">No loan activity has been recorded yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($topBooks as $book): ?>
                        <tr>
                            <td><span class="book-title"><?php echo e($book['title']); ?></span></td>
                            <td><?php echo e($book['author_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo e($book['isbn']); ?></td>
                            <td><span class="count-pill"><?php echo number_format((int)$book['loan_count']); ?> loans</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel report-panel" id="inventory-report">
    <div class="panel-head">
        <div>
            <h2>Inventory Report</h2>
            <p class="panel-intro">Book-level copy totals and availability calculated from active loans.</p>
        </div>
        <span class="count-pill"><?php echo number_format(count($inventory)); ?> shown</span>
    </div>

    <form method="get" action="<?php echo e(app_url('reports/index.php')); ?>" class="staff-filter report-no-print">
        <div class="filter-field">
            <label for="inv_search">Search Inventory</label>
            <input class="form-control" type="search" id="inv_search" name="inv_search"
                   value="<?php echo e($invSearch); ?>" maxlength="200"
                   placeholder="Title, ISBN, author or category">
        </div>
        <div class="filter-field">
            <label for="inv_status">Availability</label>
            <select class="form-control" id="inv_status" name="inv_status">
                <option value="">All availability</option>
                <option value="available" <?php echo $invStatus === 'available' ? 'selected' : ''; ?>>Available copies</option>
                <option value="unavailable" <?php echo $invStatus === 'unavailable' ? 'selected' : ''; ?>>No copies available</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a class="btn btn-secondary" href="<?php echo e(app_url('reports/index.php')); ?>">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" aria-label="Inventory report">
            <thead>
                <tr>
                    <th scope="col">Book</th>
                    <th scope="col">ISBN</th>
                    <th scope="col">Author</th>
                    <th scope="col">Category</th>
                    <th scope="col">Publisher</th>
                    <th scope="col">Year</th>
                    <th scope="col">Copies</th>
                    <th scope="col">Available</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($inventory) === 0): ?>
                    <tr>
                        <td colspan="8" class="empty-cell">No books match the selected inventory filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventory as $book): ?>
                        <?php $availableCopies = (int)$book['available_copies']; ?>
                        <tr>
                            <td><span class="book-title"><?php echo e($book['title']); ?></span></td>
                            <td><?php echo e($book['isbn']); ?></td>
                            <td><?php echo e($book['author_name']); ?></td>
                            <td><?php echo e($book['category_name']); ?></td>
                            <td>
                                <?php echo $book['publisher'] !== null && $book['publisher'] !== '' ? e($book['publisher']) : '&mdash;'; ?>
                            </td>
                            <td>
                                <?php echo $book['publication_year'] !== null ? e($book['publication_year']) : '&mdash;'; ?>
                            </td>
                            <td><?php echo number_format((int)$book['copy_count']); ?></td>
                            <td>
                                <span class="availability-badge <?php echo $availableCopies > 0 ? 'availability-available' : 'availability-unavailable'; ?>">
                                    <?php echo $availableCopies > 0 ? number_format($availableCopies) . ' available' : 'Unavailable'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel report-panel" id="member-report">
    <div class="panel-head">
        <div>
            <h2>Member Report</h2>
            <p class="panel-intro">Membership details, borrowing totals, active loans and overdue counts.</p>
        </div>
        <span class="count-pill"><?php echo number_format(count($memberReport)); ?> shown</span>
    </div>

    <form method="get" action="<?php echo e(app_url('reports/index.php')); ?>" class="staff-filter report-no-print">
        <div class="filter-field">
            <label for="mem_search">Search Members</label>
            <input class="form-control" type="search" id="mem_search" name="mem_search"
                   value="<?php echo e($memSearch); ?>" maxlength="200"
                   placeholder="Name, email or phone">
        </div>
        <div class="filter-field">
            <label for="mem_status">Status</label>
            <select class="form-control" id="mem_status" name="mem_status">
                <option value="">All statuses</option>
                <option value="Active" <?php echo $memStatus === 'Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo $memStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="mem_from">Joined From</label>
            <input class="form-control" type="date" id="mem_from" name="mem_from" value="<?php echo e($memFrom); ?>">
        </div>
        <div class="filter-field">
            <label for="mem_to">Joined To</label>
            <input class="form-control" type="date" id="mem_to" name="mem_to" value="<?php echo e($memTo); ?>">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a class="btn btn-secondary" href="<?php echo e(app_url('reports/index.php')); ?>">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" aria-label="Member report">
            <thead>
                <tr>
                    <th scope="col">Member</th>
                    <th scope="col">Contact</th>
                    <th scope="col">Membership Date</th>
                    <th scope="col">Status</th>
                    <th scope="col">Total Loans</th>
                    <th scope="col">Active Loans</th>
                    <th scope="col">Overdue</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($memberReport) === 0): ?>
                    <tr>
                        <td colspan="7" class="empty-cell">No members match the selected report filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($memberReport as $member): ?>
                        <?php
                        $isActive = strcasecmp((string)$member['status'], 'Active') === 0;
                        $overdueLoans = (int)$member['overdue_loans'];
                        ?>
                        <tr>
                            <td>
                                <span class="book-title"><?php echo e($member['name']); ?></span>
                                <span class="book-subtext">Member ID #<?php echo (int)$member['id']; ?></span>
                            </td>
                            <td>
                                <span><?php echo e($member['email']); ?></span>
                                <span class="book-subtext">
                                    <?php echo $member['phone'] !== null && $member['phone'] !== '' ? e($member['phone']) : '&mdash;'; ?>
                                </span>
                            </td>
                            <td><?php echo report_date_label($member['membership_date']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo e($member['status']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format((int)$member['total_loans']); ?></td>
                            <td><?php echo number_format((int)$member['active_loans']); ?></td>
                            <td>
                                <span class="availability-badge <?php echo $overdueLoans > 0 ? 'availability-unavailable' : 'availability-none'; ?>">
                                    <?php echo number_format($overdueLoans); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel report-panel" id="circulation-report">
    <div class="panel-head">
        <div>
            <h2>Circulation Report</h2>
            <p class="panel-intro">Loan history with member, book, copy, status and issue-date filters.</p>
        </div>
        <span class="count-pill"><?php echo number_format(count($circulationReport)); ?> shown</span>
    </div>

    <form method="get" action="<?php echo e(app_url('reports/index.php')); ?>" class="staff-filter report-no-print">
        <div class="filter-field">
            <label for="circ_search">Search Loans</label>
            <input class="form-control" type="search" id="circ_search" name="circ_search"
                   value="<?php echo e($circSearch); ?>" maxlength="200"
                   placeholder="Member, title, ISBN or barcode">
        </div>
        <div class="filter-field">
            <label for="circ_status">Loan Status</label>
            <select class="form-control" id="circ_status" name="circ_status">
                <option value="">All statuses</option>
                <option value="active" <?php echo $circStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="overdue" <?php echo $circStatus === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                <option value="returned" <?php echo $circStatus === 'returned' ? 'selected' : ''; ?>>Returned</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="circ_from">Issued From</label>
            <input class="form-control" type="date" id="circ_from" name="circ_from" value="<?php echo e($circFrom); ?>">
        </div>
        <div class="filter-field">
            <label for="circ_to">Issued To</label>
            <input class="form-control" type="date" id="circ_to" name="circ_to" value="<?php echo e($circTo); ?>">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a class="btn btn-secondary" href="<?php echo e(app_url('reports/index.php')); ?>">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" aria-label="Circulation report">
            <thead>
                <tr>
                    <th scope="col">Book</th>
                    <th scope="col">Copy</th>
                    <th scope="col">Member</th>
                    <th scope="col">Issued</th>
                    <th scope="col">Due</th>
                    <th scope="col">Returned</th>
                    <th scope="col">Status</th>
                    <th scope="col">Issued By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($circulationReport) === 0): ?>
                    <tr>
                        <td colspan="8" class="empty-cell">No loans match the selected circulation filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($circulationReport as $loan): ?>
                        <?php
                        [$loanStatus, $loanClass] = report_loan_state($loan);
                        ?>
                        <tr>
                            <td>
                                <span class="book-title"><?php echo e($loan['title']); ?></span>
                                <span class="book-subtext">ISBN <?php echo e($loan['isbn']); ?></span>
                            </td>
                            <td><span class="copy-barcode"><?php echo e($loan['barcode']); ?></span></td>
                            <td>
                                <span><?php echo e($loan['member_name']); ?></span>
                                <span class="book-subtext"><?php echo e($loan['member_email']); ?></span>
                            </td>
                            <td><?php echo report_date_label($loan['issue_date']); ?></td>
                            <td><?php echo report_date_label($loan['due_date']); ?></td>
                            <td><?php echo report_date_label($loan['returned_at']); ?></td>
                            <td>
                                <span class="availability-badge <?php echo e($loanClass); ?>">
                                    <?php echo e($loanStatus); ?>
                                </span>
                            </td>
                            <td><?php echo e($loan['issued_by_name'] ?? 'Unknown'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
