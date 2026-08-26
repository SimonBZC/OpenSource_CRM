<?php

declare(strict_types=1);

namespace App\Automation;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Point d'entrée UNIQUE pour l'émission des événements applicatifs.
 *
 * Le dispatcher Symfony (EventDispatcherInterface) route les écouteurs par
 * nom de CLASSE EXACT de l'événement : un listener enregistré sur une
 * interface ou une classe parente n'est jamais appelé. Pour que le système
 * reste réellement ouvert à l'extension (nouvel événement = nouvelle
 * classe, nouvelle règle = nouvelle classe, zéro modification de
 * l'existant), tous les émetteurs de l'application n'utilisent QUE ce bus,
 * qui :
 *   1. forwarde l'événement à l'EventDispatcherInterface Symfony standard,
 *      pour les listeners/subscribers classiques si besoin un jour ;
 *   2. le fait aussi transiter par le registre de règles d'automatisation
 *      (App\Automation\AutomationRuleInterface), taggées automatiquement
 *      via #[AutoconfigureTag('app.automation_rule')] et collectées ici par
 *      tagged_iterator.
 *
 * Robustesse : une règle qui lève une exception est capturée et journalisée
 * en `error`, puis l'exécution continue avec les règles suivantes. Le choix
 * assumé est qu'un défaut d'automatisation (ex. échec de création d'une
 * tâche auto-générée) ne doit JAMAIS faire échouer la requête HTTP qui a
 * déclenché l'événement (ex. POST /api/opportunities/{id}/analyze doit
 * continuer à répondre 200 même si une règle plante).
 */
final class DomainEventBus
{
    /**
     * @param iterable<AutomationRuleInterface> $rules
     */
    public function __construct(
        #[TaggedIterator('app.automation_rule')]
        private readonly iterable $rules,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function dispatch(object $event): void
    {
        $this->dispatcher->dispatch($event);

        foreach ($this->rules as $rule) {
            if (!$rule->supports($event)) {
                continue;
            }

            try {
                $rule->execute($event);

                $this->logger->info(sprintf('[automation] Règle "%s" exécutée.', $rule->getName()), [
                    'rule' => $rule->getName(),
                    'event' => $event::class,
                ]);
            } catch (\Throwable $e) {
                // Une règle qui échoue ne doit jamais casser le flux appelant
                // (ex. la requête HTTP /analyze doit quand même répondre 200).
                $this->logger->error(sprintf('[automation] Règle "%s" en échec : %s', $rule->getName(), $e->getMessage()), [
                    'rule' => $rule->getName(),
                    'event' => $event::class,
                    'exception' => $e,
                ]);
            }
        }
    }
}
