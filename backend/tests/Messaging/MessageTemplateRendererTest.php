<?php

declare(strict_types=1);

namespace App\Tests\Messaging;

use App\Entity\Company;
use App\Entity\Contact;
use App\Messaging\MessageTemplateRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de App\Messaging\MessageTemplateRenderer : matrice des cas
 * de la spec « messages pré-construits ».
 */
final class MessageTemplateRendererTest extends TestCase
{
    private const FULL_BODY = 'Bonjour {%prenom%} {%nom%} de {%entreprise%}';

    private function contact(?string $firstName = 'Jean', string $lastName = 'Dupont', ?string $companyName = 'Acme'): Contact
    {
        $contact = (new Contact())->setFirstName($firstName)->setLastName($lastName);

        if (null !== $companyName) {
            $contact->setCompany((new Company())->setName($companyName));
        }

        return $contact;
    }

    public function testPlaceholdersAreTheThreeSpecifiedKeysWithLabels(): void
    {
        self::assertSame(
            ['prenom' => 'Prénom', 'nom' => 'Nom', 'entreprise' => 'Entreprise'],
            MessageTemplateRenderer::PLACEHOLDERS,
        );
    }

    public function testCompleteContactReplacesEveryPlaceholder(): void
    {
        $rendered = (new MessageTemplateRenderer())->render(self::FULL_BODY, $this->contact());

        self::assertSame('Bonjour Jean Dupont de Acme', $rendered->body);
        self::assertSame([], $rendered->missing);
    }

    /**
     * @return iterable<string, array{0: ?string}>
     */
    public static function emptyFirstNameProvider(): iterable
    {
        yield 'prénom null' => [null];
        yield 'prénom vide' => [''];
        yield 'prénom fait d\'espaces' => ['   '];
    }

    #[DataProvider('emptyFirstNameProvider')]
    public function testEmptyFirstNameKeepsPlaceholderAndIsReported(?string $firstName): void
    {
        $rendered = (new MessageTemplateRenderer())->render(self::FULL_BODY, $this->contact(firstName: $firstName));

        self::assertSame('Bonjour {%prenom%} Dupont de Acme', $rendered->body);
        self::assertSame(['prenom'], $rendered->missing);
    }

    public function testBlankLastNameKeepsPlaceholderAndIsReported(): void
    {
        $rendered = (new MessageTemplateRenderer())->render(self::FULL_BODY, $this->contact(lastName: '   '));

        self::assertSame('Bonjour Jean {%nom%} de Acme', $rendered->body);
        self::assertSame(['nom'], $rendered->missing);
    }

    public function testContactWithoutCompanyKeepsPlaceholderAndIsReported(): void
    {
        $rendered = (new MessageTemplateRenderer())->render(self::FULL_BODY, $this->contact(companyName: null));

        self::assertSame('Bonjour Jean Dupont de {%entreprise%}', $rendered->body);
        self::assertSame(['entreprise'], $rendered->missing);
    }

    public function testCompanyWithBlankNameIsReported(): void
    {
        $rendered = (new MessageTemplateRenderer())->render('Chez {%entreprise%}', $this->contact(companyName: '  '));

        self::assertSame('Chez {%entreprise%}', $rendered->body);
        self::assertSame(['entreprise'], $rendered->missing);
    }

    public function testWithoutContactBodyIsUnchangedAndPresentPlaceholdersAreMissing(): void
    {
        $rendered = (new MessageTemplateRenderer())->render(self::FULL_BODY, null);

        self::assertSame(self::FULL_BODY, $rendered->body);
        self::assertSame(['prenom', 'nom', 'entreprise'], $rendered->missing);
    }

    public function testWithoutContactOnlyPlaceholdersPresentInBodyAreMissing(): void
    {
        $rendered = (new MessageTemplateRenderer())->render('Bonjour {%nom%}', null);

        self::assertSame('Bonjour {%nom%}', $rendered->body);
        self::assertSame(['nom'], $rendered->missing);
    }

    public function testRepeatedPlaceholderIsReplacedEverywhere(): void
    {
        $rendered = (new MessageTemplateRenderer())->render('{%nom%}, oui {%nom%} !', $this->contact());

        self::assertSame('Dupont, oui Dupont !', $rendered->body);
        self::assertSame([], $rendered->missing);
    }

    public function testRepeatedMissingPlaceholderIsListedOnce(): void
    {
        $rendered = (new MessageTemplateRenderer())->render('{%nom%} et {%nom%}', null);

        self::assertSame('{%nom%} et {%nom%}', $rendered->body);
        self::assertSame(['nom'], $rendered->missing);
    }

    public function testPlaceholderAbsentFromBodyIsNotReportedEvenIfDataIsMissing(): void
    {
        $rendered = (new MessageTemplateRenderer())->render('Bonjour {%prenom%}', $this->contact(companyName: null));

        self::assertSame('Bonjour Jean', $rendered->body);
        self::assertSame([], $rendered->missing);
    }

    public function testUnknownPlaceholderIsLeftAsIsAndNotReported(): void
    {
        $rendered = (new MessageTemplateRenderer())->render('{%prenom%}, {%poste%}', $this->contact());

        self::assertSame('Jean, {%poste%}', $rendered->body);
        self::assertSame([], $rendered->missing);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function nonExactSyntaxProvider(): iterable
    {
        yield 'majuscules' => ['{%Prenom%}'];
        yield 'espaces internes' => ['{% prenom %}'];
        yield 'accolades doubles' => ['{{prenom}}'];
    }

    #[DataProvider('nonExactSyntaxProvider')]
    public function testSyntaxIsExactAndCaseSensitive(string $body): void
    {
        $rendered = (new MessageTemplateRenderer())->render($body, $this->contact());

        self::assertSame($body, $rendered->body);
        self::assertSame([], $rendered->missing);
    }

    public function testCompanyIsTheContactCompany(): void
    {
        $rendered = (new MessageTemplateRenderer())->render('{%entreprise%}', $this->contact(companyName: 'Société du contact'));

        self::assertSame('Société du contact', $rendered->body);
    }

    public function testValueContainingAPlaceholderIsNotReinterpreted(): void
    {
        $rendered = (new MessageTemplateRenderer())->render('{%nom%} / {%prenom%}', $this->contact(firstName: 'Jean', lastName: '{%prenom%}'));

        self::assertSame('{%prenom%} / Jean', $rendered->body);
        self::assertSame([], $rendered->missing);
    }

    public function testMissingKeysFollowPlaceholderOrder(): void
    {
        $rendered = (new MessageTemplateRenderer())->render('{%entreprise%} {%prenom%}', null);

        self::assertSame(['prenom', 'entreprise'], $rendered->missing);
    }
}
