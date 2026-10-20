<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\MessageTemplate;
use App\Repository\MessageTemplateRepository;
use App\State\MessageTemplateUniqueTitleProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de App\State\MessageTemplateUniqueTitleProcessor.
 *
 * La comparaison insensible à la casse et aux espaces est faite en base par
 * MessageTemplateRepository::findOneByTitleIgnoreCase() (LOWER/TRIM en DQL) :
 * elle est vérifiée en boîte noire sur la stack de test. Ici, le repository
 * est simulé : on vérifie l'identifiant exclu, le refus par une violation sur
 * `title` et l'absence de persistance dans ce cas.
 */
final class MessageTemplateUniqueTitleProcessorTest extends TestCase
{
    /**
     * @return array{0: MessageTemplateUniqueTitleProcessor, 1: ProcessorInterface&MockObject, 2: MessageTemplateRepository&MockObject}
     */
    private function makeProcessor(): array
    {
        $decorated = $this->createMock(ProcessorInterface::class);
        $repository = $this->createMock(MessageTemplateRepository::class);

        return [new MessageTemplateUniqueTitleProcessor($decorated, $repository), $decorated, $repository];
    }

    private function template(string $title, ?int $id = null): MessageTemplate
    {
        $template = (new MessageTemplate())->setTitle($title)->setBody('Bonjour');
        if (null !== $id) {
            (new \ReflectionProperty(MessageTemplate::class, 'id'))->setValue($template, $id);
        }

        return $template;
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function duplicateTitleProvider(): iterable
    {
        yield 'casse différente' => ['relance'];
        yield 'majuscules' => ['RELANCE'];
        yield 'espaces en début et fin' => ['  Relance  '];
    }

    #[DataProvider('duplicateTitleProvider')]
    public function testDuplicateTitleIsRejectedWithViolationOnTitle(string $submittedTitle): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $repository->expects(self::once())
            ->method('findOneByTitleIgnoreCase')
            ->with($submittedTitle, null)
            ->willReturn($this->template('Relance', 1));
        $decorated->expects(self::never())->method('process');

        try {
            $processor->process($this->template($submittedTitle), new Post());
            self::fail('Une ValidationException était attendue pour un doublon.');
        } catch (ValidationException $exception) {
            $violations = $exception->getConstraintViolationList();
            self::assertCount(1, $violations);
            self::assertSame('title', $violations[0]->getPropertyPath());
            self::assertSame('Un message porte déjà ce titre.', $violations[0]->getMessage());
        }
    }

    public function testFreeTitleIsDelegatedToPersistProcessor(): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $template = $this->template('Nouveau message');
        $operation = new Post();

        $repository->method('findOneByTitleIgnoreCase')->with('Nouveau message', null)->willReturn(null);
        $decorated->expects(self::once())->method('process')->with($template, $operation, [], [])->willReturn($template);

        self::assertSame($template, $processor->process($template, $operation));
    }

    public function testPatchOnItselfExcludesOwnId(): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $template = $this->template('Relance', 42);
        $repository->expects(self::once())->method('findOneByTitleIgnoreCase')->with('Relance', 42)->willReturn(null);
        $decorated->expects(self::once())->method('process')->willReturn($template);

        self::assertSame($template, $processor->process($template, new Patch(), ['id' => 42]));
    }

    public function testPatchToAnotherTemplateTitleIsRejected(): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $repository->method('findOneByTitleIgnoreCase')->with('RELANCE', 42)->willReturn($this->template('Relance', 7));
        $decorated->expects(self::never())->method('process');

        $this->expectException(ValidationException::class);

        $processor->process($this->template('RELANCE', 42), new Patch(), ['id' => 42]);
    }

    public function testPutOnItselfUsesPreviousDataId(): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $template = $this->template('Relance');
        $repository->expects(self::once())->method('findOneByTitleIgnoreCase')->with('Relance', 42)->willReturn(null);
        $decorated->expects(self::once())->method('process')->willReturn($template);

        $processor->process($template, new Put(), ['id' => 42], ['previous_data' => $this->template('Relance', 42)]);
    }

    public function testPutWithoutPreviousDataFallsBackOnUriId(): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $template = $this->template('Relance');
        $repository->expects(self::once())->method('findOneByTitleIgnoreCase')->with('Relance', 42)->willReturn(null);
        $decorated->expects(self::once())->method('process')->willReturn($template);

        $processor->process($template, new Put(), ['id' => '42']);
    }

    public function testNonTemplateDataIsDelegatedWithoutLookup(): void
    {
        [$processor, $decorated, $repository] = $this->makeProcessor();

        $data = new \stdClass();
        $repository->expects(self::never())->method('findOneByTitleIgnoreCase');
        $decorated->expects(self::once())->method('process')->willReturn($data);

        self::assertSame($data, $processor->process($data, new Post()));
    }
}
