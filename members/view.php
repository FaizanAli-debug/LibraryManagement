<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$noticeMap = [
    'status' => 'Member status updated successfully.',
];

$notice = $noticeMap[get_value('notice')] ?? '';
$idInput = get_value('id');

if (!ctype_digit($idInput) || (int)$idInput <= 0) {
    redirect(app_url('members/index.php?error=invalid'));
}

$memberId = (int)$idInput;
$lookup = $conn->prepare("
    SELECT
        m.id,
        m.name,
        m.email,
        m.phone,
        m.address,
        m.membership_date,
        m.status,
        m.created_at,
        (SELECT COUNT(*) FROM loans l WHERE l.member_id = m.id) AS total_loans,
        (SELECT COUNT(*) FROM loans l WHERE l.member_id = m.id AND l.returned_at IS NULL) AS active_loans,
        (SELECT COUNT(*) FROM loans l WHERE l.member_id = m.id AND l.returned_at IS NOT NULL) AS returned_loans,
        (SELECT COUNT(*)
         FROM loans l
         WHERE l.member_id = m.id
           AND l.returned_at IS NULL
           AND l.due_date < CURDATE()) AS overdue_loans
    FROM members m
    WHERE m.id = ?
    LIMIT 1
");

if (!$lookup) {
    error_log('Member profile lookup preparation failed: ' . $conn->error);
    redirect(app_url('members/index.php?error=save'));
}

$lookup->bind_param('i', $memberId);

if (!$lookup->execute()) {
    error_log('Member profile lookup failed: ' . $lookup->error);
    $lookup->close();
    redirect(app_url('members/index.php?error=save'));
}

$member = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$member) {
    redirect(app_url('members/index.php?error=notfound'));
}

