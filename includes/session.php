<?php
/** Central session configuration and request-security helpers. */

// Never expose PHP warnings or uncaught exception details to visitors.
// Keep diagnostics in the server error log instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (!function_exists('skillConnectErrorResponse')) {
    /** Render a small, generic error page without exposing implementation details. */
    function skillConnectErrorResponse(int $status = 500, string $message = 'Something went wrong. Please try again later.'): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>SkillConnect - Error</title><style>'
            . 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f8fc;'
            . 'font:16px/1.6 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#14213d}'
            . 'main{box-sizing:border-box;width:min(92%,520px);padding:32px;border:1px solid #dce5f2;'
            . 'border-radius:18px;background:#fff;box-shadow:0 12px 35px #17345b12}h1{margin:0 0 12px;font-size:24px}'
            . 'p{margin:0;color:#60718d}</style></head><body><main><h1>SkillConnect</h1><p>'
            . $safeMessage . '</p></main></body></html>';
    }
}

// Register once even if this helper is reached through differently-cased paths.
if (!defined('SKILLCONNECT_EXCEPTION_HANDLER_REGISTERED')) {
    define('SKILLCONNECT_EXCEPTION_HANDLER_REGISTERED', true);
    set_exception_handler(static function (Throwable $exception): void {
        error_log(sprintf(
            'Unhandled SkillConnect exception: %s: %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));

        if (!headers_sent()) {
            skillConnectErrorResponse(500, 'An unexpected error occurred. Please try again later.');
            return;
        }

        // Avoid printing the exception or stack trace after partial output.
        echo '<p>An unexpected error occurred. Please try again later.</p>';
    });
}
if (session_status() === PHP_SESSION_NONE) {
    session_name('SKILLCONNECTSESSID');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $httpsValue = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $isHttps = $httpsValue !== '' && $httpsValue !== 'off' && $httpsValue !== '0';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!headers_sent() && isset($_SESSION['user_id'])) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

// Guards prevent fatal redeclaration if this file is reached via differently-cased paths.
if (!function_exists('csrfToken')) {
    function csrfToken(string $key = 'csrf_token'): string
    {
        if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key]) || strlen($_SESSION[$key]) < 32) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }
        return $_SESSION[$key];
    }
}

if (!function_exists('csrfValid')) {
    function csrfValid($submitted, string $key = 'csrf_token'): bool
    {
        $stored = $_SESSION[$key] ?? null;
        return is_string($submitted) && $submitted !== ''
            && is_string($stored) && $stored !== ''
            && hash_equals($stored, $submitted);
    }
}

/** File-backed rate limit; keys are hashed and raw email/answer data is never written. */
if (!function_exists('rateLimitExceeded')) {
    function rateLimitExceeded(string $scope, string $identity, int $limit, int $windowSeconds): bool
    {
        $key = hash('sha256', $scope . '|' . $identity);
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'skillconnect_rl_' . $key . '.json';
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            error_log('SkillConnect rate-limit storage unavailable.');
            return true;
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                return true;
            }
            $contents = stream_get_contents($handle);
            $attempts = json_decode($contents ?: '[]', true);
            if (!is_array($attempts)) {
                $attempts = [];
            }
            $now = time();
            $attempts = array_values(array_filter(
                $attempts,
                static fn($t) => is_int($t) && $t > ($now - $windowSeconds)
            ));
            if (count($attempts) >= $limit) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, json_encode($attempts));
                fflush($handle);
                return true;
            }
            $attempts[] = $now;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($attempts));
            fflush($handle);
            return false;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

if (!function_exists('clearRateLimit')) {
    function clearRateLimit(string $scope, string $identity): void
    {
        $key = hash('sha256', $scope . '|' . $identity);
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'skillconnect_rl_' . $key . '.json';
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

if (!function_exists('normalizeSecurityAnswer')) {
    function normalizeSecurityAnswer(string $answer): string
    {
        $answer = trim($answer);
        return function_exists('mb_strtolower') ? mb_strtolower($answer, 'UTF-8') : strtolower($answer);
    }
}
