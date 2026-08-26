<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ai\Exception\InvalidAnalysisResponseException;
use App\Ai\Exception\LLMProviderException;
use App\Ai\Exception\MissingApiKeyException;
use App\Ai\OpportunityAnalysisApplier;
use App\Ai\OpportunityAnalysisInputFactory;
use App\Ai\OpportunityAnalyzer;
use App\Automation\DomainEventBus;
use App\Automation\Event\OpportunityAnalyzed;
use App\Repository\OpportunityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * HTTP entry point that bridges the standalone AI analysis layer
 * (App\Ai\OpportunityAnalyzer) to the CRM domain.
 *
 * Implemented as a plain Symfony controller (not an API Platform custom
 * operation/State Processor) on purpose: this action is not a CRUD
 * operation on the Opportunity resource — it triggers a side effect (an
 * LLM call) and returns a composite payload (opportunity + analysis +
 * appliedFields) that does not match the Opportunity resource shape. A
 * dedicated, explicitly routed controller keeps this concern decoupled
 * from api_platform.yaml/ApiResource metadata while still living under
 * /api, and cannot collide with the existing Get/Put/Patch/Delete
 * operations on /api/opportunities/{id}, since the "analyze" path segment
 * makes the route pattern distinct.
 *
 * The LLM never writes to the database: this controller delegates to
 * App\Ai\OpportunityAnalysisApplier, which is the only place that decides
 * which fields of the Opportunity are updated from the analysis result,
 * following the persistence policy described on that service.
 */
#[AsController]
class OpportunityAnalysisController
{
    public function __construct(
        private readonly OpportunityRepository $opportunityRepository,
        private readonly OpportunityAnalyzer $analyzer,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly NormalizerInterface $normalizer,
        private readonly OpportunityAnalysisApplier $applier,
        private readonly DomainEventBus $eventBus,
    ) {
    }

    #[Route('/api/opportunities/{id}/analyze', name: 'opportunity_analyze', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $opportunity = $this->opportunityRepository->find($id);

        if (null === $opportunity) {
            return $this->errorResponse(
                Response::HTTP_NOT_FOUND,
                'Opportunité introuvable',
                sprintf("Aucune opportunité avec l'identifiant %d n'a été trouvée.", $id),
                'opportunity_not_found',
            );
        }

        $input = OpportunityAnalysisInputFactory::fromOpportunity($opportunity);

        try {
            $analysis = $this->analyzer->analyze($input);
        } catch (MissingApiKeyException $e) {
            return $this->errorResponse(
                Response::HTTP_SERVICE_UNAVAILABLE,
                "Analyse IA non configurée",
                "L'analyse IA n'est pas configurée : la clé OPENAI_API_KEY est absente.",
                'ai_not_configured',
            );
        } catch (InvalidAnalysisResponseException $e) {
            return $this->errorResponse(
                Response::HTTP_BAD_GATEWAY,
                "Réponse IA invalide",
                "Le fournisseur IA a renvoyé une réponse invalide ou inexploitable.",
                'ai_invalid_response',
            );
        } catch (LLMProviderException $e) {
            return $this->errorResponse(
                Response::HTTP_SERVICE_UNAVAILABLE,
                "Fournisseur IA indisponible",
                "Le fournisseur IA est momentanément indisponible. Veuillez réessayer plus tard.",
                'ai_provider_unavailable',
            );
        }

        $appliedFields = $this->applier->apply($opportunity, $analysis);

        if ([] !== $appliedFields) {
            $violations = $this->validator->validate($opportunity);

            if (\count($violations) > 0) {
                // Should not normally happen given the guards in
                // OpportunityAnalysisApplier::apply(), but we never flush an
                // invalid entity.
                return $this->errorResponse(
                    Response::HTTP_BAD_GATEWAY,
                    "Réponse IA invalide",
                    "L'application de l'analyse IA produirait une opportunité invalide.",
                    'ai_invalid_response',
                );
            }

            $this->entityManager->flush();

            // Émis uniquement après une analyse réussie ET persistée. Une
            // éventuelle exception dans une règle d'automatisation est
            // capturée par DomainEventBus et ne doit jamais faire échouer
            // cette réponse HTTP.
            $this->eventBus->dispatch(new OpportunityAnalyzed($opportunity, $analysis, $appliedFields));
        }

        $opportunityData = $this->normalizer->normalize($opportunity, null, ['groups' => ['opportunity:read']]);

        return new JsonResponse([
            'opportunity' => $opportunityData,
            'analysis' => [
                'score' => $analysis->score,
                'relevant' => $analysis->relevant,
                'technologies' => $analysis->technologies,
                'tjmMin' => $analysis->tjmMin,
                'tjmMax' => $analysis->tjmMax,
                'remote' => $analysis->remote,
                'location' => $analysis->location,
                'duration' => $analysis->duration,
                'startDate' => $analysis->startDate?->format('Y-m-d'),
                'reason' => $analysis->reason,
                'missingInformation' => $analysis->missingInformation,
            ],
            'appliedFields' => $appliedFields,
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
