<?php

declare(strict_types=1);

namespace App\Automation\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Automation\DomainEventBus;
use App\Automation\Event\OpportunityCreated;
use App\Entity\Opportunity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Décore le processor de persistance Doctrine par défaut d'API Platform,
 * mais UNIQUEMENT pour l'opération Post de la ressource Opportunity (voir
 * `processor: OpportunityCreateProcessor::class` sur #[Post] dans
 * App\Entity\Opportunity) : le service partagé
 * `api_platform.doctrine.orm.state.persist_processor` est aussi utilisé par
 * Put/Patch et par toutes les autres ressources ApiResource ; le décorer
 * globalement (via `decorates:` dans services.yaml) aurait déclenché
 * l'événement OpportunityCreated sur des mises à jour et sur d'autres
 * entités. Le limiter au niveau de l'opération est le point d'accroche le
 * plus sûr pour n'émettre l'événement qu'à la création d'une Opportunity.
 *
 * Volontairement PAS un listener Doctrine postPersist : un tel listener
 * s'exécute PENDANT le flush() en cours et un flush() imbriqué déclenché
 * depuis une règle d'automatisation à cet instant est risqué (état
 * incohérent de l'UnitOfWork). Ici, l'événement est émis APRÈS que le
 * processor de persistance délégué a terminé (donc après le flush), ce qui
 * rend un flush() supplémentaire déclenché par une règle parfaitement sûr.
 */
final class OpportunityCreateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $decorated,
        private readonly DomainEventBus $eventBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        if ($result instanceof Opportunity) {
            $this->eventBus->dispatch(new OpportunityCreated($result));
        }

        return $result;
    }
}
