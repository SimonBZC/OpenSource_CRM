<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Interaction;
use App\Enum\InteractionType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InteractionTest extends TestCase
{
    private static ValidatorInterface $validator;

    public static function setUpBeforeClass(): void
    {
        self::$validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testConstructorInitializesCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $interaction = new Interaction();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $interaction->getCreatedAt());
        self::assertLessThanOrEqual($after, $interaction->getCreatedAt());
    }

    public function testValidInteractionProducesNoViolation(): void
    {
        $interaction = (new Interaction())
            ->setType(InteractionType::EMAIL)
            ->setOccurredAt(new \DateTimeImmutable('-1 hour'));

        $violations = self::$validator->validate($interaction);

        self::assertCount(0, $violations);
    }

    /**
     * `type` et `occurredAt` sont des propriétés PHP typées non-nullables sans
     * valeur par défaut. Le Validator Symfony gère proprement le cas non
     * initialisé (sans lever d'Error PHP) : il le traite comme une valeur
     * null et déclenche la contrainte #[Assert\NotNull] correspondante.
     */
    public function testTypeMissingProducesNotNullViolation(): void
    {
        $interaction = (new Interaction())->setOccurredAt(new \DateTimeImmutable());

        $violations = self::$validator->validate($interaction);

        self::assertCount(1, $violations);
        self::assertSame('type', $violations[0]->getPropertyPath());
    }

    public function testOccurredAtMissingProducesNotNullViolation(): void
    {
        $interaction = (new Interaction())->setType(InteractionType::EMAIL);

        $violations = self::$validator->validate($interaction);

        self::assertCount(1, $violations);
        self::assertSame('occurredAt', $violations[0]->getPropertyPath());
    }
}
