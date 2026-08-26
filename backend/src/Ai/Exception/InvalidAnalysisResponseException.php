<?php

declare(strict_types=1);

namespace App\Ai\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Thrown by App\Ai\OpportunityAnalyzer when the LLM response cannot be
 * turned into a valid App\Ai\Dto\OpportunityAnalysisResult: malformed JSON,
 * missing/invalid fields, or Symfony Validator constraint violations.
 *
 * The analyzer never returns a partially-valid or unvalidated DTO: it
 * either returns a valid result or throws this exception.
 */
class InvalidAnalysisResponseException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?ConstraintViolationListInterface $violations = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getViolations(): ?ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
