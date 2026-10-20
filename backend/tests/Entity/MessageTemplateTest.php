<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\MessageTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MessageTemplateTest extends TestCase
{
    private static ValidatorInterface $validator;

    public static function setUpBeforeClass(): void
    {
        self::$validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function makeValid(): MessageTemplate
    {
        return (new MessageTemplate())
            ->setTitle('Relance après candidature')
            ->setBody('Bonjour {%prenom%}');
    }

    public function testConstructorInitializesCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $template = new MessageTemplate();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $template->getCreatedAt());
        self::assertLessThanOrEqual($after, $template->getCreatedAt());
    }

    public function testValidTemplateHasNoViolation(): void
    {
        self::assertCount(0, self::$validator->validate($this->makeValid()));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function blankFieldProvider(): iterable
    {
        yield 'titre vide' => ['title', ''];
        yield 'corps vide' => ['body', ''];
        yield 'titre fait d\'espaces' => ['title', '   '];
        yield 'corps fait d\'espaces' => ['body', " \n "];
    }

    #[DataProvider('blankFieldProvider')]
    public function testBlankFieldIsRejected(string $field, string $value): void
    {
        $template = $this->makeValid();
        'title' === $field ? $template->setTitle($value) : $template->setBody($value);

        $violations = self::$validator->validate($template);

        self::assertGreaterThan(0, \count($violations));
        self::assertSame($field, $violations[0]->getPropertyPath());
    }

    public function testTitleLongerThan255IsRejected(): void
    {
        $violations = self::$validator->validate($this->makeValid()->setTitle(str_repeat('a', 256)));

        self::assertCount(1, $violations);
        self::assertSame('title', $violations[0]->getPropertyPath());
    }
}
