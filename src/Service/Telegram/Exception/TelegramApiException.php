<?php

declare(strict_types=1);

namespace App\Service\Telegram\Exception;

use RuntimeException;
use Throwable;

final class TelegramApiException extends RuntimeException
{
    public function __construct(
        private readonly string $telegramMethod,
        string $message,
        private readonly ?int $telegramErrorCode = null,
        private readonly ?string $telegramDescription = null,
        private readonly ?string $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $telegramErrorCode ?? 0, $previous);
    }

    public static function fromResponse(string $telegramMethod, array $response, ?string $responseBody = null): self
    {
        $errorCode = isset($response['error_code']) && is_int($response['error_code']) ? $response['error_code'] : null;
        $description = isset($response['description']) && is_string($response['description'])
            ? $response['description']
            : 'Telegram API returned an unknown error.';

        $message = $errorCode === null
            ? sprintf('Telegram API error on "%s": %s', $telegramMethod, $description)
            : sprintf('Telegram API error on "%s" [%d]: %s', $telegramMethod, $errorCode, $description);

        return new self($telegramMethod, $message, $errorCode, $description, $responseBody);
    }

    public static function invalidJson(string $telegramMethod, ?string $responseBody = null, ?Throwable $previous = null): self
    {
        return new self(
            $telegramMethod,
            sprintf('Telegram API response for "%s" is not valid JSON.', $telegramMethod),
            responseBody: $responseBody,
            previous: $previous,
        );
    }

    public static function unexpectedResponse(string $telegramMethod, ?string $responseBody = null): self
    {
        return new self(
            $telegramMethod,
            sprintf('Telegram API response for "%s" has an unexpected format.', $telegramMethod),
            responseBody: $responseBody,
        );
    }

    public function getTelegramMethod(): string
    {
        return $this->telegramMethod;
    }

    public function getTelegramErrorCode(): ?int
    {
        return $this->telegramErrorCode;
    }

    public function getTelegramDescription(): ?string
    {
        return $this->telegramDescription;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
