<?php

declare(strict_types=1);

namespace App\Tests\Ai\Provider;

use App\Ai\Exception\LLMRequestFailedException;
use App\Ai\Exception\MissingApiKeyException;
use App\Ai\Provider\OpenAiProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenAiProviderTest extends TestCase
{
    public function testCompleteThrowsMissingApiKeyExceptionWhenKeyIsEmpty(): void
    {
        $httpClient = new MockHttpClient();
        $provider = new OpenAiProvider($httpClient);

        $this->expectException(MissingApiKeyException::class);
        $this->expectExceptionMessageMatches('/OPENAI_API_KEY/');

        try {
            $provider->complete('hello');
        } finally {
            self::assertSame(0, $httpClient->getRequestsCount(), 'No HTTP request should have been emitted when the API key is missing.');
        }
    }

    public function testCompleteJsonThrowsMissingApiKeyExceptionWhenKeyIsEmpty(): void
    {
        $httpClient = new MockHttpClient();
        $provider = new OpenAiProvider($httpClient);

        $this->expectException(MissingApiKeyException::class);
        $this->expectExceptionMessageMatches('/OPENAI_API_KEY/');

        try {
            $provider->completeJson('hello');
        } finally {
            self::assertSame(0, $httpClient->getRequestsCount(), 'No HTTP request should have been emitted when the API key is missing.');
        }
    }

    public function testCompleteThrowsMissingApiKeyExceptionWhenKeyIsBlank(): void
    {
        $httpClient = new MockHttpClient();
        $provider = new OpenAiProvider($httpClient, '   ');

        $this->expectException(MissingApiKeyException::class);

        try {
            $provider->complete('hello');
        } finally {
            self::assertSame(0, $httpClient->getRequestsCount());
        }
    }

    public function testConstructingWithoutApiKeyDoesNotThrow(): void
    {
        $provider = new OpenAiProvider(new MockHttpClient());

        self::assertInstanceOf(OpenAiProvider::class, $provider);
    }

    public function testCompleteReturnsTextContentAndSendsExpectedRequest(): void
    {
        $response = new MockResponse(json_encode([
            'choices' => [
                ['message' => ['content' => 'Hello, world!']],
            ],
        ], \JSON_THROW_ON_ERROR));

        $httpClient = new MockHttpClient($response, 'https://api.openai.com');
        $provider = new OpenAiProvider($httpClient, 'sk-test-key', 'gpt-4o-mini');

        $result = $provider->complete('Say hello', 'You are a helpful assistant.');

        self::assertSame('Hello, world!', $result);
        self::assertSame(1, $httpClient->getRequestsCount());
        self::assertSame('POST', $response->getRequestMethod());
        self::assertSame('https://api.openai.com/v1/chat/completions', $response->getRequestUrl());

        $requestOptions = $response->getRequestOptions();
        self::assertArrayHasKey('headers', $requestOptions);
        $authHeaderFound = false;
        foreach ($requestOptions['headers'] as $header) {
            if (str_starts_with($header, 'Authorization: Bearer sk-test-key')) {
                $authHeaderFound = true;
            }
        }
        self::assertTrue($authHeaderFound, 'Authorization header with the API key was not sent.');

        $payload = json_decode($requestOptions['body'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('gpt-4o-mini', $payload['model']);
        self::assertSame('You are a helpful assistant.', $payload['messages'][0]['content']);
        self::assertSame('system', $payload['messages'][0]['role']);
        self::assertSame('Say hello', $payload['messages'][1]['content']);
    }

    public function testCompleteJsonReturnsDecodedArrayAndSetsResponseFormat(): void
    {
        $response = new MockResponse(json_encode([
            'choices' => [
                ['message' => ['content' => '{"score": 80, "relevant": true}']],
            ],
        ], \JSON_THROW_ON_ERROR));

        $httpClient = new MockHttpClient($response, 'https://api.openai.com');
        $provider = new OpenAiProvider($httpClient, 'sk-test-key');

        $result = $provider->completeJson('Analyze this');

        self::assertSame(['score' => 80, 'relevant' => true], $result);

        $requestOptions = $response->getRequestOptions();
        $payload = json_decode($requestOptions['body'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['type' => 'json_object'], $payload['response_format']);
    }

    public function testCompleteJsonStripsMarkdownFences(): void
    {
        $response = new MockResponse(json_encode([
            'choices' => [
                ['message' => ['content' => "```json\n{\"foo\": \"bar\"}\n```"]],
            ],
        ], \JSON_THROW_ON_ERROR));

        $httpClient = new MockHttpClient($response, 'https://api.openai.com');
        $provider = new OpenAiProvider($httpClient, 'sk-test-key');

        $result = $provider->completeJson('Analyze this');

        self::assertSame(['foo' => 'bar'], $result);
    }

    public function testCompleteThrowsLLMRequestFailedExceptionOn401(): void
    {
        $response = new MockResponse(
            json_encode(['error' => ['message' => 'Invalid API key']], \JSON_THROW_ON_ERROR),
            ['http_code' => 401],
        );

        $httpClient = new MockHttpClient($response, 'https://api.openai.com');
        $provider = new OpenAiProvider($httpClient, 'sk-test-key');

        try {
            $provider->complete('hello');
            self::fail('Expected LLMRequestFailedException to be thrown.');
        } catch (LLMRequestFailedException $e) {
            self::assertStringContainsString('401', $e->getMessage());
            self::assertStringContainsString('Invalid API key', $e->getMessage());
        }
    }

    public function testCompleteThrowsLLMRequestFailedExceptionOn500(): void
    {
        $response = new MockResponse('{}', ['http_code' => 500]);

        $httpClient = new MockHttpClient($response, 'https://api.openai.com');
        $provider = new OpenAiProvider($httpClient, 'sk-test-key');

        try {
            $provider->complete('hello');
            self::fail('Expected LLMRequestFailedException to be thrown.');
        } catch (LLMRequestFailedException $e) {
            self::assertStringContainsString('500', $e->getMessage());
        }
    }

    public function testCompleteThrowsLLMRequestFailedExceptionWhenContentIsEmpty(): void
    {
        $response = new MockResponse(json_encode([
            'choices' => [
                ['message' => ['content' => '']],
            ],
        ], \JSON_THROW_ON_ERROR));

        $httpClient = new MockHttpClient($response, 'https://api.openai.com');
        $provider = new OpenAiProvider($httpClient, 'sk-test-key');

        $this->expectException(LLMRequestFailedException::class);

        $provider->complete('hello');
    }

    public function testCompleteThrowsLLMRequestFailedExceptionWhenChoicesAreMissing(): void
    {
        $response = new MockResponse(json_encode(['id' => 'abc'], \JSON_THROW_ON_ERROR));

        $httpClient = new MockHttpClient($response, 'https://api.openai.com');
        $provider = new OpenAiProvider($httpClient, 'sk-test-key');

        $this->expectException(LLMRequestFailedException::class);

        $provider->complete('hello');
    }

    public function testCompleteJsonThrowsLLMRequestFailedExceptionWhenContentIsNotJson(): void
    {
        $response = new MockResponse(json_encode([
            'choices' => [
                ['message' => ['content' => 'this is not json at all']],
            ],
        ], \JSON_THROW_ON_ERROR));

        $httpClient = new MockHttpClient($response, 'https://api.openai.com');
        $provider = new OpenAiProvider($httpClient, 'sk-test-key');

        $this->expectException(LLMRequestFailedException::class);

        $provider->completeJson('hello');
    }

    public function testCompleteThrowsLLMRequestFailedExceptionOnTransportError(): void
    {
        $response = new MockResponse('', ['error' => 'Connection timed out']);

        $httpClient = new MockHttpClient($response, 'https://api.openai.com');
        $provider = new OpenAiProvider($httpClient, 'sk-test-key');

        try {
            $provider->complete('hello');
            self::fail('Expected LLMRequestFailedException to be thrown.');
        } catch (LLMRequestFailedException $e) {
            self::assertStringContainsString('Connection timed out', $e->getMessage());
        } catch (\Throwable $e) {
            self::fail(\sprintf('Expected LLMRequestFailedException, got %s leaking from the HTTP client: %s', $e::class, $e->getMessage()));
        }
    }
}
