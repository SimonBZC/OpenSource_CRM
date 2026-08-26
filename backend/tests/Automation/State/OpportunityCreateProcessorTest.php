<?php

declare(strict_types=1);

namespace App\Tests\Automation\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Automation\Event\OpportunityCreated;
use App\Automation\State\OpportunityCreateProcessor;
use App\Entity\Opportunity;
use App\Entity\Task;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests unitaires de App\Automation\State\OpportunityCreateProcessor.
 *
 * App\Automation\DomainEventBus est `final` : une instance réelle est
 * construite avec un vrai Symfony\Component\EventDispatcher\EventDispatcher
 * et [] comme règles (voir la même approche documentée dans
 * tests/Controller/OpportunityAnalysisControllerTest.php et
 * tests/Command/CheckDueFollowUpsCommandTest.php), avec un listener qui
 * enregistre les OpportunityCreated dispatchés.
 */
final class OpportunityCreateProcessorTest extends TestCase
{
    /**
     * @return array{0: OpportunityCreateProcessor, 1: ProcessorInterface&\PHPUnit\Framework\MockObject\MockObject, 2: \ArrayObject<int, OpportunityCreated>}
     */
    private function makeProcessor(): array
    {
        $decorated = $this->createMock(ProcessorInterface::class);

        $capturedEvents = new \ArrayObject();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            OpportunityCreated::class,
            static function (OpportunityCreated $event) use ($capturedEvents): void {
                $capturedEvents[] = $event;
            },
        );
        $eventBus = new \App\Automation\DomainEventBus([], $dispatcher, new NullLogger());

        $processor = new OpportunityCreateProcessor($decorated, $eventBus);

        return [$processor, $decorated, $capturedEvents];
    }

    public function testProcessDelegatesToDecoratedProcessorAndReturnsItsResult(): void
    {
        [$processor, $decorated] = $this->makeProcessor();

        $opportunity = (new Opportunity())->setTitle('Développeur Symfony senior');
        $operation = new Post();
        $uriVariables = ['id' => 1];
        $context = ['some' => 'context'];

        $decorated->expects(self::once())
            ->method('process')
            ->with($opportunity, $operation, $uriVariables, $context)
            ->willReturn($opportunity);

        $result = $processor->process($opportunity, $operation, $uriVariables, $context);

        self::assertSame($opportunity, $result);
    }

    public function testProcessEmitsOpportunityCreatedWhenDecoratedResultIsAnOpportunity(): void
    {
        [$processor, $decorated, $capturedEvents] = $this->makeProcessor();

        $opportunity = (new Opportunity())->setTitle('Développeur Symfony senior');
        $decorated->method('process')->willReturn($opportunity);

        $processor->process($opportunity, new Post());

        self::assertCount(1, $capturedEvents);
        self::assertSame($opportunity, $capturedEvents[0]->opportunity);
    }

    public function testProcessDoesNotEmitOpportunityCreatedWhenDecoratedResultIsNotAnOpportunity(): void
    {
        // Cas réel : Post::securityMessage ou une opération sur une autre
        // ressource déléguerait à ce même decorated processor et pourrait
        // renvoyer autre chose (null pour un no-op, ou une autre entité).
        [$processor, $decorated, $capturedEvents] = $this->makeProcessor();

        $task = new Task();
        $decorated->method('process')->willReturn($task);

        $result = $processor->process($task, new Post());

        self::assertSame($task, $result);
        self::assertCount(0, $capturedEvents);
    }

    public function testProcessDoesNotEmitOpportunityCreatedWhenDecoratedResultIsNull(): void
    {
        [$processor, $decorated, $capturedEvents] = $this->makeProcessor();

        $decorated->method('process')->willReturn(null);

        $result = $processor->process(new Opportunity(), new Post());

        self::assertNull($result);
        self::assertCount(0, $capturedEvents);
    }
}
