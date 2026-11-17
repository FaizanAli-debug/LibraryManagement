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

$error = '';
$name = '';
$email = '';
$phone = '';
$address = '';
$membershipDate = date('Y-m-d');

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
            $check = $conn->prepare('SELECT id FROM members WHERE email = ? LIMIT 1');

            if (!$check) {
                error_log('Member duplicate-email check preparation failed: ' . $conn->error);
                $error = 'The member could not be created. Please try again.';
            } else {
                $check->bind_param('s', $email);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error = 'A member with this email address already exists.';
                }

                $check->close();
            }
        }

        if ($error === '') {
            $phoneValue = $phone === '' ? null : $phone;
            $addressValue = $address === '' ? null : $address;
            $stmt = $conn->prepare('
                INSERT INTO members (name, email, phone, address, membership_date)
                VALUES (?, ?, ?, ?, ?)
            ');

            if (!$stmt) {
                error_log('Member creation preparation failed: ' . $conn->error);
                $error = 'The member could not be created. Please try again.';
            } else {
                $stmt->bind_param('sssss', $name, $email, $phoneValue, $addressValue, $membershipDate);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirect(app_url('members/index.php?notice=created'));
                }

                $insertError = $stmt->errno;
                $insertMessage = $stmt->error;
                $stmt->close();

                if ($insertError === 1062) {
                    $error = 'A member with this email address already exists.';
                } else {
                    error_log('Member creation failed: ' . $insertMessage);
                    $error = 'The member could not be created. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Add Member';
$activeNav = 'members';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Member Management</div>
        <h1>Add Member</h1>
        <p>Register a new library member. New members are active immediately.</p>
    </div>
    <a class="btn btn-secondary" href="<?php echo e(app_url('members/index.php')); ?>">Back to Members</a>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2>Member Details</h2>
            <p class="panel-intro">Email addresses must be unique. Phone and address are optional.</p>
        </div>
    </div>

    <form method="post" action="<?php echo e(app_url('members/add.php')); ?>">
        <?php echo csrf_field(); ?>

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
                <div class="form-help">Up to 2,000 characters.</div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?php echo e(app_url('members/index.php')); ?>">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Member</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
