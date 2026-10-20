<?php

declare(strict_types=1);

namespace App\Tests\Automation\Scheduled;

use App\Automation\Scheduled\AlertRuleEvaluator;
use App\Enum\AlertRuleType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie le câblage du conteneur : l'évaluateur reçoit une règle par
 * AlertRuleType grâce au tag d'autoconfiguration. Sans ce câblage, le bouton
 * « Vérifier les règles » répondrait « Aucune nouvelle alerte » sans rien
 * évaluer. Aucune requête en base : le conteneur est seulement construit.
 */
final class AlertRuleEvaluatorWiringTest extends KernelTestCase
{
    public function testEvaluatorReceivesOneRulePerType(): void
    {
        self::bootKernel();

        $evaluator = self::getContainer()->get(AlertRuleEvaluator::class);
        $rulesByType = (new \ReflectionProperty(AlertRuleEvaluator::class, 'rulesByType'))->getValue($evaluator);

        $expected = array_map(static fn (AlertRuleType $type): string => $type->value, AlertRuleType::cases());
        sort($expected);
        $actual = array_keys($rulesByType);
        sort($actual);

        self::assertSame($expected, $actual);
    }
}
