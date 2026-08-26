<?php

declare(strict_types=1);

namespace App\Automation\Notifier;

/**
 * Abstraction minimale d'un canal de notification, pour permettre de
 * remplacer plus tard le stub de journalisation (LoggerNotifier) par un
 * vrai canal (email, push, SMS...) sans changer les règles d'automatisation
 * qui l'utilisent.
 */
interface NotifierInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function notify(string $message, array $context = []): void;
}
