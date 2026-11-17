<?php
require_once __DIR__ . '/includes/auth.php';

$staff = require_staff();

$statsResult = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM books) AS total_books,
        (SELECT COUNT(*) FROM book_copies) AS total_copies,
        (SELECT COUNT(*) FROM members) AS total_members,
        (SELECT COUNT(*) FROM loans WHERE returned_at IS NULL) AS active_loans,
        (SELECT COUNT(*) FROM loans WHERE returned_at IS NULL AND due_date < CURDATE()) AS overdue_loans,
        (SELECT COUNT(*)
         FROM book_copies bc
         WHERE NOT EXISTS (
             SELECT 1
             FROM loans l
             WHERE l.copy_id = bc.id
               AND l.returned_at IS NULL
         )) AS available_copies
");

$stats = $statsResult ? $statsResult->fetch_assoc() : null;

if ($statsResult) {
    $statsResult->free();
}

$recentLoans = [];
$recentError = false;
$recentResult = $conn->query("
    SELECT
        l.id,
        l.issue_date,
        l.due_date,
        l.returned_at,
        m.name AS member_name,
        b.title,
        bc.barcode
    FROM loans l
    INNER JOIN members m ON m.id = l.member_id
    INNER JOIN book_copies bc ON bc.id = l.copy_id
    INNER JOIN books b ON b.id = bc.book_id
    ORDER BY l.issue_date DESC, l.id DESC
    LIMIT 5
");

if ($recentResult) {
    while ($row = $recentResult->fetch_assoc()) {
        $recentLoans[] = $row;
    }

    $recentResult->free();
} else {
    error_log('Dashboard recent loans query failed: ' . $conn->error);
    $recentError = true;
}

function stat_value(?array $stats, string $key): string
{
    return $stats !== null && isset($stats[$key]) ? number_format((int)$stats[$key]) : '&mdash;';
}

function dashboard_loan_state(array $loan): array
{
    if ($loan['returned_at'] !== null) {
        return ['Returned', 'availability-none'];
    }

    if ((string)$loan['due_date'] < date('Y-m-d')) {
        return ['Overdue', 'availability-unavailable'];
    }

    return ['Active', 'availability-available'];
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Staff Workspace</div>
        <h1>Dashboard</h1>
        <p>Welcome back, <strong><?php echo e($staff['name']); ?></strong>.
            <span class="role-badge"><?php echo e($staff['role']); ?></span>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?php echo e(app_url('catalog.php')); ?>">Browse Catalog</a>
        <a class="btn btn-secondary" href="<?php echo e(app_url('reports/index.php')); ?>">Reports</a>
        <a class="btn btn-secondary" href="<?php echo e(app_url('loans/index.php')); ?>">Loans</a>
        <a class="btn btn-primary" href="<?php echo e(app_url('loans/issue.php')); ?>">Issue Book</a>
    </div>
</section>

<?php if ($stats === null): ?>
    <div class="alert alert-error" role="alert">
        Live dashboard statistics could not be loaded. Please refresh or try again shortly.
    </div>
<?php endif; ?>

<section class="stat-grid" aria-label="Library statistics">
    <article class="stat-card">
        <div class="stat-label">Total Book Titles</div>
        <div class="stat-value"><?php echo stat_value($stats, 'total_books'); ?></div>
        <div class="stat-note">Catalogued titles</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Physical Copies</div>
        <div class="stat-value"><?php echo stat_value($stats, 'total_copies'); ?></div>
        <div class="stat-note"><?php echo stat_value($stats, 'available_copies'); ?> available now</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Members</div>
        <div class="stat-value"><?php echo stat_value($stats, 'total_members'); ?></div>
        <div class="stat-note">Registered library members</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Active Loans</div>
        <div class="stat-value"><?php echo stat_value($stats, 'active_loans'); ?></div>
        <div class="stat-note">Copies currently issued</div>
    </article>
    <article class="stat-card<?php echo $stats !== null && (int)$stats['overdue_loans'] > 0 ? ' danger' : ''; ?>">
        <div class="stat-label">Overdue Loans</div>
        <div class="stat-value"><?php echo stat_value($stats, 'overdue_loans'); ?></div>
        <div class="stat-note">Past their due date</div>
    </article>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Recent Loans</h2>
            <p class="panel-intro">The five most recent circulation records.</p>
        </div>
        <a class="btn btn-secondary btn-small" href="<?php echo e(app_url('loans/index.php')); ?>">View All Loans</a>
    </div>

    <?php if ($recentError): ?>
        <div class="alert alert-error" role="alert">Recent loan activity could not be loaded.</div>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="data-table" aria-label="Recent loans">
            <thead>
                <tr>
                    <th scope="col">Book</th>
                    <th scope="col">Member</th>
                    <th scope="col">Issue Date</th>
                    <th scope="col">Due Date</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recentLoans) === 0): ?>
                    <tr>
                        <td colspan="5" class="empty-cell">No circulation activity yet. Issue a book to get started.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentLoans as $loan): ?>
                        <?php [$statusLabel, $statusClass] = dashboard_loan_state($loan); ?>
                        <tr>
                            <td>
                                <span class="book-title"><?php echo e($loan['title']); ?></span>
                                <span class="book-subtext"><?php echo e($loan['barcode']); ?></span>
                            </td>
                            <td><?php echo e($loan['member_name']); ?></td>
                            <td><?php echo e(date('M j, Y', strtotime((string)$loan['issue_date']))); ?></td>
                            <td><?php echo e(date('M j, Y', strtotime((string)$loan['due_date']))); ?></td>
                            <td>
                                <span class="availability-badge <?php echo e($statusClass); ?>">
                                    <?php echo e($statusLabel); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Library Modules</h2>
    <p class="panel-intro">Secure staff access, live statistics, catalog management, circulation, member records, reporting and administrator-only staff account management are available.</p>

    <div class="module-grid">
        <article class="module-card">
            <h3>Book Catalog</h3>
            <p>Browse the public catalog with search, category and availability filters.</p>
            <span class="status-pill status-ready">Available</span>
        </article>
        <article class="module-card">
            <h3>Books &amp; Categories</h3>
            <p>Create and maintain book titles, publication details and categories.</p>
            <span class="status-pill status-ready">Available</span>
        </article>
        <article class="module-card">
            <h3><a href="<?php echo e(app_url('authors/index.php')); ?>">Authors</a></h3>
            <p>Create, search and maintain author records linked to book titles.</p>
            <span class="status-pill status-ready">Available</span>
        </article>
        <?php if (is_admin()): ?>
            <article class="module-card">
                <h3>Staff Management</h3>
                <p>Create librarian accounts, update staff details and control access.</p>
                <span class="status-pill status-ready">Available</span>
            </article>
        <?php endif; ?>
        <article class="module-card">
            <h3>Physical Copies</h3>
            <p>Manage individual copy records, unique barcodes and item availability.</p>
            <span class="status-pill status-ready">Available</span>
        </article>
        <article class="module-card">
            <h3>Members</h3>
            <p>Member records, contact details, membership status and borrowing history.</p>
            <span class="status-pill status-ready">Available</span>
        </article>
        <article class="module-card">
            <h3>Circulation</h3>
            <p>Issue books, process returns, track overdue loans and review transaction history.</p>
            <span class="status-pill status-ready">Available</span>
        </article>
        <article class="module-card">
            <h3><a href="<?php echo e(app_url('reports/index.php')); ?>">Reports</a></h3>
            <p>Inventory, member and circulation reporting with filters and print support.</p>
            <span class="status-pill status-ready">Available</span>
        </article>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
