<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\MessageTemplateRenderController;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\MessageTemplate;
use App\Messaging\MessageTemplateRenderer;
use App\Repository\ContactRepository;
use App\Repository\MessageTemplateRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests unitaires de App\Controller\MessageTemplateRenderController.
 * Les repositories sont simulés ; le renderer (final, sans dépendance) est réel.
 */
final class MessageTemplateRenderControllerTest extends TestCase
{
    /**
     * @return array{0: MessageTemplateRenderController, 1: MessageTemplateRepository&MockObject, 2: ContactRepository&MockObject}
     */
    private function makeController(): array
    {
        $templates = $this->createMock(MessageTemplateRepository::class);
        $contacts = $this->createMock(ContactRepository::class);

        return [new MessageTemplateRenderController($templates, $contacts, new MessageTemplateRenderer()), $templates, $contacts];
    }

    private function template(): MessageTemplate
    {
        return (new MessageTemplate())->setTitle('Relance')->setBody('Bonjour {%prenom%} {%nom%} de {%entreprise%}');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    public function testRendersTemplateForCompleteContact(): void
    {
        [$controller, $templates, $contacts] = $this->makeController();

        $contact = (new Contact())->setFirstName('Jean')->setLastName('Dupont')->setCompany((new Company())->setName('Acme'));
        $templates->method('find')->with(5)->willReturn($this->template());
        $contacts->method('find')->with(3)->willReturn($contact);

        $response = $controller(5, new Request(['contact' => '3']));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            ['title' => 'Relance', 'body' => 'Bonjour Jean Dupont de Acme', 'missing' => []],
            $this->decode($response),
        );
    }

    public function testContactWithoutFirstNameReportsMissing(): void
    {
        [$controller, $templates, $contacts] = $this->makeController();

        $contact = (new Contact())->setFirstName(null)->setLastName('Dupont')->setCompany((new Company())->setName('Acme'));
        $templates->method('find')->willReturn($this->template());
        $contacts->method('find')->willReturn($contact);

        $data = $this->decode($controller(5, new Request(['contact' => '3'])));

        self::assertSame('Bonjour {%prenom%} Dupont de Acme', $data['body']);
        self::assertSame(['prenom'], $data['missing']);
    }

    public function testWithoutContactParameterBodyIsUnchanged(): void
    {
        [$controller, $templates, $contacts] = $this->makeController();

        $templates->method('find')->willReturn($this->template());
        $contacts->expects(self::never())->method('find');

        $data = $this->decode($controller(5, new Request()));

        self::assertSame('Bonjour {%prenom%} {%nom%} de {%entreprise%}', $data['body']);
        self::assertSame(['prenom', 'nom', 'entreprise'], $data['missing']);
    }

    public function testEmptyContactParameterIsTreatedAsNoContact(): void
    {
        [$controller, $templates, $contacts] = $this->makeController();

        $templates->method('find')->willReturn($this->template());
        $contacts->expects(self::never())->method('find');

        $response = $controller(5, new Request(['contact' => '']));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testUnknownTemplateReturns404(): void
    {
        [$controller, $templates] = $this->makeController();

        $templates->method('find')->with(999)->willReturn(null);

        $response = $controller(999, new Request());

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('message_template_not_found', $this->decode($response)['errorCode']);
    }

    public function testUnknownContactReturns404(): void
    {
        [$controller, $templates, $contacts] = $this->makeController();

        $templates->method('find')->willReturn($this->template());
        $contacts->method('find')->with(999)->willReturn(null);

        $response = $controller(5, new Request(['contact' => '999']));

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('contact_not_found', $this->decode($response)['errorCode']);
    }

    public function testNonNumericContactReturns404WithoutLookup(): void
    {
        [$controller, $templates, $contacts] = $this->makeController();

        $templates->method('find')->willReturn($this->template());
        $contacts->expects(self::never())->method('find');

        $response = $controller(5, new Request(['contact' => 'abc']));

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('contact_not_found', $this->decode($response)['errorCode']);
    }
}
