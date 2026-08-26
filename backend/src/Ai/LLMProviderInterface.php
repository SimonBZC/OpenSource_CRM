<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Exception\LLMProviderException;
use App\Ai\Exception\LLMRequestFailedException;
use App\Ai\Exception\MissingApiKeyException;

/**
 * Agnostic contract for a text-completion LLM provider.
 *
 * Implementations MUST NOT expose any third-party SDK/HTTP client type in
 * their public signature: only PHP native types are used here, so that
 * App\Ai\OpportunityAnalyzer (and, more broadly, the business domain) can
 * depend on this interface without ever coupling to a specific vendor
 * (OpenAI, Claude, Mistral...).
 *
 * Implementations MUST be instantiable without any credentials configured
 * (e.g. missing API key): the exception below is thrown when a method is
 * actually called, never at construction/boot time.
 */
interface LLMProviderInterface
{
    /**
     * Requests a free-form text completion from the LLM.
     *
     * @param string      $prompt       the user/task prompt
     * @param string|null $systemPrompt optional system-level instructions
     *
     * @throws MissingApiKeyException when the provider has no credentials configured
     * @throws LLMRequestFailedException when the call fails at the transport level
     *                                    (network error, non-2xx response, unreadable body...)
     * @throws LLMProviderException for any other provider-level failure
     */
    public function complete(string $prompt, ?string $systemPrompt = null): string;

    /**
     * Requests a completion from the LLM and returns it decoded as an
     * associative array (the provider is responsible for instructing the
     * model to answer in JSON and for parsing that JSON).
     *
     * This method does NOT validate the structure/content of the array:
     * callers (e.g. App\Ai\OpportunityAnalyzer) are responsible for mapping
     * and validating it against their own expected shape.
     *
     * @param string      $prompt       the user/task prompt
     * @param string|null $systemPrompt optional system-level instructions
     *
     * @return array<string, mixed>
     *
     * @throws MissingApiKeyException when the provider has no credentials configured
     * @throws LLMRequestFailedException when the call fails at the transport level,
     *                                    or when the response is not valid JSON
     * @throws LLMProviderException for any other provider-level failure
     */
    public function completeJson(string $prompt, ?string $systemPrompt = null): array;
}
