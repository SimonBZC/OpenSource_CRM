<?php

declare(strict_types=1);

namespace App\Controller;

use App\Automation\Scheduled\AlertRuleEvaluator;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Vérification des règles d'alerte configurables :
 * POST /api/alert_rules/evaluate → `{created, byRule}`.
 *
 * Déclenchée uniquement par le bouton « Vérifier les règles » de la page
 * Alertes. Contrôleur Symfony simple (comme MessageTemplateRenderController) :
 * la réponse n'a pas la forme de la ressource AlertRule. L'évaluation, la
 * déduplication et la création sont déléguées à AlertRuleEvaluator.
 */
#[AsController]
class AlertRuleEvaluationController
{
    public function __construct(
        private readonly AlertRuleEvaluator $evaluator,
    ) {
    }

    #[Route('/api/alert_rules/evaluate', name: 'alert_rule_evaluate', methods: ['POST'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->evaluator->evaluate()->toArray());
    }
}
