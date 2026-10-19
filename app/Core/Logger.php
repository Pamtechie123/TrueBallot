<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Writes one line per event to storage/logs/app-YYYY-MM-DD.log.
 * Never pass passwords or other secrets to this class.
 */
final class Logger
{
    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    public static function warning(string $message): void
    {
        self::write('WARNING', $message);
    }

    public static function error(string $message): void
    {
        self::write('ERROR', $message);
    }

    private static function write(string $level, string $message): void
    {
        $folder = BASE_PATH . '/storage/logs';

        try {
            if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
                throw new \RuntimeException('Cannot create the log folder.');
            }

            $line = sprintf(
                "[%s] %s %s\n",
                date('Y-m-d H:i:s'),
                $level,
                str_replace(["\r", "\n"], ' ', $message)
            );

            if (file_put_contents($folder . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX) === false) {
                throw new \RuntimeException('Cannot write to the log file.');
            }
        } catch (\Throwable) {
            // Logging must never break the page. Fall back to the web server's own log.
            error_log("TrueBallot [$level] $message");
        }
    }
}
