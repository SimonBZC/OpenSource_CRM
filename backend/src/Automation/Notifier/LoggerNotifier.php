<?php

declare(strict_types=1);

namespace App\Automation\Notifier;

use Psr\Log\LoggerInterface;

/**
 * Implémentation VOLONTAIREMENT stub de NotifierInterface : "notifier"
 * consiste ici uniquement à écrire un log structuré (niveau info). Aucun
 * envoi réel (email, push, SMS...) n'est fait — c'est explicitement hors
 * scope de cette phase. Le jour où un vrai canal est nécessaire, il suffit
 * d'ajouter une nouvelle implémentation de NotifierInterface et de changer
 * le service utilisé, sans toucher aux règles d'automatisation.
 *
 * Pas de canal Monolog dédié "automation" : MonologBundle n'est pas
 * installé dans ce projet (seul le logger minimal de
 * Symfony\Component\HttpKernel\Log\Logger est disponible via
 * Psr\Log\LoggerInterface). Ajouter monolog-bundle uniquement pour un canal
 * séparé aurait été une nouvelle dépendance non indispensable ; le préfixe
 * "[automation]" dans les messages permet de filtrer les logs facilement en
 * attendant.
 */
final class LoggerNotifier implements NotifierInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notify(string $message, array $context = []): void
    {
        $this->logger->info('[automation] '.$message, $context);
    }
}
