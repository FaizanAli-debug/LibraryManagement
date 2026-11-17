<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

$noticeMap = [
    'created' => 'Member created successfully.',
    'updated' => 'Member updated successfully.',
    'status' => 'Member status updated successfully.',
    'deleted' => 'Member deleted successfully.',
];

$errorMap = [
    'notfound' => 'The requested member could not be found.',
    'history' => 'This member cannot be deleted because they have loan history.',
    'invalid' => 'The request was invalid. Please try again.',
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
    if (in_array($statusInput, ['Active', 'Inactive'], true)) {
        $statusFilter = $statusInput;
    } else {
        $error = 'Select a valid status filter.';
    }
}

$conditions = [];
$types = '';
$params = [];

if ($search !== '') {
    $conditions[] = '(m.name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($statusFilter !== '') {
    $conditions[] = 'm.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}

$sql = "
    SELECT
        m.id,
        m.name,
        m.email,
        m.phone,
        m.membership_date,
        m.status,
        m.created_at,
        (SELECT COUNT(*) FROM loans l WHERE l.member_id = m.id) AS total_loans,
        (SELECT COUNT(*) FROM loans l WHERE l.member_id = m.id AND l.returned_at IS NULL) AS active_loans
    FROM members m
";

if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY m.name ASC, m.id ASC';

$members = [];
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log('Member list preparation failed: ' . $conn->error);
    $error = 'Members could not be loaded right now. Please try again later.';
} else {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
    } else {
        error_log('Member list query failed: ' . $stmt->error);
        $error = 'Members could not be loaded right now. Please try again later.';
    }

    $stmt->close();
}

$totalShown = count($members);
$activeShown = 0;
$inactiveShown = 0;

foreach ($members as $memberRow) {
    if (strcasecmp((string)$memberRow['status'], 'Active') === 0) {
        $activeShown++;
    } else {
        $inactiveShown++;
    }
}

$scopeLabel = ($search !== '' || $statusFilter !== '') ? 'Current results' : 'All members';

$pageTitle = 'Members';
$activeNav = 'members';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Member Management</div>
        <h1>Members</h1>
        <p>Manage member records, contact details, membership status and borrowing history.</p>
    </div>
    <a class="btn btn-primary" href="<?php echo e(app_url('members/add.php')); ?>">Add Member</a>
</section>

<?php if ($notice !== ''): ?>
    <div class="alert alert-success" role="status"><?php echo e($notice); ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="stat-grid member-stats" aria-label="Member statistics">
    <article class="stat-card">
        <div class="stat-label">Total Members</div>
        <div class="stat-value"><?php echo number_format($totalShown); ?></div>
        <div class="stat-note"><?php echo e($scopeLabel); ?></div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Active</div>
        <div class="stat-value"><?php echo number_format($activeShown); ?></div>
        <div class="stat-note">Eligible to borrow</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Inactive</div>
        <div class="stat-value"><?php echo number_format($inactiveShown); ?></div>
        <div class="stat-note">Borrowing disabled</div>
    </article>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Member Directory</h2>
            <p class="panel-intro">Contact information is restricted to authenticated staff.</p>
        </div>
        <span class="count-pill"><?php echo number_format($totalShown); ?> shown</span>
    </div>

    <form method="get" action="<?php echo e(app_url('members/index.php')); ?>" class="staff-filter">
        <div class="filter-field">
            <label for="search">Search Members</label>
            <input class="form-control" type="search" id="search" name="search"
                   value="<?php echo e($search); ?>" maxlength="200"
                   placeholder="Name, email or phone">
        </div>

        <div class="filter-field">
            <label for="status">Status</label>
            <select class="form-control" id="status" name="status">
                <option value="">All statuses</option>
                <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo $statusFilter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Search</button>
            <a class="btn btn-secondary" href="<?php echo e(app_url('members/index.php')); ?>">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" aria-label="Library members">
            <thead>
                <tr>
                    <th scope="col">Member</th>
                    <th scope="col">Contact</th>
                    <th scope="col">Membership Date</th>
                    <th scope="col">Status</th>
                    <th scope="col">Loans</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($totalShown === 0): ?>
                    <tr>
                        <td colspan="6" class="empty-cell">No members found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($members as $member): ?>
                        <?php
                        $isActive = strcasecmp((string)$member['status'], 'Active') === 0;
                        $totalLoans = (int)$member['total_loans'];
                        $activeLoans = (int)$member['active_loans'];
                        $membershipLabel = $member['membership_date']
                            ? date('M j, Y', strtotime((string)$member['membership_date']))
                            : '&mdash;';
                        $deleteConfirm = 'Permanently delete ' . $member['name'] . '? This action cannot be undone.';
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
                            <td><?php echo $membershipLabel; ?></td>
                            <td>
                                <span class="status-badge <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo e($member['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="copy-count<?php echo $activeLoans === 0 ? ' zero' : ''; ?>">
                                    <?php echo number_format($activeLoans); ?>
                                </span>
                                <span class="book-subtext">
                                    active &middot; <?php echo number_format($totalLoans); ?> total
                                </span>
                            </td>
                            <td class="table-actions">
                                <a class="btn btn-secondary btn-small"
                                   href="<?php echo e(app_url('members/view.php?id=' . (int)$member['id'])); ?>">View</a>
                                <a class="btn btn-secondary btn-small"
                                   href="<?php echo e(app_url('members/edit.php?id=' . (int)$member['id'])); ?>">Edit</a>

                                <form method="post" action="<?php echo e(app_url('members/status.php')); ?>" class="inline-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$member['id']; ?>">
                                    <input type="hidden" name="return_to" value="list">
                                    <button type="submit" class="btn btn-small <?php echo $isActive ? 'btn-danger' : 'btn-secondary'; ?>">
                                        <?php echo $isActive ? 'Deactivate' : 'Reactivate'; ?>
                                    </button>
                                </form>

                                <?php if ($totalLoans > 0): ?>
                                    <span class="protected-label" title="Members with loan history cannot be deleted">
                                        Loan history
                                    </span>
                                <?php elseif (is_admin()): ?>
                                    <form method="post" action="<?php echo e(app_url('members/delete.php')); ?>"
                                          class="inline-form" data-confirm="<?php echo e($deleteConfirm); ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$member['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                    </form>
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
