<?php

declare(strict_types=1);

namespace App\Tests\Service\Telegram;

use App\Service\Telegram\Exception\TelegramApiException;
use App\Service\Telegram\Exception\TelegramTransportException;
use App\Service\Telegram\TelegramBotClient;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TelegramBotClientTest extends TestCase
{
    public function testSendMessageReturnsDecodedResponse(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.telegram.org/bottest-token/sendMessage', $url);
            self::assertSame([
                'chat_id' => 123,
                'text' => 'Hello',
            ], json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR));
            self::assertSame(10.0, $options['timeout']);

            return new MockResponse('{"ok":true,"result":{"message_id":1}}');
        });

        $client = new TelegramBotClient($httpClient, 'test-token');

        self::assertSame([
            'ok' => true,
            'result' => [
                'message_id' => 1,
            ],
        ], $client->sendMessage(123, 'Hello'));
    }

    public function testGetMeReturnsDecodedResponse(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.telegram.org/bottest-token/getMe', $url);
            self::assertSame([], json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR));

            return new MockResponse('{"ok":true,"result":{"id":1,"is_bot":true}}');
        });

        $client = new TelegramBotClient($httpClient, 'test-token');

        self::assertSame([
            'ok' => true,
            'result' => [
                'id' => 1,
                'is_bot' => true,
            ],
        ], $client->getMe());
    }

    public function testSendMessageAddsOptionsToPayload(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.telegram.org/bottest-token/sendMessage', $url);
            self::assertSame([
                'chat_id' => '@channel_name',
                'text' => 'Formatted message',
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
                'reply_to_message_id' => 42,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Open', 'url' => 'https://example.com'],
                        ],
                    ],
                ],
            ], json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR));

            return new MockResponse('{"ok":true,"result":{"message_id":7}}');
        });

        $client = new TelegramBotClient($httpClient, 'test-token');

        $response = $client->sendMessage('@channel_name', 'Formatted message', [
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_to_message_id' => 42,
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Open', 'url' => 'https://example.com'],
                    ],
                ],
            ],
        ]);

        self::assertSame(7, $response['result']['message_id']);
    }

    public function testRequestThrowsApiExceptionWhenTelegramReturnsError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"ok":false,"error_code":400,"description":"Bad Request: chat not found"}', [
                'http_code' => 400,
            ]),
        ]);

        $client = new TelegramBotClient($httpClient, 'test-token');

        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessage('Telegram API error on "sendMessage" [400]: Bad Request: chat not found');

        try {
            $client->sendMessage(123, 'Hello');
        } catch (TelegramApiException $exception) {
            self::assertSame('sendMessage', $exception->getTelegramMethod());
            self::assertSame(400, $exception->getTelegramErrorCode());
            self::assertSame('Bad Request: chat not found', $exception->getTelegramDescription());
            self::assertSame(
                '{"ok":false,"error_code":400,"description":"Bad Request: chat not found"}',
                $exception->getResponseBody(),
            );

            throw $exception;
        }
    }

    public function testRequestThrowsTransportExceptionOnNetworkFailure(): void
    {
        $httpClient = new MockHttpClient(static function (): never {
            throw new TransportException('Connection failed');
        });

        $client = new TelegramBotClient($httpClient, 'test-token');

        $this->expectException(TelegramTransportException::class);
        $this->expectExceptionMessage('Telegram request "getMe" failed due to a transport error.');

        $client->getMe();
    }

    public function testRequestThrowsTransportExceptionOnUnexpectedHttpStatus(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"ok":true,"result":{"id":1}}', [
                'http_code' => 503,
            ]),
        ]);

        $client = new TelegramBotClient($httpClient, 'test-token');

        $this->expectException(TelegramTransportException::class);
        $this->expectExceptionMessage('Telegram request "getMe" failed with HTTP status 503.');

        $client->getMe();
    }

    public function testRequestThrowsApiExceptionOnInvalidJson(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('not-json'),
        ]);

        $client = new TelegramBotClient($httpClient, 'test-token');

        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessage('Telegram API response for "getMe" is not valid JSON.');

        try {
            $client->getMe();
        } catch (TelegramApiException $exception) {
            self::assertSame('getMe', $exception->getTelegramMethod());
            self::assertNull($exception->getTelegramErrorCode());
            self::assertNull($exception->getTelegramDescription());
            self::assertSame('not-json', $exception->getResponseBody());

            throw $exception;
        }
    }

    public function testConstructorRejectsEmptyToken(): void
    {
        $httpClient = new MockHttpClient();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Telegram bot token must not be empty.');

        new TelegramBotClient($httpClient, '   ');
    }
}
