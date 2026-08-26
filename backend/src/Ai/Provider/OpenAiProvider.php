<?php

declare(strict_types=1);

namespace App\Ai\Provider;

use App\Ai\Exception\LLMRequestFailedException;
use App\Ai\Exception\MissingApiKeyException;
use App\Ai\LLMProviderInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAI implementation of LLMProviderInterface, using the Chat Completions
 * API (https://platform.openai.com/docs/api-reference/chat).
 *
 * This class can always be instantiated (and therefore autowired by the DI
 * container) without an API key: the container must compile and the app
 * must boot even when OPENAI_API_KEY is not configured. The missing-key
 * check only happens when an actual call is made, via
 * MissingApiKeyException.
 */
final class OpenAiProvider implements LLMProviderInterface
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $openAiApiKey = '',
        private readonly string $openAiModel = 'gpt-4o-mini',
        private readonly int $openAiTimeout = 30,
    ) {
    }

    public function complete(string $prompt, ?string $systemPrompt = null): string
    {
        $response = $this->request($prompt, $systemPrompt, jsonMode: false);

        $content = $response['choices'][0]['message']['content'] ?? null;

        if (!\is_string($content) || '' === $content) {
            throw new LLMRequestFailedException('OpenAI response did not contain any completion content.');
        }

        return $content;
    }

    public function completeJson(string $prompt, ?string $systemPrompt = null): array
    {
        $response = $this->request($prompt, $systemPrompt, jsonMode: true);

        $content = $response['choices'][0]['message']['content'] ?? null;

        if (!\is_string($content) || '' === $content) {
            throw new LLMRequestFailedException('OpenAI response did not contain any completion content.');
        }

        $decoded = json_decode($this->stripMarkdownFences($content), true);

        if (!\is_array($decoded)) {
            throw new LLMRequestFailedException('OpenAI response content is not valid JSON.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string, mixed> decoded response body
     */
    private function request(string $prompt, ?string $systemPrompt, bool $jsonMode): array
    {
        if ('' === trim($this->openAiApiKey)) {
            throw new MissingApiKeyException(
                'No OpenAI API key configured: set the OPENAI_API_KEY environment variable to use the AI features.'
            );
        }

        $messages = [];
        if (null !== $systemPrompt && '' !== $systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $payload = [
            'model' => $this->openAiModel,
            'messages' => $messages,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->openAiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => $this->openAiTimeout,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->toArray(throw: false);
        } catch (HttpClientExceptionInterface $e) {
            throw new LLMRequestFailedException('Failed to call the OpenAI API: '.$e->getMessage(), 0, $e);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorMessage = $body['error']['message'] ?? 'unknown error';
            throw new LLMRequestFailedException(
                sprintf('OpenAI API returned an error (HTTP %d): %s', $statusCode, $errorMessage)
            );
        }

        return $body;
    }

    private function stripMarkdownFences(string $content): string
    {
        $trimmed = trim($content);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $trimmed, $matches)) {
            return $matches[1];
        }

        return $trimmed;
    }
}
