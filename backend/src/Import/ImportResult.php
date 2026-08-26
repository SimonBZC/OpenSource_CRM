<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Opportunity;

/**
 * Résultat structuré d'une tentative d'import d'opportunité, produit par un
 * App\Import\OpportunityImporterInterface.
 *
 * Value object immuable : utiliser exclusivement les constructeurs nommés
 * statiques ci-dessous pour garantir un état cohérent (ex. jamais de
 * `messages` sur un CREATED, jamais d'`opportunity` sur un VALIDATION_ERROR).
 */
final readonly class ImportResult
{
    /**
     * @param list<string> $messages
     */
    private function __construct(
        public ImportStatus $status,
        public ?Opportunity $opportunity = null,
        public array $messages = [],
    ) {
    }

    public static function created(Opportunity $opportunity): self
    {
        return new self(ImportStatus::CREATED, $opportunity);
    }

    public static function duplicate(Opportunity $existing): self
    {
        return new self(ImportStatus::DUPLICATE, $existing);
    }

    /**
     * @param list<string> $messages
     */
    public static function validationError(array $messages): self
    {
        return new self(ImportStatus::VALIDATION_ERROR, null, $messages);
    }

    public static function notImplemented(string $message): self
    {
        return new self(ImportStatus::NOT_IMPLEMENTED, null, [$message]);
    }

    public function getStatus(): ImportStatus
    {
        return $this->status;
    }

    public function getOpportunity(): ?Opportunity
    {
        return $this->opportunity;
    }

    /**
     * @return list<string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Seul CREATED est considéré comme un succès : DUPLICATE n'a rien créé
     * (même si une Opportunity existante est exposée), VALIDATION_ERROR et
     * NOT_IMPLEMENTED sont des échecs.
     */
    public function isSuccess(): bool
    {
        return ImportStatus::CREATED === $this->status;
    }
}
