<?php

declare(strict_types=1);

namespace App\Tests\Automation;

use App\Automation\AutomationRuleInterface;
use App\Automation\DomainEventBus;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests unitaires de App\Automation\DomainEventBus.
 *
 * App\Automation\AutomationRuleInterface est ici implémentée par de petites
 * classes anonymes/dédiées plutôt que mockée : le comportement exact de
 * supports()/execute() (y compris lever une exception) doit être déterministe
 * et facile à lire dans chaque test.
 */
final class DomainEventBusTest extends TestCase
{
    public function testDispatchForwardsEventToSymfonyEventDispatcher(): void
    {
        $event = new \stdClass();

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with($event);

        $bus = new DomainEventBus([], $dispatcher, $this->createMock(LoggerInterface::class));

        $bus->dispatch($event);
    }

    public function testDispatchExecutesOnlyRulesThatSupportTheEvent(): void
    {
        $event = new \stdClass();

        $supportingRule = new RecordingAutomationRule(supports: true, name: 'supporting_rule');
        $nonSupportingRule = new RecordingAutomationRule(supports: false, name: 'non_supporting_rule');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $bus = new DomainEventBus([$supportingRule, $nonSupportingRule], $dispatcher, $this->createMock(LoggerInterface::class));

        $bus->dispatch($event);

        self::assertSame([$event], $supportingRule->executedWith);
        self::assertSame([], $nonSupportingRule->executedWith, 'Une règle dont supports() renvoie false ne doit jamais être exécutée.');
    }

    public function testARuleThrowingIsCaughtLoggedAndDoesNotPreventFollowingRulesFromRunning(): void
    {
        $event = new \stdClass();

        $failingRule = new ThrowingAutomationRule('failing_rule');
        $followingRule = new RecordingAutomationRule(supports: true, name: 'following_rule');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $logger->expects(self::once())->method('info');

        $bus = new DomainEventBus([$failingRule, $followingRule], $dispatcher, $logger);

        // L'appel ne doit lever aucune exception : elle est capturée par le bus.
        $bus->dispatch($event);

        self::assertSame([$event], $followingRule->executedWith, 'La règle suivante doit quand même être exécutée après l\'échec de la première.');
    }
}

final class RecordingAutomationRule implements AutomationRuleInterface
{
    /** @var list<object> */
    public array $executedWith = [];

    public function __construct(
        private readonly bool $supports,
        private readonly string $name,
    ) {
    }

    public function supports(object $event): bool
    {
        return $this->supports;
    }

    public function execute(object $event): void
    {
        $this->executedWith[] = $event;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

final class ThrowingAutomationRule implements AutomationRuleInterface
{
    public function __construct(
        private readonly string $name,
    ) {
    }

    public function supports(object $event): bool
    {
        return true;
    }

    public function execute(object $event): void
    {
        throw new \RuntimeException('Boom, cette règle échoue toujours.');
    }

    public function getName(): string
    {
        return $this->name;
    }
}
