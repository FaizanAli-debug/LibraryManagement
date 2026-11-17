<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

function loan_register_state(array $loan): array
{
    if ($loan['returned_at'] !== null) {
        return ['Returned', 'availability-none'];
    }

    if ((string)$loan['due_date'] < date('Y-m-d')) {
        return ['Overdue', 'availability-unavailable'];
    }

    return ['Active', 'availability-available'];
}

$noticeMap = [
    'issued' => 'Book issued successfully.',
    'returned' => 'Book returned successfully.',
];

$errorMap = [
    'invalid' => 'The request was invalid. Please try again.',
    'notfound' => 'The requested loan could not be found.',
    'returned' => 'This loan has already been returned.',
    'save' => 'The change could not be saved. Please try again.',
];

$notice = $noticeMap[get_value('notice')] ?? '';
$error = $errorMap[get_value('error')] ?? '';
$search = get_value('search');
$statusInput = get_value('status');

if (function_exists('mb_substr')) {
    $search = mb_substr($search, 0, 200);
} else {
    $search = substr($search, 0, 200);
}

$statusFilter = '';

if ($statusInput !== '') {
    if (in_array($statusInput, ['active', 'overdue', 'returned'], true)) {
        $statusFilter = $statusInput;
    } else {
        $error = 'Select a valid loan status filter.';
    }
}

$conditions = [];
$types = '';
$params = [];

if ($search !== '') {
    $conditions[] = '(m.name LIKE ? OR m.email LIKE ? OR b.title LIKE ? OR b.isbn LIKE ? OR bc.barcode LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($statusFilter === 'active') {
    $conditions[] = 'l.returned_at IS NULL AND l.due_date >= CURDATE()';
} elseif ($statusFilter === 'overdue') {
    $conditions[] = 'l.returned_at IS NULL AND l.due_date < CURDATE()';
} elseif ($statusFilter === 'returned') {
    $conditions[] = 'l.returned_at IS NOT NULL';
}

$sql = "
    SELECT
        l.id,
        l.issue_date,
        l.due_date,
        l.returned_at,
        m.id AS member_id,
        m.name AS member_name,
        m.email AS member_email,
        b.title,
        b.isbn,
        bc.barcode,
        u.name AS issued_by_name
    FROM loans l
    INNER JOIN members m ON m.id = l.member_id
    INNER JOIN book_copies bc ON bc.id = l.copy_id
    INNER JOIN books b ON b.id = bc.book_id
    LEFT JOIN users u ON u.id = l.issued_by
";

if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY l.issue_date DESC, l.id DESC';

$loans = [];
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log('Loan register preparation failed: ' . $conn->error);
    $error = 'Loans could not be loaded right now. Please try again later.';
} else {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $loans[] = $row;
        }
    } else {
        error_log('Loan register query failed: ' . $stmt->error);
        $error = 'Loans could not be loaded right now. Please try again later.';
    }

    $stmt->close();
}

$totalShown = count($loans);
$activeShown = 0;
$overdueShown = 0;
$returnedShown = 0;

foreach ($loans as $loanRow) {
    if ($loanRow['returned_at'] !== null) {
        $returnedShown++;
    } elseif ((string)$loanRow['due_date'] < date('Y-m-d')) {
        $overdueShown++;
    } else {
        $activeShown++;
    }
}

$scopeLabel = ($search !== '' || $statusFilter !== '') ? 'Current results' : 'All loan records';
$activeNav = $statusFilter === 'active' ? 'returns' : 'loans';

$pageTitle = 'Loans';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Circulation</div>
        <h1>Loan Register</h1>
        <p>Review active, overdue and returned loans, process returns and search circulation history.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?php echo e(app_url('loans/index.php?status=active')); ?>">Active Loans</a>
        <a class="btn btn-primary" href="<?php echo e(app_url('loans/issue.php')); ?>">Issue Book</a>
    </div>
</section>