$error = '';
$loans = [];
$historyStmt = $conn->prepare("
    SELECT
        l.id,
        l.issue_date,
        l.due_date,
        l.returned_at,
        b.title,
        b.isbn,
        bc.barcode,
        u.name AS issued_by_name
    FROM loans l
    INNER JOIN book_copies bc ON bc.id = l.copy_id
    INNER JOIN books b ON b.id = bc.book_id
    LEFT JOIN users u ON u.id = l.issued_by
    WHERE l.member_id = ?
    ORDER BY l.issue_date DESC, l.id DESC
");

if (!$historyStmt) {
    error_log('Member borrowing history preparation failed: ' . $conn->error);
    $error = 'Borrowing history could not be loaded right now. Please try again later.';
} else {
    $historyStmt->bind_param('i', $memberId);

    if ($historyStmt->execute()) {
        $historyResult = $historyStmt->get_result();

        while ($row = $historyResult->fetch_assoc()) {
            $loans[] = $row;
        }
    } else {
        error_log('Member borrowing history query failed: ' . $historyStmt->error);
        $error = 'Borrowing history could not be loaded right now. Please try again later.';
    }

    $historyStmt->close();
}

$isActive = strcasecmp((string)$member['status'], 'Active') === 0;
$totalLoans = (int)$member['total_loans'];
$activeLoans = (int)$member['active_loans'];
$returnedLoans = (int)$member['returned_loans'];
$overdueLoans = (int)$member['overdue_loans'];
$membershipLabel = $member['membership_date']
    ? date('M j, Y', strtotime((string)$member['membership_date']))
    : '&mdash;';
$createdLabel = $member['created_at']
    ? date('M j, Y g:i A', strtotime((string)$member['created_at']))
    : '&mdash;';
$statusReturnUrl = 'view';

$pageTitle = 'Member Profile';
$activeNav = 'members';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Member Profile</div>
        <h1><?php echo e($member['name']); ?></h1>
        <p>Member ID #<?php echo (int)$member['id']; ?> &middot; <?php echo e($member['email']); ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?php echo e(app_url('members/index.php')); ?>">Back to Members</a>
        <a class="btn btn-secondary" href="<?php echo e(app_url('members/edit.php?id=' . (int)$member['id'])); ?>">Edit Member</a>
        <form method="post" action="<?php echo e(app_url('members/status.php')); ?>" class="inline-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$member['id']; ?>">
            <input type="hidden" name="return_to" value="<?php echo e($statusReturnUrl); ?>">
            <button type="submit" class="btn <?php echo $isActive ? 'btn-danger' : 'btn-primary'; ?>">
                <?php echo $isActive ? 'Deactivate Member' : 'Reactivate Member'; ?>
            </button>
        </form>
    </div>
</section>

<?php if ($notice !== ''): ?>
    <div class="alert alert-success" role="status"><?php echo e($notice); ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="stat-grid member-stats" aria-label="Member borrowing statistics">
    <article class="stat-card">
        <div class="stat-label">Total Loans</div>
        <div class="stat-value"><?php echo number_format($totalLoans); ?></div>
        <div class="stat-note">All borrowing records</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Active Loans</div>
        <div class="stat-value"><?php echo number_format($activeLoans); ?></div>
        <div class="stat-note">Currently issued</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Returned</div>
        <div class="stat-value"><?php echo number_format($returnedLoans); ?></div>
        <div class="stat-note">Completed loans</div>
    </article>
    <article class="stat-card<?php echo $overdueLoans > 0 ? ' danger' : ''; ?>">
        <div class="stat-label">Overdue</div>
        <div class="stat-value"><?php echo number_format($overdueLoans); ?></div>
        <div class="stat-note">Past their due date</div>
    </article>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Contact &amp; Membership</h2>
            <p class="panel-intro">Contact information is visible to authenticated staff only.</p>
        </div>
        <div class="detail-badges">
            <span class="status-badge <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>">
                <?php echo e($member['status']); ?>
            </span>
            <span class="count-pill"><?php echo number_format($activeLoans); ?> active loans</span>
        </div>
    </div>

    <div class="member-meta">
        <div class="member-meta-item">
            <span class="member-meta-label">Email Address</span>
            <div class="member-meta-value"><?php echo e($member['email']); ?></div>
        </div>
        <div class="member-meta-item">
            <span class="member-meta-label">Phone</span>
            <div class="member-meta-value">
                <?php echo $member['phone'] !== null && $member['phone'] !== '' ? e($member['phone']) : '&mdash;'; ?>
            </div>
        </div>
        <div class="member-meta-item">
            <span class="member-meta-label">Membership Date</span>
            <div class="member-meta-value"><?php echo $membershipLabel; ?></div>
        </div>
        <div class="member-meta-item">
            <span class="member-meta-label">Registered</span>
            <div class="member-meta-value"><?php echo $createdLabel; ?></div>
        </div>
        <div class="member-meta-item form-group-full">
            <span class="member-meta-label">Address</span>
            <div class="member-meta-value member-address">
                <?php if ($member['address'] !== null && $member['address'] !== ''): ?>
                    <?php echo nl2br(e($member['address'])); ?>
                <?php else: ?>
                    &mdash;
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Borrowing History</h2>
            <p class="panel-intro">Loan records are read-only and are never deleted with a member.</p>
        </div>
        <span class="count-pill"><?php echo number_format(count($loans)); ?> record<?php echo count($loans) === 1 ? '' : 's'; ?></span>
    </div>

    <div class="table-wrap">
        <table class="data-table" aria-label="Member borrowing history">
            <thead>
                <tr>
                    <th scope="col">Book</th>
                    <th scope="col">Barcode</th>
                    <th scope="col">Issue Date</th>
                    <th scope="col">Due Date</th>
                    <th scope="col">Returned</th>
                    <th scope="col">Loan Status</th>
                    <th scope="col">Issued By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($loans) === 0): ?>
                    <tr>
                        <td colspan="7" class="empty-cell">No borrowing history found for this member.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($loans as $loan): ?>
                        <?php
                        if ($loan['returned_at'] !== null) {
                            $loanStatus = 'Returned';
                            $loanClass = 'availability-none';
                        } elseif ((string)$loan['due_date'] < date('Y-m-d')) {
                            $loanStatus = 'Overdue';
                            $loanClass = 'availability-unavailable';
                        } else {
                            $loanStatus = 'Active';
                            $loanClass = 'availability-available';
                        }
                        ?>
                        <tr>
                            <td>
                                <span class="book-title"><?php echo e($loan['title']); ?></span>
                                <span class="book-subtext">ISBN <?php echo e($loan['isbn']); ?></span>
                            </td>
                            <td><span class="copy-barcode"><?php echo e($loan['barcode']); ?></span></td>
                            <td><?php echo e(date('M j, Y', strtotime((string)$loan['issue_date']))); ?></td>
                            <td><?php echo e(date('M j, Y', strtotime((string)$loan['due_date']))); ?></td>
                            <td>
                                <?php echo $loan['returned_at'] !== null
                                    ? e(date('M j, Y', strtotime((string)$loan['returned_at'])))
                                    : '&mdash;'; ?>
                            </td>
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
