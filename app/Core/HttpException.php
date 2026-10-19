<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Throw this to stop a request with a specific HTTP status code,
 * for example: throw new HttpException(404, 'Page not found');
 */
final class HttpException extends \RuntimeException
{
    public function __construct(private int $statusCode, string $message = '')
    {
        parent::__construct($message, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
