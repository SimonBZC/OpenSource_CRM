<?php

declare(strict_types=1);

namespace App\Controller;

use App\Messaging\MessageTemplateRenderer;
use App\Repository\ContactRepository;
use App\Repository\MessageTemplateRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Rendu d'un modèle de message pour un contact :
 * GET /api/message_templates/{id}/render?contact={id}.
 *
 * Contrôleur Symfony simple (comme OpportunityAnalysisController) : la réponse
 * `{title, body, missing}` n'a pas la forme de la ressource MessageTemplate.
 * Lecture seule : rien n'est persisté. Le paramètre `contact` est facultatif ;
 * sans lui, le corps est renvoyé inchangé et `missing` liste les indicateurs
 * présents. La substitution elle-même est déléguée à MessageTemplateRenderer.
 */
#[AsController]
class MessageTemplateRenderController
{
    public function __construct(
        private readonly MessageTemplateRepository $messageTemplateRepository,
        private readonly ContactRepository $contactRepository,
        private readonly MessageTemplateRenderer $renderer,
    ) {
    }

    #[Route('/api/message_templates/{id}/render', name: 'message_template_render', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $template = $this->messageTemplateRepository->find($id);

        if (null === $template) {
            return $this->errorResponse(
                Response::HTTP_NOT_FOUND,
                'Message introuvable',
                sprintf("Aucun message avec l'identifiant %d n'a été trouvé.", $id),
                'message_template_not_found',
            );
        }

        $contact = null;
        $contactParam = $request->query->get('contact');

        if (null !== $contactParam && '' !== $contactParam) {
            $contact = ctype_digit((string) $contactParam)
                ? $this->contactRepository->find((int) $contactParam)
                : null;

            if (null === $contact) {
                return $this->errorResponse(
                    Response::HTTP_NOT_FOUND,
                    'Contact introuvable',
                    sprintf("Aucun contact avec l'identifiant %s n'a été trouvé.", (string) $contactParam),
                    'contact_not_found',
                );
            }
        }

        $rendered = $this->renderer->render($template->getBody(), $contact);

        return new JsonResponse([
            'title' => $template->getTitle(),
            'body' => $rendered->body,
            'missing' => $rendered->missing,
        ]);
    }

    private function errorResponse(int $status, string $title, string $detail, string $errorCode): JsonResponse
    {
        return new JsonResponse([
            'title' => $title,
            'detail' => $detail,
            'errorCode' => $errorCode,
        ], $status);
    }
}
