<?php

declare(strict_types=1);

namespace App\Automation\Scheduled;

use App\Entity\Alert;
use App\Enum\AlertStatus;
use App\Repository\AlertRepository;
use App\Repository\AlertRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Évalue les règles d'alerte configurables (Paramètres > Règles) et crée les
 * alertes manquantes. Appelé uniquement par le bouton « Vérifier les
 * règles » de la page Alertes (POST /api/alert_rules/evaluate) : aucune
 * tâche planifiée.
 *
 * Pour chaque règle activée, chaque cible proposée par la classe du type
 * correspondant donne une alerte (priorité de la règle, `ruleType`
 * renseigné), sauf si une alerte de la même règle est encore ouverte
 * (« À faire » ou « En cours ») pour la même cible. Une alerte terminée ou
 * annulée n'empêche pas d'en créer une nouvelle.
 */
final class AlertRuleEvaluator
{
    /** @var array<string, ScheduledAlertRuleInterface> */
    private array $rulesByType = [];

    /**
     * @param iterable<ScheduledAlertRuleInterface> $rules
     */
    public function __construct(
        private readonly AlertRuleRepository $alertRuleRepository,
        private readonly AlertRepository $alertRepository,
        private readonly EntityManagerInterface $entityManager,
        #[AutowireIterator(ScheduledAlertRuleInterface::TAG)]
        iterable $rules,
    ) {
        foreach ($rules as $rule) {
            $this->rulesByType[$rule->getType()->value] = $rule;
        }
    }

    public function evaluate(?\DateTimeImmutable $now = null): AlertRuleEvaluationResult
    {
        $now ??= new \DateTimeImmutable();
        $created = 0;
        $byRule = [];

        foreach ($this->alertRuleRepository->findAllOrdered() as $config) {
            $type = $config->getType();
            $byRule[$type->value] = 0;
            $rule = $this->rulesByType[$type->value] ?? null;

            if (!$config->isEnabled() || null === $rule) {
                continue;
            }

            foreach ($rule->findCandidates($config->getDelayDays(), $now) as $candidate) {
                if ($this->alertRepository->hasOpenAlertForRule($type, $candidate->opportunity, $candidate->contact)) {
                    continue;
                }

                $alert = (new Alert())
                    ->setTitle($candidate->title)
                    ->setDescription($candidate->description)
                    ->setPriority($config->getPriority())
                    ->setStatus(AlertStatus::TODO)
                    ->setOpportunity($candidate->opportunity)
                    ->setContact($candidate->contact)
                    ->setRuleType($type);

                $this->entityManager->persist($alert);
                ++$byRule[$type->value];
                ++$created;
            }
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        return new AlertRuleEvaluationResult($created, $byRule);
    }
}
