<?php

declare(strict_types=1);

namespace App\Ai\Exception;

/**
 * Base exception for all errors originating from an LLM provider
 * (App\Ai\LLMProviderInterface implementations).
 *
 * Concrete providers (e.g. OpenAiProvider) must always wrap low-level
 * SDK/HTTP exceptions into a subclass of this exception. Callers of
 * LLMProviderInterface should never have to catch a third-party
 * exception type.
 */
class LLMProviderException extends \RuntimeException
{
}
