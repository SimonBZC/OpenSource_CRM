<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\FollowUp;
use App\Enum\FollowUpStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Refuse la suppression d'une relance terminée (statut `done`), puis délègue
 * au processor de suppression Doctrine par défaut d'API Platform.
 *
 * Branché sur l'opération Delete de App\Entity\FollowUp. Le refus est un 409 :
 * la requête est valide, mais l'état de la relance l'interdit. Une relance
 * annulée ou à faire reste supprimable.
 */
final class FollowUpDeletionGuardProcessor implements ProcessorInterface
{
    public const DONE_FOLLOW_UP_MESSAGE = 'Une relance terminée ne peut pas être supprimée.';

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private readonly ProcessorInterface $decorated,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof FollowUp && FollowUpStatus::DONE === $data->getStatus()) {
            throw new ConflictHttpException(self::DONE_FOLLOW_UP_MESSAGE);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
