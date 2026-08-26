<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Task;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TaskTest extends TestCase
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
        $task = new Task();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $task->getCreatedAt());
        self::assertLessThanOrEqual($after, $task->getCreatedAt());
        self::assertSame(TaskStatus::TODO, $task->getStatus());
        self::assertSame(TaskPriority::MEDIUM, $task->getPriority());
    }

    /**
     * @return iterable<string, array{0: TaskStatus, 1: ?string, 2: bool}>
     */
    public static function isOverdueProvider(): iterable
    {
        foreach ([TaskStatus::TODO, TaskStatus::IN_PROGRESS] as $activeStatus) {
            yield sprintf('%s with past due date is overdue', $activeStatus->value) => [$activeStatus, '-1 day', true];
            yield sprintf('%s with future due date is not overdue', $activeStatus->value) => [$activeStatus, '+1 day', false];
            yield sprintf('%s with no due date is not overdue', $activeStatus->value) => [$activeStatus, null, false];
        }

        foreach ([TaskStatus::DONE, TaskStatus::CANCELLED] as $terminalStatus) {
            yield sprintf('%s with past due date is not overdue', $terminalStatus->value) => [$terminalStatus, '-1 day', false];
            yield sprintf('%s with future due date is not overdue', $terminalStatus->value) => [$terminalStatus, '+1 day', false];
            yield sprintf('%s with no due date is not overdue', $terminalStatus->value) => [$terminalStatus, null, false];
        }
    }

    #[DataProvider('isOverdueProvider')]
    public function testIsOverdueWithExplicitNow(TaskStatus $status, ?string $modifier, bool $expected): void
    {
        $reference = new \DateTimeImmutable('2026-01-15 12:00:00');

        $task = (new Task())->setStatus($status);

        if (null !== $modifier) {
            $task->setDueDate($reference->modify($modifier));
        }

        self::assertSame($expected, $task->isOverdue($reference));
    }

    public function testIsOverdueUsesCurrentTimeWhenNowIsNull(): void
    {
        $task = (new Task())
            ->setStatus(TaskStatus::TODO)
            ->setDueDate(new \DateTimeImmutable('-1 minute'));

        self::assertTrue($task->isOverdue());
    }

    public function testIsNotOverdueUsingCurrentTimeWhenDueDateInFuture(): void
    {
        $task = (new Task())
            ->setStatus(TaskStatus::TODO)
            ->setDueDate(new \DateTimeImmutable('+1 hour'));

        self::assertFalse($task->isOverdue());
    }

    public function testIsOverdueIsFalseWhenDueDateExactlyAtNow(): void
    {
        $now = new \DateTimeImmutable('2026-01-15 12:00:00');

        $task = (new Task())
            ->setStatus(TaskStatus::TODO)
            ->setDueDate($now);

        self::assertFalse($task->isOverdue($now));
    }

    public function testTitleBlankProducesViolation(): void
    {
        $task = (new Task())->setTitle('');

        $violations = self::$validator->validate($task);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testTitleTooLongProducesViolation(): void
    {
        $task = (new Task())->setTitle(str_repeat('a', 256));

        $violations = self::$validator->validate($task);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testValidTaskProducesNoViolation(): void
    {
        $task = (new Task())->setTitle('Relire le devis');

        $violations = self::$validator->validate($task);

        self::assertCount(0, $violations);
    }
}
