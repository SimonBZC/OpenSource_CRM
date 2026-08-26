<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Automation\DomainEventBus;
use App\Automation\Event\FollowUpDue;
use App\Command\CheckDueFollowUpsCommand;
use App\Entity\FollowUp;
use App\Enum\FollowUpStatus;
use App\Repository\FollowUpRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests unitaires de App\Command\CheckDueFollowUpsCommand.
 *
 * App\Repository\FollowUpRepository est mockée (pas final, pas de DB
 * requise : createMock() n'appelle pas le constructeur parent). App\Automation\DomainEventBus
 * est `final` (voir le même choix documenté dans
 * tests/Controller/OpportunityAnalysisControllerTest.php) : une instance
 * réelle est construite avec [] comme règles, ce qui permet d'observer
 * fidèlement les événements réellement dispatchés via un listener sur un
 * Symfony\Component\EventDispatcher\EventDispatcher réel.
 */
final class CheckDueFollowUpsCommandTest extends TestCase
{
    private function makeFollowUp(?int $id = null): FollowUp
    {
        $followUp = (new FollowUp())
            ->setTitle('Relance')
            ->setScheduledAt(new \DateTimeImmutable('2026-08-20 10:00:00'))
            ->setStatus(FollowUpStatus::TODO);

        if (null !== $id) {
            $reflection = new \ReflectionProperty(FollowUp::class, 'id');
            $reflection->setAccessible(true);
            $reflection->setValue($followUp, $id);
        }

        return $followUp;
    }

    /**
     * @return array{0: CheckDueFollowUpsCommand, 1: FollowUpRepository&\PHPUnit\Framework\MockObject\MockObject, 2: \ArrayObject<int, FollowUpDue>}
     */
    private function makeCommand(): array
    {
        $repository = $this->createMock(FollowUpRepository::class);

        $capturedEvents = new \ArrayObject();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            FollowUpDue::class,
            static function (FollowUpDue $event) use ($capturedEvents): void {
                $capturedEvents[] = $event;
            },
        );
        $eventBus = new DomainEventBus([], $dispatcher, new NullLogger());

        $command = new CheckDueFollowUpsCommand($repository, $eventBus);

        return [$command, $repository, $capturedEvents];
    }

    public function testDryRunDoesNotEmitAnyEventButCountsAndDisplaysCorrectly(): void
    {
        [$command, $repository, $capturedEvents] = $this->makeCommand();

        $dueFollowUps = [$this->makeFollowUp(1), $this->makeFollowUp(2), $this->makeFollowUp(3)];
        $repository->expects(self::once())
            ->method('findDueFollowUps')
            ->with(self::isInstanceOf(\DateTimeImmutable::class), FollowUpStatus::TODO)
            ->willReturn($dueFollowUps);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--dry-run' => true]);

        self::assertSame(0, $exitCode);
        self::assertCount(0, $capturedEvents, 'Aucun événement ne doit être émis en mode --dry-run.');
        self::assertStringContainsString('3 relance(s) échue(s) détectée(s) (dry-run : aucun événement émis).', $tester->getDisplay());
    }

    public function testNormalModeEmitsOneFollowUpDuePerRepositoryResultAndDisplaysCorrectOutput(): void
    {
        [$command, $repository, $capturedEvents] = $this->makeCommand();

        $followUp1 = $this->makeFollowUp(1);
        $followUp2 = $this->makeFollowUp(2);
        $repository->expects(self::once())
            ->method('findDueFollowUps')
            ->willReturn([$followUp1, $followUp2]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertCount(2, $capturedEvents);
        self::assertSame($followUp1, $capturedEvents[0]->followUp);
        self::assertSame($followUp2, $capturedEvents[1]->followUp);
        self::assertStringContainsString('2 relance(s) échue(s) traitée(s).', $tester->getDisplay());
    }

    public function testNormalModeWithNoDueFollowUpsEmitsNothingAndDisplaysZero(): void
    {
        [$command, $repository, $capturedEvents] = $this->makeCommand();

        $repository->method('findDueFollowUps')->willReturn([]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertCount(0, $capturedEvents);
        self::assertStringContainsString('0 relance(s) échue(s) traitée(s).', $tester->getDisplay());
    }
}