<?php if ($notice !== ''): ?>
    <div class="alert alert-success" role="status"><?php echo e($notice); ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="stat-grid loan-stats" aria-label="Loan statistics">
    <article class="stat-card">
        <div class="stat-label">Total Loans</div>
        <div class="stat-value"><?php echo number_format($totalShown); ?></div>
        <div class="stat-note"><?php echo e($scopeLabel); ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Active</div>
        <div class="stat-value"><?php echo number_format($activeShown); ?></div>
        <div class="stat-note">Currently on loan</div>
    </article>
    <article class="stat-card<?php echo $overdueShown > 0 ? ' danger' : ''; ?>">
        <div class="stat-label">Overdue</div>
        <div class="stat-value"><?php echo number_format($overdueShown); ?></div>
        <div class="stat-note">Past their due date</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Returned</div>
        <div class="stat-value"><?php echo number_format($returnedShown); ?></div>
        <div class="stat-note">Completed loans</div>
    </article>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Circulation History</h2>
            <p class="panel-intro">Loan records are permanent and are never deleted.</p>
        </div>
        <span class="count-pill"><?php echo number_format($totalShown); ?> shown</span>
    </div>

    <form method="get" action="<?php echo e(app_url('loans/index.php')); ?>" class="staff-filter">
        <div class="filter-field">
            <label for="search">Search Loans</label>
            <input class="form-control" type="search" id="search" name="search"
                   value="<?php echo e($search); ?>" maxlength="200"
                   placeholder="Member, book, ISBN or barcode">
        </div>

        <div class="filter-field">
            <label for="status">Status</label>
            <select class="form-control" id="status" name="status">
                <option value="">All statuses</option>
                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="overdue" <?php echo $statusFilter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                <option value="returned" <?php echo $statusFilter === 'returned' ? 'selected' : ''; ?>>Returned</option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Search</button>
            <a class="btn btn-secondary" href="<?php echo e(app_url('loans/index.php')); ?>">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" aria-label="Loan records">
            <thead>
                <tr>
                    <th scope="col">Member</th>
                    <th scope="col">Book</th>
                    <th scope="col">Issue Date</th>
                    <th scope="col">Due Date</th>
                    <th scope="col">Returned</th>
                    <th scope="col">Issued By</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($totalShown === 0): ?>
                    <tr>
                        <td colspan="8" class="empty-cell">No loan records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($loans as $loan): ?>
                        <?php
                        [$statusLabel, $statusClass] = loan_register_state($loan);
                        $returnConfirm = 'Mark "' . $loan['title'] . '" (' . $loan['barcode']
                            . ') as returned today?';
                        ?>
                        <tr>
                            <td>
                                <span class="book-title"><?php echo e($loan['member_name']); ?></span>
                                <span class="book-subtext"><?php echo e($loan['member_email']); ?></span>
                            </td>
                            <td>
                                <span class="book-title"><?php echo e($loan['title']); ?></span>
                                <span class="book-subtext">ISBN <?php echo e($loan['isbn']); ?></span>
                            </td>
                            <td><?php echo e(date('M j, Y', strtotime((string)$loan['issue_date']))); ?></td>
                            <td><?php echo e(date('M j, Y', strtotime((string)$loan['due_date']))); ?></td>
                            <td>
                                <?php echo $loan['returned_at'] !== null
                                    ? e(date('M j, Y', strtotime((string)$loan['returned_at'])))
                                    : '&mdash;'; ?>
                            </td>
                            <td><?php echo e($loan['issued_by_name'] ?? 'Unknown'); ?></td>
                            <td>
                                <div class="detail-badges">
                                    <span class="availability-badge <?php echo e($statusClass); ?>">
                                        <?php echo e($statusLabel); ?>
                                    </span>
                                    <span class="copy-barcode"><?php echo e($loan['barcode']); ?></span>
                                </div>
                            </td>
                            <td class="table-actions">
                                <?php if ($loan['returned_at'] === null): ?>
                                    <form method="post" action="<?php echo e(app_url('loans/return.php')); ?>"
                                          class="inline-form" data-confirm="<?php echo e($returnConfirm); ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$loan['id']; ?>">
                                        <button type="submit" class="btn btn-primary btn-small">Return</button>
                                    </form>
                                <?php else: ?>
                                    <span class="protected-label">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
