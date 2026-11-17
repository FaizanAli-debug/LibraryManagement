<?php
require_once __DIR__ . '/../includes/auth.php';

$staff = require_staff();

const DEFAULT_LOAN_DAYS = 14;

class LoanIssueException extends RuntimeException
{
}

$error = '';
$loadError = '';
$memberId = 0;
$copyId = 0;
$issueDate = date('Y-m-d');
$dueDate = date('Y-m-d', strtotime('+' . DEFAULT_LOAN_DAYS . ' days'));

if (is_post()) {
    if (!csrf_verify()) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $memberInput = post_value('member_id');
        $copyInput = post_value('copy_id');
        $issueDate = post_value('issue_date');
        $dueDate = post_value('due_date');
        $memberId = ctype_digit($memberInput) ? (int)$memberInput : 0;
        $copyId = ctype_digit($copyInput) ? (int)$copyInput : 0;
        $issueParsed = DateTimeImmutable::createFromFormat('!Y-m-d', $issueDate);
        $dueParsed = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
        $today = new DateTimeImmutable('today');

        if ($memberId <= 0) {
            $error = 'Select an active member for this loan.';
        } elseif ($copyId <= 0) {
            $error = 'Select an available physical copy for this loan.';
        } elseif ($issueDate === '' || !$issueParsed || $issueParsed->format('Y-m-d') !== $issueDate) {
            $error = 'Enter a valid issue date.';
        } elseif ($dueDate === '' || !$dueParsed || $dueParsed->format('Y-m-d') !== $dueDate) {
            $error = 'Enter a valid due date.';
        } elseif ($issueParsed > $today) {
            $error = 'The issue date cannot be in the future.';
        } elseif ($dueParsed < $issueParsed) {
            $error = 'The due date cannot be before the issue date.';
        } elseif (!$conn->begin_transaction()) {
            error_log('Loan issue transaction could not start: ' . $conn->error);
            $error = 'The book could not be issued. Please try again.';
        } else {
            try {
                $memberStmt = $conn->prepare('SELECT id, status FROM members WHERE id = ? FOR UPDATE');

                if (!$memberStmt) {
                    error_log('Loan issue member lookup preparation failed: ' . $conn->error);
                    throw new LoanIssueException('The book could not be issued. Please try again.');
                }

                $memberStmt->bind_param('i', $memberId);

                if (!$memberStmt->execute()) {
                    error_log('Loan issue member lookup failed: ' . $memberStmt->error);
                    $memberStmt->close();
                    throw new LoanIssueException('The book could not be issued. Please try again.');
                }

                $member = $memberStmt->get_result()->fetch_assoc();
                $memberStmt->close();

                if (!$member) {
                    throw new LoanIssueException('The selected member could not be found.');
                }

                if (strcasecmp((string)$member['status'], 'Active') !== 0) {
                    throw new LoanIssueException('Only active members can borrow books.');
                }

                $copyStmt = $conn->prepare('SELECT id FROM book_copies WHERE id = ? FOR UPDATE');

                if (!$copyStmt) {
                    error_log('Loan issue copy lookup preparation failed: ' . $conn->error);
                    throw new LoanIssueException('The book could not be issued. Please try again.');
                }

                $copyStmt->bind_param('i', $copyId);

                if (!$copyStmt->execute()) {
                    error_log('Loan issue copy lookup failed: ' . $copyStmt->error);
                    $copyStmt->close();
                    throw new LoanIssueException('The book could not be issued. Please try again.');
                }

                $copy = $copyStmt->get_result()->fetch_assoc();
                $copyStmt->close();

                if (!$copy) {
                    throw new LoanIssueException('The selected physical copy could not be found.');
                }

                $activeLoanStmt = $conn->prepare('
                    SELECT id
                    FROM loans
                    WHERE copy_id = ?
                      AND returned_at IS NULL
                    LIMIT 1
                    FOR UPDATE
                ');

                if (!$activeLoanStmt) {
                    error_log('Loan issue availability check preparation failed: ' . $conn->error);
                    throw new LoanIssueException('The book could not be issued. Please try again.');
                }

                $activeLoanStmt->bind_param('i', $copyId);

                if (!$activeLoanStmt->execute()) {
                    error_log('Loan issue availability check failed: ' . $activeLoanStmt->error);
                    $activeLoanStmt->close();
                    throw new LoanIssueException('The book could not be issued. Please try again.');
                }

                $activeLoan = $activeLoanStmt->get_result()->fetch_assoc();
                $activeLoanStmt->close();

                if ($activeLoan) {
                    throw new LoanIssueException('This physical copy is already on loan.');
                }

                $issuedBy = (int)$staff['id'];
                $insert = $conn->prepare('
                    INSERT INTO loans (member_id, copy_id, issued_by, issue_date, due_date)
                    VALUES (?, ?, ?, ?, ?)
                ');

                if (!$insert) {
                    error_log('Loan issue insert preparation failed: ' . $conn->error);
                    throw new LoanIssueException('The book could not be issued. Please try again.');
                }

                $insert->bind_param('iiiss', $memberId, $copyId, $issuedBy, $issueDate, $dueDate);

                if (!$insert->execute()) {
                    error_log('Loan issue insert failed: ' . $insert->error);
                    $insert->close();
                    throw new LoanIssueException('The book could not be issued. Please try again.');
                }

                $insert->close();

                if (!$conn->commit()) {
                    error_log('Loan issue commit failed: ' . $conn->error);
                    throw new LoanIssueException('The book could not be issued. Please try again.');
                }

                redirect(app_url('loans/index.php?notice=issued'));
            } catch (LoanIssueException $e) {
                $conn->rollback();
                $error = $e->getMessage();
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Unexpected loan issue failure: ' . $e->getMessage());
                $error = 'The book could not be issued. Please try again.';
            }
        }
    }
}

$activeMembers = [];
$membersResult = $conn->query("
    SELECT id, name, email
    FROM members
    WHERE status = 'Active'
    ORDER BY name ASC, id ASC
");

if ($membersResult) {
    while ($row = $membersResult->fetch_assoc()) {
        $activeMembers[] = $row;
    }

    $membersResult->free();
} else {
    error_log('Loan issue member options failed: ' . $conn->error);
    $loadError = 'Active member options could not be loaded. Please refresh the page.';
}

$availableCopies = [];
$copiesResult = $conn->query("
    SELECT
        bc.id,
        bc.barcode,
        b.title,
        b.isbn,
        a.name AS author_name
    FROM book_copies bc
    INNER JOIN books b ON b.id = bc.book_id
    LEFT JOIN authors a ON a.id = b.author_id
    WHERE NOT EXISTS (
        SELECT 1
        FROM loans l
        WHERE l.copy_id = bc.id
          AND l.returned_at IS NULL
    )
    ORDER BY b.title ASC, bc.barcode ASC
");

if ($copiesResult) {
    while ($row = $copiesResult->fetch_assoc()) {
        $availableCopies[] = $row;
    }

    $copiesResult->free();
} else {
    error_log('Loan issue copy options failed: ' . $conn->error);
    $loadError = 'Available copy options could not be loaded. Please refresh the page.';
}

$canIssue = count($activeMembers) > 0 && count($availableCopies) > 0;

$pageTitle = 'Issue Book';
$activeNav = 'issue';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="page-head">
    <div>
        <div class="eyebrow">Circulation</div>
        <h1>Issue Book</h1>
        <p>Issue an available physical copy to an active library member.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?php echo e(app_url('loans/index.php')); ?>">Loan Register</a>
        <a class="btn btn-secondary" href="<?php echo e(app_url('dashboard.php')); ?>">Dashboard</a>
    </div>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($error); ?></div>
<?php endif; ?>

<?php if ($loadError !== ''): ?>
    <div class="alert alert-error" role="alert"><?php echo e($loadError); ?></div>
<?php endif; ?>

<?php if (!$canIssue && $loadError === ''): ?>
    <div class="alert alert-error" role="alert">
        A loan cannot be issued until at least one active member and one available physical copy exist.
    </div>
<?php endif; ?>

<section class="panel form-panel">
    <div class="panel-head">
        <div>
            <h2>Loan Details</h2>
            <p class="panel-intro">
                The due date defaults to <?php echo (int)DEFAULT_LOAN_DAYS; ?> days after the issue date.
                Only active members and currently available copies are listed.
            </p>
        </div>
        <span class="count-pill">
            <?php echo number_format(count($availableCopies)); ?> available
        </span>
    </div>

    <form method="post" action="<?php echo e(app_url('loans/issue.php')); ?>">
        <?php echo csrf_field(); ?>

        <div class="form-grid">
            <div class="form-group form-group-full">
                <label for="member_id">Member</label>
                <select class="form-control" id="member_id" name="member_id" required
                        <?php echo count($activeMembers) === 0 ? 'disabled' : ''; ?>>
                    <option value="">Select an active member</option>
                    <?php foreach ($activeMembers as $member): ?>
                        <option value="<?php echo (int)$member['id']; ?>"
                            <?php echo $memberId === (int)$member['id'] ? 'selected' : ''; ?>>
                            <?php echo e($member['name']); ?> &mdash; <?php echo e($member['email']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-help"><?php echo number_format(count($activeMembers)); ?> active members available.</div>
            </div>

            <div class="form-group form-group-full">
                <label for="copy_id">Physical Copy</label>
                <select class="form-control" id="copy_id" name="copy_id" required
                        <?php echo count($availableCopies) === 0 ? 'disabled' : ''; ?>>
                    <option value="">Select an available copy</option>
                    <?php foreach ($availableCopies as $copy): ?>
                        <option value="<?php echo (int)$copy['id']; ?>"
                            <?php echo $copyId === (int)$copy['id'] ? 'selected' : ''; ?>>
                            <?php echo e($copy['title']); ?> &mdash; <?php echo e($copy['barcode']); ?>
                            (ISBN <?php echo e($copy['isbn']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-help"><?php echo number_format(count($availableCopies)); ?> copies currently available.</div>
            </div>

            <div class="form-group">
                <label for="issue_date">Issue Date</label>
                <input class="form-control" type="date" id="issue_date" name="issue_date"
                       value="<?php echo e($issueDate); ?>" max="<?php echo e(date('Y-m-d')); ?>" required>
            </div>

            <div class="form-group">
                <label for="due_date">Due Date</label>
                <input class="form-control" type="date" id="due_date" name="due_date"
                       value="<?php echo e($dueDate); ?>" min="<?php echo e($issueDate); ?>" required>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?php echo e(app_url('loans/index.php')); ?>">Cancel</a>
            <button type="submit" class="btn btn-primary" <?php echo $canIssue ? '' : 'disabled'; ?>>Issue Book</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
