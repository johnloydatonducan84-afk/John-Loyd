<?php

/*
 * How long (in seconds) a signed-in session stays valid without activity.
 * Default PHP settings (session.gc_maxlifetime = 1440s / 24 minutes) were
 * causing users to be bounced back to the login page after using the app
 * for a while, so we extend it here and refresh the cookie on every
 * request so it keeps sliding forward while the user is active.
 */
define('SESSION_LIFETIME_SECONDS', 4 * 60 * 60); // 4 hours

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME_SECONDS);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME_SECONDS,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/*
 * Sliding expiration: extend the cookie's lifetime on every request so
 * an active user is never logged out mid-use.
 */
if (!empty($_SESSION['user_id']) && ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        session_id(),
        time() + SESSION_LIFETIME_SECONDS,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

/**
 * Generate or return CSRF token.
 */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token.
 */
function verify_csrf(string $token): bool
{
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Flash message helper.
 */
function flash(string $type, string $message = null)
{
    if ($message === null) {
        if (!empty($_SESSION['flash'][$type])) {
            $value = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]);
            return $value;
        }

        return null;
    }

    $_SESSION['flash'][$type] = $message;
}

/**
 * Redirect helper.
 */
function redirect(string $path)
{
    header('Location: ' . $path);
    exit;
}

/**
 * Escape output.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a full name string.
 */
function format_full_name(?string $first, ?string $middle, ?string $last): string
{
    $parts = array_filter([
        trim((string) $first),
        trim((string) $middle),
        trim((string) $last)
    ]);

    return implode(' ', $parts);
}

/**
 * Return the primary active admin user.
 */
function get_admin_user(PDO $pdo): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, u.email, a.full_name
         FROM users u
         INNER JOIN admins a ON a.user_id = u.id
         WHERE u.role = \'admin\'
           AND u.status = \'active\'
         ORDER BY a.created_at DESC
         LIMIT 1'
    );

    $stmt->execute();

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    return $admin ?: null;
}

/**
 * Get the unread message count for a user.
 */
function get_unread_message_count(PDO $pdo, int $user_id): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM messages WHERE receiver_id = :user_id AND is_read = 0'
    );

    $stmt->execute(['user_id' => $user_id]);

    return (int) $stmt->fetchColumn();
}

/**
 * Logout helper.
 */
function logout(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
