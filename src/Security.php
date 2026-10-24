<?php
declare(strict_types=1);

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function e(string|int|float|bool|null $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function user_error_message(Throwable $error): string
{
    if ($error instanceof InvalidArgumentException) {
        return $error->getMessage();
    }
    if ($error instanceof PDOException) {
        return 'No se pudo guardar: hay datos duplicados o relacionados.';
    }
    return 'Ocurrió un error interno. Intente nuevamente.';
}

function log_internal_error(Throwable $error, ?string $logPath = null): void
{
    if ($logPath === null) {
        $logDirectory = dirname(__DIR__) . '/logs';
        if (!is_dir($logDirectory) && !@mkdir($logDirectory, 0775, true)) return;
        $logPath = $logDirectory . '/app.log';
    }

    $entry = sprintf(
        "%s %s code=%s source=%s:%d\n",
        date(DATE_ATOM),
        $error::class,
        (string) $error->getCode(),
        basename($error->getFile()),
        $error->getLine()
    );
    @file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
}

function handle_uncaught_exception(Throwable $error): void
{
    log_internal_error($error);
    if (!headers_sent()) http_response_code(500);
    echo 'Ocurrió un error interno. Intente nuevamente.';
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function rotate_csrf_token(): string
{
    unset($_SESSION['csrf']);
    return csrf_token();
}

function verify_csrf(): void
{
    $sessionToken = $_SESSION['csrf'] ?? null;
    $token = $_POST['csrf'] ?? '';
    if (
        !is_string($sessionToken)
        || $sessionToken === ''
        || !is_string($token)
        || $token === ''
        || !hash_equals($sessionToken, $token)
    ) {
        http_response_code(403);
        exit('Solicitud no válida. Recargue la página e intente nuevamente.');
    }
}

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function invalidate_session_user(): void
{
    unset($_SESSION['user'], $_SESSION['csrf']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function revalidate_session_user(App $app): void
{
    $sessionUser = current_user();
    if ($sessionUser === null) return;

    $userId = filter_var($sessionUser['id'] ?? null, FILTER_VALIDATE_INT);
    if ($userId === false || $userId < 1) {
        invalidate_session_user();
        return;
    }

    $user = (new \Sgsii\Services\AuthenticationService(
        new \Sgsii\Repositories\UserRepository($app->db())
    ))->activeSessionUser((int) $userId);
    if ($user === null) {
        invalidate_session_user();
        return;
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'role' => $user['role'],
        'name' => $user['first_name'],
    ];
}

function is_admin(): bool { return (current_user()['role'] ?? null) === 'admin'; }
function require_login(): void { if (!current_user()) { header('Location: ?page=login'); exit; } }
function require_admin(): void { require_login(); if (!is_admin()) { http_response_code(403); exit('Acceso denegado.'); } }
function flash(string $type, string $message): void { $_SESSION['flash'] = [$type, $message]; }
function pull_flash(): ?array { $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash; }
function go(string $page): never { header('Location: ?page=' . rawurlencode($page)); exit; }
