<?php
require_once __DIR__ . '/includes/auth.php';

if (!is_post()) {
    redirect(is_logged_in() ? app_url('dashboard.php') : app_url('login.php'));
}

require_csrf();
logout_user();
redirect(app_url('login.php'));
