<?php
declare(strict_types=1);

namespace App\Core;

/**
 * One place that turns every problem into a proper HTTP status code and a friendly page.
 * Technical details go to the log file, and are shown on screen only when debug is on.
 */
final class ErrorHandler
{
    private const PAGES = [400, 403, 404, 500];

    private static bool $debug = false;

    public static function register(bool $debug): void
    {
        self::$debug = $debug;

        error_reporting(E_ALL);
        ini_set('display_errors', '0');   // never print raw PHP errors to visitors

        // Turn warnings and notices into exceptions so they are handled like any other error.
        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $level)) {
                return false;
            }
            throw new \ErrorException($message, 0, $level, $file, $line);
        });

        set_exception_handler([self::class, 'handle']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handle(\Throwable $e): void
    {
        if ($e instanceof HttpException) {
            $code    = $e->statusCode();
            $message = $e->getMessage();
        } else {
            $code    = 500;
            $message = '';
        }

        if ($code >= 500) {
            Logger::error(sprintf('%s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
        } else {
            Logger::info(sprintf('HTTP %d for %s %s', $code, $_SERVER['REQUEST_METHOD'] ?? '-', $_SERVER['REQUEST_URI'] ?? '-'));
        }

        self::render($code, $message, $e);
    }

    /** Catches fatal errors that cannot be thrown as exceptions. */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

        if ($error !== null && in_array($error['type'], $fatal, true)) {
            Logger::error(sprintf('Fatal error: %s in %s:%d', $error['message'], $error['file'], $error['line']));
            if (!headers_sent()) {
                self::render(500, '', null);
            }
        }
    }

    private static function render(int $code, string $message, ?\Throwable $e): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($code);
        }

        $debugDetail = null;
        if (self::$debug && $e !== null) {
            $debugDetail = get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine();
        }

        $page = in_array($code, self::PAGES, true) ? $code : 500;

        try {
            require BASE_PATH . '/app/Views/errors/' . $page . '.php';
        } catch (\Throwable) {
            echo 'Something went wrong. Please try again later.';
        }
    }
}
