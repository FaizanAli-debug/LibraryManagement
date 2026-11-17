<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../config/db.php';

start_secure_session();

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $role = normalize_role((string)($_SESSION['user_role'] ?? ''));

    if ($role === null) {
        return null;
    }

    return [
        'id' => (int)$_SESSION['user_id'],
        'name' => (string)($_SESSION['user_name'] ?? ''),
        'role' => $role,
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();

    return $user !== null && $user['role'] === 'Admin';
}

function login_user(array $user): void
{
    $role = normalize_role((string)($user['role'] ?? ''));

    if ($role === null) {
        throw new InvalidArgumentException('Unsupported staff role.');
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = (string)$user['name'];
    $_SESSION['user_role'] = $role;
    $_SESSION['logged_in_at'] = time();

    rotate_csrf_token();
}

function logout_user(): void
{
    destroy_session();
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        redirect(app_url('login.php'));
    }

    global $conn;

    $stmt = $conn->prepare('SELECT name, role, status FROM users WHERE id = ? LIMIT 1');

    if (!$stmt) {
        logout_user();
        redirect(app_url('login.php'));
    }

    $stmt->bind_param('i', $user['id']);

    if (!$stmt->execute()) {
        $stmt->close();
        logout_user();
        redirect(app_url('login.php'));
    }

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $role = $row ? normalize_role((string)$row['role']) : null;

    if (!$row || $role === null || strcasecmp((string)$row['status'], 'Active') !== 0) {
        logout_user();
        redirect(app_url('login.php'));
    }

    $_SESSION['user_name'] = (string)$row['name'];
    $_SESSION['user_role'] = $role;

    return [
        'id' => $user['id'],
        'name' => (string)$row['name'],
        'role' => $role,
    ];
}

function require_staff(): array
{
    $user = require_login();

    if (normalize_role($user['role']) === null) {
        logout_user();
        redirect(app_url('login.php'));
    }

    return $user;
}

function require_admin(): array
{
    $user = require_staff();

    if ($user['role'] !== 'Admin') {
        http_response_code(403);
        exit('Access denied. Administrator privileges are required.');
    }

    return $user;
}
