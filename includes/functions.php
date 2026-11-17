<?php

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(dirname(__DIR__));
    $base = '';

    if ($documentRoot && $appRoot) {
        $docRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $rootPath = rtrim(str_replace('\\', '/', $appRoot), '/');

        if (stripos($rootPath, $docRoot) === 0) {
            $base = substr($rootPath, strlen($docRoot));
        }
    }

    $base = rtrim($base, '/');
    $path = ltrim($path, '/');

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return $base . '/' . $path;
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';

    if (!headers_sent()) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();
}

function destroy_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        start_secure_session();
    }

    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!headers_sent() && ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            $options = [
                'expires' => time() - 42000,
                'path' => $params['path'] !== '' ? $params['path'] : '/',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ];

            if (!empty($params['domain'])) {
                $options['domain'] = $params['domain'];
            }

            setcookie(session_name(), '', $options);
        }

        session_destroy();
    }
}

function redirect(string $url): void
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    }

    exit;
}

function is_post(): bool
{
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post_value(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function get_value(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function normalize_role(string $role): ?string
{
    if (strcasecmp($role, 'Admin') === 0) {
        return 'Admin';
    }

    if (strcasecmp($role, 'Librarian') === 0) {
        return 'Librarian';
    }

    return null;
}

function db_value(mysqli $conn, string $sql): ?int
{
    $result = $conn->query($sql);

    if (!$result) {
        return null;
    }

    $row = $result->fetch_row();
    $result->free();

    return $row === null ? null : (int)$row[0];
}
