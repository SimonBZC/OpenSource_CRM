<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Alert;
use App\Enum\AlertPriority;
use App\Enum\AlertRuleType;
use App\Enum\AlertStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AlertTest extends TestCase
{
    private static ValidatorInterface $validator;

    public static function setUpBeforeClass(): void
    {
        self::$validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testConstructorInitializesCreatedAtAndDefaults(): void
    {
        $before = new \DateTimeImmutable();
        $alert = new Alert();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $alert->getCreatedAt());
        self::assertLessThanOrEqual($after, $alert->getCreatedAt());
        self::assertSame(AlertStatus::TODO, $alert->getStatus());
        self::assertSame(AlertPriority::MEDIUM, $alert->getPriority());
    }

    /**
     * @return iterable<string, array{0: AlertStatus, 1: ?string, 2: bool}>
     */
    public static function isOverdueProvider(): iterable
    {
        foreach ([AlertStatus::TODO, AlertStatus::IN_PROGRESS] as $activeStatus) {
            yield sprintf('%s with past due date is overdue', $activeStatus->value) => [$activeStatus, '-1 day', true];
            yield sprintf('%s with future due date is not overdue', $activeStatus->value) => [$activeStatus, '+1 day', false];
            yield sprintf('%s with no due date is not overdue', $activeStatus->value) => [$activeStatus, null, false];
        }

        foreach ([AlertStatus::DONE, AlertStatus::CANCELLED] as $terminalStatus) {
            yield sprintf('%s with past due date is not overdue', $terminalStatus->value) => [$terminalStatus, '-1 day', false];
            yield sprintf('%s with future due date is not overdue', $terminalStatus->value) => [$terminalStatus, '+1 day', false];
            yield sprintf('%s with no due date is not overdue', $terminalStatus->value) => [$terminalStatus, null, false];
        }
    }

    #[DataProvider('isOverdueProvider')]
    public function testIsOverdueWithExplicitNow(AlertStatus $status, ?string $modifier, bool $expected): void
    {
        $reference = new \DateTimeImmutable('2026-01-15 12:00:00');

        $alert = (new Alert())->setStatus($status);

        if (null !== $modifier) {
            $alert->setDueDate($reference->modify($modifier));
        }

        self::assertSame($expected, $alert->isOverdue($reference));
    }

    public function testIsOverdueUsesCurrentTimeWhenNowIsNull(): void
    {
        $alert = (new Alert())
            ->setStatus(AlertStatus::TODO)
            ->setDueDate(new \DateTimeImmutable('-1 minute'));

        self::assertTrue($alert->isOverdue());
    }

    public function testIsNotOverdueUsingCurrentTimeWhenDueDateInFuture(): void
    {
        $alert = (new Alert())
            ->setStatus(AlertStatus::TODO)
            ->setDueDate(new \DateTimeImmutable('+1 hour'));

        self::assertFalse($alert->isOverdue());
    }

    public function testIsOverdueIsFalseWhenDueDateExactlyAtNow(): void
    {
        $now = new \DateTimeImmutable('2026-01-15 12:00:00');

        $alert = (new Alert())
            ->setStatus(AlertStatus::TODO)
            ->setDueDate($now);

        self::assertFalse($alert->isOverdue($now));
    }

    public function testTitleBlankProducesViolation(): void
    {
        $alert = (new Alert())->setTitle('');

        $violations = self::$validator->validate($alert);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testTitleTooLongProducesViolation(): void
    {
        $alert = (new Alert())->setTitle(str_repeat('a', 256));

        $violations = self::$validator->validate($alert);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testValidAlertProducesNoViolation(): void
    {
        $alert = (new Alert())->setTitle('Relire le devis');

        $violations = self::$validator->validate($alert);

        self::assertCount(0, $violations);
    }

    public function testRuleTypeDefaultsToNullAndCanBeSet(): void
    {
        $alert = new Alert();

        self::assertNull($alert->getRuleType());
        self::assertSame(AlertRuleType::CONTACT_NOT_PRESENTED, $alert->setRuleType(AlertRuleType::CONTACT_NOT_PRESENTED)->getRuleType());
    }
}
