<?php
require_once __DIR__ . '/auth.php';

$staff = current_user();

if ($staff === null) {
    redirect(app_url('login.php'));
}

$pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle : 'Staff Workspace';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> | Grand Library</title>
    <link rel="stylesheet" href="<?php echo e(app_url('assets/tera.css')); ?>">
</head>
<body class="staff-body">
<header class="staff-topbar">
    <button type="button" class="menu-toggle" data-menu-toggle aria-controls="staffSidebar"
            aria-expanded="false" aria-label="Open navigation menu">
        <span></span><span></span><span></span>
    </button>

    <a class="logo staff-logo" href="<?php echo e(app_url('dashboard.php')); ?>">Grand<span>Library.</span></a>

    <div class="staff-user">
        <div class="staff-user-info">
            <div class="staff-user-name"><?php echo e($staff['name']); ?></div>
            <span class="role-badge"><?php echo e($staff['role']); ?></span>
        </div>
        <form method="post" action="<?php echo e(app_url('logout.php')); ?>" class="logout-form">
            <?php echo csrf_field(); ?>
            <button type="submit" class="logout-btn">Log out</button>
        </form>
    </div>
</header>

<div class="staff-layout">
    <div class="sidebar-overlay" data-sidebar-overlay></div>
