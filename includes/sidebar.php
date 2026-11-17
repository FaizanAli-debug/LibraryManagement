<?php
$activeNav = isset($activeNav) ? $activeNav : '';
?>
<aside class="staff-sidebar" id="staffSidebar">
    <div class="sidebar-brand">Library<span>Desk</span></div>

    <nav class="sidebar-nav" aria-label="Staff navigation">
        <div class="sidebar-label">Workspace</div>
        <a href="<?php echo e(app_url('dashboard.php')); ?>" class="<?php echo $activeNav === 'dashboard' ? 'active' : ''; ?>">
            <span>Dashboard</span>
        </a>
        <a href="<?php echo e(app_url('catalog.php')); ?>" class="<?php echo $activeNav === 'catalog' ? 'active' : ''; ?>">
            <span>Browse Catalog</span>
        </a>

        <div class="sidebar-label">Management</div>
        <a href="<?php echo e(app_url('books/index.php')); ?>" class="<?php echo $activeNav === 'books' ? 'active' : ''; ?>">
            <span>Books &amp; Titles</span>
        </a>
        <a href="<?php echo e(app_url('authors/index.php')); ?>" class="<?php echo $activeNav === 'authors' ? 'active' : ''; ?>">
            <span>Authors</span>
        </a>
        <a href="<?php echo e(app_url('categories/index.php')); ?>" class="<?php echo $activeNav === 'categories' ? 'active' : ''; ?>">
            <span>Categories</span>
        </a>
        <a href="<?php echo e(app_url('members/index.php')); ?>" class="<?php echo $activeNav === 'members' ? 'active' : ''; ?>">
            <span>Members</span>
        </a>
        <a href="<?php echo e(app_url('copies/index.php')); ?>" class="<?php echo $activeNav === 'copies' ? 'active' : ''; ?>">
            <span>Physical Copies</span>
        </a>

        <div class="sidebar-label">Circulation</div>
        <a href="<?php echo e(app_url('loans/issue.php')); ?>" class="<?php echo $activeNav === 'issue' ? 'active' : ''; ?>">
            <span>Issue Book</span>
        </a>
        <a href="<?php echo e(app_url('loans/index.php?status=active')); ?>" class="<?php echo $activeNav === 'returns' ? 'active' : ''; ?>">
            <span>Return Book</span>
        </a>
        <a href="<?php echo e(app_url('loans/index.php')); ?>" class="<?php echo $activeNav === 'loans' ? 'active' : ''; ?>">
            <span>Loans</span>
        </a>
        <a href="<?php echo e(app_url('reports/index.php')); ?>" class="<?php echo $activeNav === 'reports' ? 'active' : ''; ?>">
            <span>Reports</span>
        </a>

        <?php if (is_admin()): ?>
            <div class="sidebar-label">Administration</div>
            <a href="<?php echo e(app_url('staff/index.php')); ?>" class="<?php echo $activeNav === 'staff' ? 'active' : ''; ?>">
                <span>Staff Management</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-foot">
        Grand Library &middot; Staff workspace
    </div>
</aside>

<main class="staff-main" id="mainContent">
