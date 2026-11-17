<?php
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

$noticeMap = [
    'created' => 'Librarian account created successfully.',
    'updated' => 'Staff member updated successfully.',
    'status' => 'Staff account status updated successfully.',
];

$errorMap = [
    'notfound' => 'The requested staff member could not be found.',
    'protected' => 'Administrator accounts cannot be deactivated through this module.',
    'invalid' => 'The request was invalid. Please try again.',
    'save' => 'The change could not be saved. Please try again.',
];

$notice = $noticeMap[get_value('notice')] ?? '';
$error = $errorMap[get_value('error')] ?? '';

$staffResult = $conn->query("
    SELECT id, name, email, role, status, created_at
    FROM users
    ORDER BY (role = 'Admin') DESC, name ASC
");

$staffMembers = [];

if ($staffResult) {
    while ($row = $staffResult->fetch_assoc()) {
        $staffMembers[] = $row;
    }

    $staffResult->free();
} else {
    $error = 'Staff accounts could not be loaded right now. Please try again later.';
}

$pageTitle = 'Staff Management';
$activeNav = 'staff';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Administration</div>
        <h1>Staff Management</h1>
        <p>Manage Librarian accounts, staff details and account access. Administrator accounts are protected.</p>
    </div>
    <a class="btn btn-primary" href="<?php echo e(app_url('staff/add.php')); ?>">Add Librarian</a>
</section>

<?php if ($notice !== ''): ?>
    <div class="alert alert-success" role="status"><?php echo e($notice); ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Staff Accounts</h2>
            <p class="panel-intro">Only Administrators can access this module. Deactivated Librarians cannot sign in.</p>
        </div>
        <span class="count-pill"><?php echo number_format(count($staffMembers)); ?> total</span>
    </div>

    <div class="table-wrap">
        <table class="data-table" aria-label="Staff accounts">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                    <th scope="col">Created Date</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($staffMembers) === 0): ?>
                    <tr>
                        <td colspan="6" class="empty-cell">No staff accounts found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($staffMembers as $member): ?>
                        <?php
                        $createdLabel = $member['created_at']
                            ? date('M j, Y', strtotime($member['created_at']))
                            : '&mdash;';
                        $isLibrarian = $member['role'] === 'Librarian';
                        $isActive = strcasecmp((string)$member['status'], 'Active') === 0;
                        ?>
                        <tr>
                            <td>
                                <span class="staff-name"><?php echo e($member['name']); ?></span>
                                <?php if ((int)$member['id'] === (int)$admin['id']): ?>
                                    <span class="self-label">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($member['email']); ?></td>
                            <td><span class="role-badge"><?php echo e($member['role']); ?></span></td>
                            <td>
                                <span class="status-badge <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo e($member['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $createdLabel; ?></td>
                            <td class="table-actions">
                                <a class="btn btn-secondary btn-small"
                                   href="<?php echo e(app_url('staff/edit.php?id=' . (int)$member['id'])); ?>">Edit</a>

                                <?php if ($isLibrarian): ?>
                                    <form method="post" action="<?php echo e(app_url('staff/status.php')); ?>" class="inline-form">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$member['id']; ?>">
                                        <button type="submit" class="btn btn-small <?php echo $isActive ? 'btn-danger' : 'btn-secondary'; ?>">
                                            <?php echo $isActive ? 'Deactivate' : 'Reactivate'; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="protected-label">Protected</span>
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
