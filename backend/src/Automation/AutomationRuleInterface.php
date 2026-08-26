<?php

declare(strict_types=1);

namespace App\Automation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contrat d'une règle d'automatisation déclenchée par un événement
 * applicatif (voir App\Automation\Event\*).
 *
 * Ajouter une nouvelle règle = créer une nouvelle classe qui implémente
 * cette interface (elle est automatiquement enregistrée dans
 * App\Automation\DomainEventBus grâce au tag ci-dessous) : aucune
 * modification du code existant n'est nécessaire (open/closed).
 */
#[AutoconfigureTag('app.automation_rule')]
interface AutomationRuleInterface
{
    /**
     * Indique si cette règle doit réagir à l'événement donné.
     */
    public function supports(object $event): bool;

    /**
     * Exécute la règle. Ne doit être appelée que si supports() a renvoyé
     * true. Toute exception levée ici est capturée par
     * App\Automation\DomainEventBus et journalisée : elle ne doit jamais
     * faire échouer le flux principal qui a émis l'événement.
     */
    public function execute(object $event): void;

    /**
     * Nom court et stable de la règle, utilisé pour la traçabilité dans les
     * logs (canal automation).
     */
    public function getName(): string;
}
