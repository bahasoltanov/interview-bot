<?php

declare(strict_types=1);

namespace App\Service\Telegram\Exception;

use RuntimeException;
use Throwable;

final class TelegramTransportException extends RuntimeException
{
    public function __construct(
        private readonly string $telegramMethod,
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromTransportFailure(string $telegramMethod, ?Throwable $previous = null): self
    {
        return new self(
            $telegramMethod,
            sprintf('Telegram request "%s" failed due to a transport error.', $telegramMethod),
            previous: $previous,
        );
    }

    public static function fromHttpStatus(string $telegramMethod, int $statusCode, ?Throwable $previous = null): self
    {
        return new self(
            $telegramMethod,
            sprintf('Telegram request "%s" failed with HTTP status %d.', $telegramMethod, $statusCode),
            $statusCode,
            $previous,
        );
    }

    public function getTelegramMethod(): string
    {
        return $this->telegramMethod;
    }
}
