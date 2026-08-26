<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\FollowUp;
use App\Enum\FollowUpStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FollowUpTest extends TestCase
{
    private static ValidatorInterface $validator;

    public static function setUpBeforeClass(): void
    {
        self::$validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function makeValidFollowUp(): FollowUp
    {
        return (new FollowUp())
            ->setTitle('Relancer par email')
            ->setScheduledAt(new \DateTimeImmutable('+1 day'));
    }

    public function testConstructorInitializesCreatedAtAndDefaultStatus(): void
    {
        $before = new \DateTimeImmutable();
        $followUp = new FollowUp();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $followUp->getCreatedAt());
        self::assertLessThanOrEqual($after, $followUp->getCreatedAt());
        self::assertSame(FollowUpStatus::TODO, $followUp->getStatus());
    }

    /**
     * @return iterable<string, array{0: FollowUpStatus, 1: string, 2: bool}>
     */
    public static function isOverdueProvider(): iterable
    {
        yield 'todo and scheduled in the past is overdue' => [FollowUpStatus::TODO, '-1 day', true];
        yield 'todo and scheduled in the future is not overdue' => [FollowUpStatus::TODO, '+1 day', false];
        yield 'done and scheduled in the past is not overdue' => [FollowUpStatus::DONE, '-1 day', false];
        yield 'cancelled and scheduled in the past is not overdue' => [FollowUpStatus::CANCELLED, '-1 day', false];
        yield 'done and scheduled in the future is not overdue' => [FollowUpStatus::DONE, '+1 day', false];
        yield 'cancelled and scheduled in the future is not overdue' => [FollowUpStatus::CANCELLED, '+1 day', false];
    }

    #[DataProvider('isOverdueProvider')]
    public function testIsOverdueWithExplicitNow(FollowUpStatus $status, string $modifier, bool $expected): void
    {
        $reference = new \DateTimeImmutable('2026-01-15 12:00:00');
        $scheduledAt = $reference->modify($modifier);

        $followUp = (new FollowUp())
            ->setStatus($status)
            ->setScheduledAt($scheduledAt);

        self::assertSame($expected, $followUp->isOverdue($reference));
    }

    public function testIsOverdueUsesCurrentTimeWhenNowIsNull(): void
    {
        $followUp = (new FollowUp())
            ->setStatus(FollowUpStatus::TODO)
            ->setScheduledAt(new \DateTimeImmutable('-1 minute'));

        self::assertTrue($followUp->isOverdue());
    }

    public function testIsNotOverdueUsingCurrentTimeWhenScheduledInFuture(): void
    {
        $followUp = (new FollowUp())
            ->setStatus(FollowUpStatus::TODO)
            ->setScheduledAt(new \DateTimeImmutable('+1 hour'));

        self::assertFalse($followUp->isOverdue());
    }

    public function testIsOverdueIsFalseWhenScheduledExactlyAtNow(): void
    {
        $now = new \DateTimeImmutable('2026-01-15 12:00:00');

        $followUp = (new FollowUp())
            ->setStatus(FollowUpStatus::TODO)
            ->setScheduledAt($now);

        self::assertFalse($followUp->isOverdue($now));
    }

    public function testTitleBlankProducesViolation(): void
    {
        $followUp = $this->makeValidFollowUp()->setTitle('');

        $violations = self::$validator->validate($followUp);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testTitleTooLongProducesViolation(): void
    {
        $followUp = $this->makeValidFollowUp()->setTitle(str_repeat('a', 256));

        $violations = self::$validator->validate($followUp);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testValidFollowUpProducesNoViolation(): void
    {
        $followUp = $this->makeValidFollowUp();

        $violations = self::$validator->validate($followUp);

        self::assertCount(0, $violations);
    }

    /**
     * `scheduledAt` est une propriété PHP typée non-nullable sans valeur par
     * défaut. Le Validator Symfony gère cependant proprement le cas non
     * initialisé (sans lever d'Error PHP) : il le traite comme une valeur
     * null et déclenche la contrainte #[Assert\NotNull].
     */
    public function testScheduledAtMissingProducesNotNullViolation(): void
    {
        $followUp = (new FollowUp())->setTitle('Relancer par email');

        $violations = self::$validator->validate($followUp);

        self::assertCount(1, $violations);
        self::assertSame('scheduledAt', $violations[0]->getPropertyPath());
    }
}
