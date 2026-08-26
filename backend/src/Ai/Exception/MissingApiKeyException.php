<?php

declare(strict_types=1);

namespace App\Ai\Exception;

/**
 * Thrown when an LLM provider is invoked without the API key required to
 * reach the remote service being configured.
 *
 * This must NEVER be thrown at construction/boot time: providers must be
 * instantiable (and the DI container must compile) without any API key
 * configured, so that the CRM keeps working when the AI feature is not
 * set up. It is only thrown when an actual call is attempted.
 */
class MissingApiKeyException extends LLMProviderException
{
}
