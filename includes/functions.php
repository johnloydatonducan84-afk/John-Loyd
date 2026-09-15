<?php

/*
 * How long (in seconds) a signed-in session stays valid without activity.
 * Default PHP settings (session.gc_maxlifetime = 1440s / 24 minutes) were
 * causing users to be bounced back to the login page after using the app
 * for a while. Users want to stay signed in until they explicitly log
 * out, so this is set very long (30 days) and slides forward on every
 * request - as long as they visit at least once within that window,
 * they are never bounced back to login on their own.
 */
define('SESSION_LIFETIME_SECONDS', 30 * 24 * 60 * 60); // 30 days

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME_SECONDS);

    /*
     * The array form of session_set_cookie_params() needs PHP 7.3+.
     * Some hosts (e.g. cheaper shared/free hosting) still run older
     * PHP, where that array form throws a fatal error on every
     * request. The classic positional-argument form works on every
     * PHP version, so we use that instead.
     */
    session_set_cookie_params(
        SESSION_LIFETIME_SECONDS,
        '/',
        '',
        false,
        true
    );

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

/*
 * The app's URL base path, detected automatically instead of hardcoded.
 *
 * Locally (XAMPP) the project lives in a /caresched/ subfolder under
 * htdocs, so links need that prefix. On live hosting (e.g. ProFreeHost)
 * the domain's document root often *is* the project root, so the same
 * hardcoded "/caresched/..." links 404 (they resolve to a non-existent
 * nested /caresched/caresched/... path). Comparing the project root
 * against the server's document root lets the same codebase deploy to
 * either layout without editing config per environment.
 */
if (!defined('APP_BASE_PATH')) {

    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $appRoot = realpath(__DIR__ . '/..');

    $basePath = '';

    if ($documentRoot && $appRoot) {

        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $appRoot = rtrim(str_replace('\\', '/', $appRoot), '/');

        if (stripos($appRoot, $documentRoot) === 0) {
            $basePath = substr($appRoot, strlen($documentRoot));
        }
    }

    define('APP_BASE_PATH', rtrim($basePath, '/'));
}

/**
 * Build a root-relative app URL (e.g. app_url('patient/dashboard.php')).
 * Pass '' or omit for the app's home URL.
 */
function app_url(string $path = ''): string
{
    return APP_BASE_PATH . '/' . ltrim($path, '/');
}

/**
 * Build a full absolute app URL (scheme + host), for contexts like
 * emails where a root-relative link isn't clickable on its own.
 */
function app_full_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . app_url($path);
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
