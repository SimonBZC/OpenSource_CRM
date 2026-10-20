<?php

declare(strict_types=1);

namespace App\Automation\Scheduled;

use App\Entity\Contact;
use App\Entity\Opportunity;

/**
 * Alerte à créer proposée par une règle configurable. La cible de
 * déduplication est l'opportunité si elle est renseignée, sinon le contact.
 */
final readonly class AlertCandidate
{
    public function __construct(
        public string $title,
        public string $description,
        public ?Opportunity $opportunity = null,
        public ?Contact $contact = null,
    ) {
    }
}
