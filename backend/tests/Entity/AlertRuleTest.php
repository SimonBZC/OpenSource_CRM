<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AlertRule;
use App\Enum\AlertPriority;
use App\Enum\AlertRuleType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AlertRuleTest extends TestCase
{
    private static ValidatorInterface $validator;

    public static function setUpBeforeClass(): void
    {
        self::$validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testDefaultsAreEnabledMediumAndTypeDelay(): void
    {
        $expectedDelays = [
            'contact_follow_up_stale' => 90,
            'opportunity_no_answer' => 15,
            'contact_not_presented' => 7,
        ];

        foreach (AlertRuleType::cases() as $type) {
            $rule = new AlertRule($type);

            self::assertSame($type, $rule->getType());
            self::assertTrue($rule->isEnabled());
            self::assertSame($expectedDelays[$type->value], $rule->getDelayDays());
            self::assertSame(AlertPriority::MEDIUM, $rule->getPriority());
            self::assertSame($type->label(), $rule->getLabel());
        }
    }

    public function testNegativeDelayProducesViolationOnDelayDays(): void
    {
        $violations = self::$validator->validate((new AlertRule(AlertRuleType::OPPORTUNITY_NO_ANSWER))->setDelayDays(-1));

        self::assertCount(1, $violations);
        self::assertSame('delayDays', $violations[0]->getPropertyPath());
    }

    public function testDelayAboveTenYearsProducesViolationOnDelayDays(): void
    {
        $rule = (new AlertRule(AlertRuleType::CONTACT_FOLLOW_UP_STALE))->setDelayDays(3651);

        $violations = self::$validator->validate($rule);

        self::assertCount(1, $violations);
        self::assertSame('delayDays', $violations[0]->getPropertyPath());
        self::assertSame('Le délai ne peut pas dépasser 3650 jours.', $violations[0]->getMessage());
    }

    public function testZeroDelayIsValid(): void
    {
        self::assertCount(0, self::$validator->validate((new AlertRule(AlertRuleType::CONTACT_NOT_PRESENTED))->setDelayDays(0)));
    }
}
