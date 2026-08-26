<?php

declare(strict_types=1);

namespace App\Ai\Exception;

/**
 * Thrown when a call to the LLM provider fails at the transport level
 * (network error, non-2xx HTTP response, unreadable/unexpected response
 * body, timeout...). Wraps the original throwable as the previous
 * exception without leaking its type to callers.
 */
class LLMRequestFailedException extends LLMProviderException
{
}
