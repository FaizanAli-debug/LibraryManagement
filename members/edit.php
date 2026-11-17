<?php
require_once __DIR__ . '/../includes/auth.php';

require_staff();

function member_phone_is_valid(string $phone): bool
{
    if ($phone === '') {
        return true;
    }

    if (strlen($phone) > 20 || preg_match('/^[0-9+()\-.\s]+$/', $phone) !== 1) {
        return false;
    }

    return strlen(preg_replace('/[^0-9]/', '', $phone)) >= 7;
}

$idInput = is_post() ? post_value('id') : get_value('id');

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
        (SELECT COUNT(*) FROM loans l WHERE l.member_id = m.id AND l.returned_at IS NULL) AS active_loans
    FROM members m
    WHERE m.id = ?
    LIMIT 1
");

if (!$lookup) {
    error_log('Member edit lookup preparation failed: ' . $conn->error);
    redirect(app_url('members/index.php?error=save'));
}

$lookup->bind_param('i', $memberId);

if (!$lookup->execute()) {
    error_log('Member edit lookup failed: ' . $lookup->error);
    $lookup->close();
    redirect(app_url('members/index.php?error=save'));
}

$member = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$member) {
    redirect(app_url('members/index.php?error=notfound'));
}

$error = '';
$name = (string)$member['name'];
$email = (string)$member['email'];
$phone = (string)($member['phone'] ?? '');
$address = (string)($member['address'] ?? '');
$membershipDate = (string)$member['membership_date'];

if (is_post()) {
    if (!csrf_verify()) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $name = post_value('name');
        $email = post_value('email');
        $phone = post_value('phone');
        $address = post_value('address');
        $membershipDate = post_value('membership_date');
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        $addressLength = function_exists('mb_strlen') ? mb_strlen($address) : strlen($address);
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $membershipDate);
        $today = new DateTimeImmutable('today');

        if ($nameLength < 2 || $nameLength > 100) {
            $error = 'Enter a member name between 2 and 100 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $error = 'Enter a valid email address.';
        } elseif (!member_phone_is_valid($phone)) {
            $error = 'Enter a valid phone number using digits and standard phone characters.';
        } elseif ($addressLength > 2000) {
            $error = 'The address must not exceed 2,000 characters.';
        } elseif ($membershipDate === '' || !$parsedDate || $parsedDate->format('Y-m-d') !== $membershipDate) {
            $error = 'Enter a valid membership date.';
        } elseif ($parsedDate > $today) {
            $error = 'The membership date cannot be in the future.';
        } else {
            $check = $conn->prepare('SELECT id FROM members WHERE email = ? AND id <> ? LIMIT 1');

            if (!$check) {
                error_log('Member edit duplicate-email check preparation failed: ' . $conn->error);
                $error = 'The member could not be updated. Please try again.';
            } else {
                $check->bind_param('si', $email, $memberId);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error = 'Another member already uses this email address.';
                }

                $check->close();
            }
        }

        if ($error === '') {
            $phoneValue = $phone === '' ? null : $phone;
            $addressValue = $address === '' ? null : $address;
            $stmt = $conn->prepare('
                UPDATE members
                SET name = ?, email = ?, phone = ?, address = ?, membership_date = ?
                WHERE id = ?
            ');

            if (!$stmt) {
                error_log('Member update preparation failed: ' . $conn->error);
                $error = 'The member could not be updated. Please try again.';
            } else {
                $stmt->bind_param('sssssi', $name, $email, $phoneValue, $addressValue, $membershipDate, $memberId);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirect(app_url('members/index.php?notice=updated'));
                }

                $updateError = $stmt->errno;
                $updateMessage = $stmt->error;
                $stmt->close();

                if ($updateError === 1062) {
                    $error = 'Another member already uses this email address.';
                } else {
                    error_log('Member update failed: ' . $updateMessage);
                    $error = 'The member could not be updated. Please try again.';
                }
            }
        }
    }
}

$isActive = strcasecmp((string)$member['status'], 'Active') === 0;
$totalLoans = (int)$member['total_loans'];
$activeLoans = (int)$member['active_loans'];
$createdLabel = $member['created_at']
    ? date('M j, Y', strtotime((string)$member['created_at']))
    : '&mdash;';

$pageTitle = 'Edit Member';
$activeNav = 'members';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Member Management</div>
        <h1>Edit Member</h1>
        <p>Update member details. Membership status is managed separately.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?php echo e(app_url('members/index.php')); ?>">Back to Members</a>
        <a class="btn btn-primary" href="<?php echo e(app_url('members/view.php?id=' . (int)$member['id'])); ?>">View Profile</a>
    </div>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2><?php echo e($member['name']); ?></h2>
            <p class="panel-intro">
                Member ID #<?php echo (int)$member['id']; ?> &middot; Registered <?php echo $createdLabel; ?>.
            </p>
        </div>
        <div class="detail-badges">
            <span class="status-badge <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>">
                <?php echo e($member['status']); ?>
            </span>
            <span class="count-pill"><?php echo number_format($activeLoans); ?> active loans</span>
            <span class="count-pill"><?php echo number_format($totalLoans); ?> total loans</span>
        </div>
    </div>

    <form method="post" action="<?php echo e(app_url('members/edit.php')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$member['id']; ?>">

        <div class="form-grid">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input class="form-control" type="text" id="name" name="name"
                       value="<?php echo e($name); ?>" maxlength="100"
                       placeholder="Member full name" autocomplete="name" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input class="form-control" type="email" id="email" name="email"
                       value="<?php echo e($email); ?>" maxlength="150"
                       placeholder="member@example.com" autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone <span class="table-note">(optional)</span></label>
                <input class="form-control" type="tel" id="phone" name="phone"
                       value="<?php echo e($phone); ?>" maxlength="20"
                       placeholder="For example: 03001234567" autocomplete="tel">
            </div>

            <div class="form-group">
                <label for="membership_date">Membership Date</label>
                <input class="form-control" type="date" id="membership_date" name="membership_date"
                       value="<?php echo e($membershipDate); ?>" max="<?php echo e(date('Y-m-d')); ?>" required>
            </div>

            <div class="form-group form-group-full">
                <label for="address">Address <span class="table-note">(optional)</span></label>
                <textarea class="form-control" id="address" name="address" maxlength="2000"
                          placeholder="Member address" autocomplete="street-address"><?php echo e($address); ?></textarea>
                <div class="form-help">
                    Up to 2,000 characters. Status changes are handled from the member profile.
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?php echo e(app_url('members/index.php')); ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
