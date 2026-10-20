<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\FollowUp;
use App\Enum\FollowUpStatus;
use App\State\FollowUpDeletionGuardProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Tests unitaires de App\State\FollowUpDeletionGuardProcessor : une relance
 * terminée n'est jamais transmise au processor de suppression ; les autres
 * statuts le sont.
 */
final class FollowUpDeletionGuardProcessorTest extends TestCase
{
    private function followUp(FollowUpStatus $status): FollowUp
    {
        return (new FollowUp())
            ->setTitle('Relancer par email')
            ->setScheduledAt(new \DateTimeImmutable('+1 day'))
            ->setStatus($status);
    }

    public function testDoneFollowUpIsRefusedWithConflict(): void
    {
        $decorated = $this->createMock(ProcessorInterface::class);
        $decorated->expects(self::never())->method('process');

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage(FollowUpDeletionGuardProcessor::DONE_FOLLOW_UP_MESSAGE);

        (new FollowUpDeletionGuardProcessor($decorated))->process($this->followUp(FollowUpStatus::DONE), new Delete());
    }

    /**
     * @return iterable<string, array{0: FollowUpStatus}>
     */
    public static function deletableStatusProvider(): iterable
    {
        yield 'à faire' => [FollowUpStatus::TODO];
        yield 'annulée' => [FollowUpStatus::CANCELLED];
    }

    #[DataProvider('deletableStatusProvider')]
    public function testOtherStatusesAreDelegated(FollowUpStatus $status): void
    {
        $followUp = $this->followUp($status);
        $decorated = $this->createMock(ProcessorInterface::class);
        $decorated->expects(self::once())->method('process')->with($followUp)->willReturn(null);

        (new FollowUpDeletionGuardProcessor($decorated))->process($followUp, new Delete());
    }
}
