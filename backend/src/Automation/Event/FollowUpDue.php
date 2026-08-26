<?php

declare(strict_types=1);

namespace App\Automation\Event;

use App\Entity\FollowUp;

/**
 * Événement applicatif émis pour chaque relance (FollowUp) considérée comme
 * échue par la commande App\Command\CheckDueFollowUpsCommand.
 */
final class FollowUpDue
{
    public function __construct(
        public readonly FollowUp $followUp,
    ) {
    }
}
