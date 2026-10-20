<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Automation\Scheduled\AlertRuleEvaluator;
use App\Controller\AlertRuleEvaluationController;
use App\Entity\AlertRule;
use App\Enum\AlertRuleType;
use App\Repository\AlertRepository;
use App\Repository\AlertRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/alert_rules/evaluate renvoie le bilan `{created, byRule}` de
 * l'évaluateur (dépôts simulés, aucune règle n'ayant de cible ici).
 */
final class AlertRuleEvaluationControllerTest extends TestCase
{
    public function testReturnsEvaluationSummary(): void
    {
        $alertRuleRepository = $this->createMock(AlertRuleRepository::class);
        $alertRuleRepository->method('findAllOrdered')->willReturn([
            new AlertRule(AlertRuleType::CONTACT_FOLLOW_UP_STALE),
            (new AlertRule(AlertRuleType::CONTACT_NOT_PRESENTED))->setEnabled(false),
        ]);

        $evaluator = new AlertRuleEvaluator(
            $alertRuleRepository,
            $this->createMock(AlertRepository::class),
            $this->createMock(EntityManagerInterface::class),
            [],
        );

        $response = (new AlertRuleEvaluationController($evaluator))();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            ['created' => 0, 'byRule' => ['contact_follow_up_stale' => 0, 'contact_not_presented' => 0]],
            json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR),
        );
    }
}
