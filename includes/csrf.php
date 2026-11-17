<?php

require_once __DIR__ . '/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    start_secure_session();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }

    if (!is_string($token) || $token === '') {
        return false;
    }

    $stored = $_SESSION['csrf_token'] ?? '';

    return is_string($stored) && $stored !== '' && hash_equals($stored, $token);
}

function rotate_csrf_token(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function require_csrf(): void
{
    if (!csrf_verify()) {
        http_response_code(403);
        exit('Your session has expired. Please refresh the page and try again.');
    }
}
