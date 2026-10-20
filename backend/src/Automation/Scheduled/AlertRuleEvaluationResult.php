<?php

declare(strict_types=1);

namespace App\Automation\Scheduled;

/**
 * Bilan d'une vérification des règles : nombre total d'alertes créées et
 * détail par type de règle (0 pour une règle désactivée).
 */
final readonly class AlertRuleEvaluationResult
{
    /**
     * @param array<string, int> $byRule nombre d'alertes créées, indexé par valeur d'AlertRuleType
     */
    public function __construct(
        public int $created,
        public array $byRule,
    ) {
    }

    /**
     * @return array{created: int, byRule: array<string, int>}
     */
    public function toArray(): array
    {
        return ['created' => $this->created, 'byRule' => $this->byRule];
    }
}
