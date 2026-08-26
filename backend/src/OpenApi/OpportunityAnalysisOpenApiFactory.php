<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Decorates API Platform's OpenAPI factory to document the
 * POST /api/opportunities/{id}/analyze endpoint, which is served by a plain
 * Symfony controller (App\Controller\OpportunityAnalysisController) and is
 * therefore not discovered automatically from ApiResource metadata.
 *
 * Documentation-only: this decorator never touches the CRUD paths generated
 * by API Platform for the existing resources.
 */
final class OpportunityAnalysisOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(private readonly OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $errorSchema = new \ArrayObject([
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'detail' => ['type' => 'string'],
                'errorCode' => [
                    'type' => 'string',
                    'enum' => ['opportunity_not_found', 'ai_not_configured', 'ai_provider_unavailable', 'ai_invalid_response'],
                ],
            ],
        ]);

        $successSchema = new \ArrayObject([
            'type' => 'object',
            'properties' => [
                'opportunity' => ['type' => 'object'],
                'analysis' => ['type' => 'object'],
                'appliedFields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ]);

        $operation = new Operation(
            operationId: 'analyzeOpportunity',
            tags: ['Opportunity'],
            responses: [
                '200' => new Response(
                    description: "Analyse IA effectuée et appliquée à l'opportunité selon la politique de persistance.",
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: $successSchema),
                    ]),
                ),
                '404' => new Response(
                    description: "Aucune opportunité avec cet identifiant n'a été trouvée.",
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: $errorSchema),
                    ]),
                ),
                '502' => new Response(
                    description: 'Le fournisseur IA a renvoyé une réponse invalide ou inexploitable.',
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: $errorSchema),
                    ]),
                ),
                '503' => new Response(
                    description: "L'analyse IA n'est pas configurée (clé API absente) ou le fournisseur IA est momentanément indisponible.",
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: $errorSchema),
                    ]),
                ),
            ],
            summary: "Déclenche une analyse IA d'une opportunité et applique le résultat selon la politique de persistance définie.",
            description: 'Cette opération ne fait pas partie du CRUD standard des opportunités : elle déclenche un appel au fournisseur IA (effet de bord) et retourne un payload composite (opportunity + analysis + appliedFields).',
            parameters: [
                new Parameter(
                    name: 'id',
                    in: 'path',
                    description: "Identifiant numérique de l'opportunité.",
                    required: true,
                    schema: ['type' => 'integer'],
                ),
            ],
            requestBody: new RequestBody(description: 'Aucun corps de requête attendu.'),
        );

        $openApi->getPaths()->addPath(
            '/api/opportunities/{id}/analyze',
            (new PathItem())->withPost($operation),
        );

        return $openApi;
    }
}
