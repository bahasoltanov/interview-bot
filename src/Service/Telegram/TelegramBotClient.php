<?php

declare(strict_types=1);

namespace App\Service\Telegram;

use App\Service\Telegram\Exception\TelegramApiException;
use App\Service\Telegram\Exception\TelegramTransportException;
use InvalidArgumentException;
use JsonException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TelegramBotClient
{
    private const float TIMEOUT_SECONDS = 10.0;

    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $botToken,
    ) {
        $botToken = trim($botToken);
        if ($botToken === '') {
            throw new InvalidArgumentException('Telegram bot token must not be empty.');
        }

        $this->baseUrl = sprintf('https://api.telegram.org/bot%s', $botToken);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function sendMessage(int|string $chatId, string $text, array $options = []): array
    {
        return $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ] + $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMe(): array
    {
        return $this->request('getMe');
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, array $payload = []): array
    {
        try {
            $response = $this->httpClient->request('POST', sprintf('%s/%s', $this->baseUrl, $method), [
                'json' => $payload,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(throw: false);
        } catch (HttpExceptionInterface $exception) {
            $statusCode = $exception->getResponse()->getInfo('http_code');

            if (is_int($statusCode) && $statusCode > 0) {
                throw TelegramTransportException::fromHttpStatus($method, $statusCode, $exception);
            }

            throw TelegramTransportException::fromTransportFailure($method, $exception);
        } catch (TransportExceptionInterface $exception) {
            throw TelegramTransportException::fromTransportFailure($method, $exception);
        }

        try {
            $decodedResponse = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw TelegramApiException::invalidJson($method, $responseBody, $exception);
        }

        if (!is_array($decodedResponse)) {
            throw TelegramApiException::unexpectedResponse($method, $responseBody);
        }

        if (($decodedResponse['ok'] ?? null) !== true) {
            throw TelegramApiException::fromResponse($method, $decodedResponse, $responseBody);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw TelegramTransportException::fromHttpStatus($method, $statusCode);
        }

        return $decodedResponse;
    }
}
