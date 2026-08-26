<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Ai\Exception\InvalidAnalysisResponseException;
use App\Ai\Exception\LLMProviderException;
use App\Ai\Exception\LLMRequestFailedException;
use App\Ai\Exception\MissingApiKeyException;
use App\Ai\LLMProviderInterface;
use App\Ai\OpportunityAnalysisApplier;
use App\Ai\OpportunityAnalyzer;
use App\Automation\DomainEventBus;
use App\Automation\Event\OpportunityAnalyzed;
use App\Controller\OpportunityAnalysisController;
use App\Entity\Opportunity;
use App\Repository\OpportunityRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Unit tests for App\Controller\OpportunityAnalysisController.
 *
 * App\Ai\OpportunityAnalyzer is declared `final`, so it cannot be created
 * via PHPUnit::createMock() (PHPUnit\Framework\MockObject\Generator\ClassIsFinalException).
 * Instead of mocking the analyzer, these tests wire a REAL OpportunityAnalyzer
 * (same instantiation pattern as tests/Ai/OpportunityAnalyzerTest.php) with a
 * mocked App\Ai\LLMProviderInterface: this keeps the tests free of any real
 * network/OpenAI call and lets us drive every analyzer outcome (success,
 * MissingApiKeyException, LLMRequestFailedException, a generic
 * LLMProviderException, InvalidAnalysisResponseException) deterministically,
 * while still exercising the controller against the exact same
 * OpportunityAnalyzer class it depends on in production.
 *
 * App\Repository\OpportunityRepository, Doctrine\ORM\EntityManagerInterface
 * and Symfony\Component\Validator\Validator\ValidatorInterface are mocked
 * directly with createMock(), since none of them is final.
 *
 * Symfony\Component\Serializer\SerializerInterface does NOT declare a
 * normalize() method (that lives on
 * Symfony\Component\Serializer\Normalizer\NormalizerInterface), yet the
 * controller calls $this->serializer->normalize(...) on a property typed
 * as SerializerInterface. A plain createMock(SerializerInterface::class)
 * therefore cannot be configured to stub normalize() (PHPUnit throws
 * MethodCannotBeConfiguredException). A small hand-written test double
 * implementing both interfaces is used instead - see
 * RecordingNormalizingSerializer below. See this test's final report for
 * why this is flagged as a potential typing issue in the controller.
 */
final class OpportunityAnalysisControllerTest extends TestCase
{
    private static ValidatorInterface $dtoValidator;

    public static function setUpBeforeClass(): void
    {
        self::$dtoValidator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function makeOpportunity(): Opportunity
    {
        return (new Opportunity())->setTitle('Développeur Symfony senior');
    }

    /**
     * @return array<string, mixed>
     */
    private function validLlmPayload(array $overrides = []): array
    {
        return array_merge([
            'score' => 80,
            'relevant' => true,
            'technologies' => ['PHP', 'Symfony'],
            'tjm_min' => null,
            'tjm_max' => null,
            'remote' => true,
            'location' => null,
            'duration' => null,
            'start_date' => null,
            'reason' => 'Bonne correspondance avec le profil recherché.',
            'missing_information' => [],
        ], $overrides);
    }

    /**
     * @return array{0: OpportunityAnalysisController, 1: OpportunityRepository&\PHPUnit\Framework\MockObject\MockObject, 2: EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject, 3: ValidatorInterface&\PHPUnit\Framework\MockObject\MockObject, 4: RecordingNormalizingSerializer, 5: LLMProviderInterface&\PHPUnit\Framework\MockObject\MockObject, 6: \ArrayObject<int, OpportunityAnalyzed>}
     */
    private function makeController(?ValidatorInterface $entityValidator = null): array
    {
        $repository = $this->createMock(OpportunityRepository::class);
        $llmProvider = $this->createMock(LLMProviderInterface::class);
        $analyzer = new OpportunityAnalyzer($llmProvider, self::$dtoValidator);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        if (null === $entityValidator) {
            $entityValidator = $this->createMock(ValidatorInterface::class);
            $entityValidator->method('validate')->willReturn(new ConstraintViolationList());
        }
        $serializer = new RecordingNormalizingSerializer();

        // App\Ai\OpportunityAnalysisApplier is dependency-free (see its class
        // doc-comment): a real instance is used so these tests keep exercising
        // the exact persistence policy logic they were written for, instead of
        // stubbing it away.
        $applier = new OpportunityAnalysisApplier();

        // App\Automation\DomainEventBus is `final` (same rationale as
        // OpportunityAnalyzer above), so it cannot be created via
        // createMock(). A real instance is wired with a real Symfony
        // EventDispatcher (also not final) and NO automation rules ([]):
        // these controller tests only care whether OpportunityAnalyzed is
        // forwarded to the dispatcher, not about any particular
        // AutomationRuleInterface reacting to it. A listener records every
        // OpportunityAnalyzed event dispatched, in $capturedEvents, so tests
        // can assert on emission (or absence of emission).
        $capturedEvents = new \ArrayObject();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            OpportunityAnalyzed::class,
            static function (OpportunityAnalyzed $event) use ($capturedEvents): void {
                $capturedEvents[] = $event;
            },
        );
        $eventBus = new DomainEventBus([], $dispatcher, new NullLogger());

        $controller = new OpportunityAnalysisController(
            $repository,
            $analyzer,
            $entityManager,
            $entityValidator,
            $serializer,
            $applier,
            $eventBus,
        );

        return [$controller, $repository, $entityManager, $entityValidator, $serializer, $llmProvider, $capturedEvents];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\JsonResponse::class, $response);
        $decoded = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    // --- 404 -------------------------------------------------------------

    public function testInvokeReturns404WithErrorCodeWhenOpportunityDoesNotExist(): void
    {
        [$controller, $repository, $entityManager, , , $llmProvider, $capturedEvents] = $this->makeController();

        $repository->expects(self::once())->method('find')->with(999)->willReturn(null);
        $llmProvider->expects(self::never())->method('completeJson');
        $entityManager->expects(self::never())->method('flush');

        $response = $controller->__invoke(999);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame('opportunity_not_found', $body['errorCode']);
        self::assertCount(0, $capturedEvents, 'OpportunityAnalyzed must not be dispatched when the opportunity is not found (404).');
    }

    // --- Exception mapping -------------------------------------------------

    public function testInvokeMapsMissingApiKeyExceptionTo503WithoutFlushingOrMutatingEntity(): void
    {
        $opportunity = $this->makeOpportunity();
        [$controller, $repository, $entityManager, , , $llmProvider, $capturedEvents] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willThrowException(new MissingApiKeyException('missing key'));
        $entityManager->expects(self::never())->method('flush');

        $response = $controller->__invoke(1);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('ai_not_configured', $this->decode($response)['errorCode']);
        self::assertNull($opportunity->getScore());
        self::assertNull($opportunity->getScoreReason());
        self::assertCount(0, $capturedEvents, 'OpportunityAnalyzed must not be dispatched when the AI analysis fails.');
    }

    public function testInvokeMapsLLMRequestFailedExceptionTo503WithoutFlushingOrMutatingEntity(): void
    {
        $opportunity = $this->makeOpportunity();
        [$controller, $repository, $entityManager, , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willThrowException(new LLMRequestFailedException('boom'));
        $entityManager->expects(self::never())->method('flush');

        $response = $controller->__invoke(1);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('ai_provider_unavailable', $this->decode($response)['errorCode']);
        self::assertNull($opportunity->getScore());
    }

    public function testInvokeMapsGenericLLMProviderExceptionTo503WithoutFlushingOrMutatingEntity(): void
    {
        $opportunity = $this->makeOpportunity();
        [$controller, $repository, $entityManager, , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        // Base LLMProviderException, not one of its more specific subclasses.
        $llmProvider->method('completeJson')->willThrowException(new LLMProviderException('generic failure'));
        $entityManager->expects(self::never())->method('flush');

        $response = $controller->__invoke(1);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('ai_provider_unavailable', $this->decode($response)['errorCode']);
        self::assertNull($opportunity->getScore());
    }

    public function testInvokeMapsInvalidAnalysisResponseExceptionTo502WithoutFlushingOrMutatingEntity(): void
    {
        $opportunity = $this->makeOpportunity();
        [$controller, $repository, $entityManager, , , $llmProvider, $capturedEvents] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        // score=150 is out of the [0,100] range enforced on OpportunityAnalysisResult,
        // so the real OpportunityAnalyzer naturally throws InvalidAnalysisResponseException.
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['score' => 150]));
        $entityManager->expects(self::never())->method('flush');

        $response = $controller->__invoke(1);

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertSame('ai_invalid_response', $this->decode($response)['errorCode']);
        self::assertNull($opportunity->getScore());
        self::assertCount(0, $capturedEvents, 'OpportunityAnalyzed must not be dispatched when the AI response is invalid.');
    }

    /**
     * Guard-rail: even when applyAnalysis() decided to write fields, the
     * controller must not flush an entity that fails validation, and must
     * report it the same way as an upstream invalid AI response.
     */
    public function testInvokeReturns502AndDoesNotFlushWhenPostApplicationValidationFails(): void
    {
        $opportunity = $this->makeOpportunity();

        $violations = $this->createMock(ConstraintViolationListInterface::class);
        $violations->method('count')->willReturn(1);
        $entityValidator = $this->createMock(ValidatorInterface::class);
        $entityValidator->method('validate')->willReturn($violations);

        [$controller, $repository, $entityManager, , , $llmProvider, $capturedEvents] = $this->makeController($entityValidator);

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload());

        $entityManager->expects(self::never())->method('flush');

        $response = $controller->__invoke(1);

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertSame('ai_invalid_response', $this->decode($response)['errorCode']);
        self::assertCount(0, $capturedEvents, 'OpportunityAnalyzed must not be dispatched when post-application validation fails.');
    }

    // --- Success response contract -----------------------------------------

    public function testInvokeReturns200WithExpectedResponseContractOnSuccess(): void
    {
        $opportunity = $this->makeOpportunity();
        [$controller, $repository, $entityManager, , $serializer, $llmProvider, $capturedEvents] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload([
            'score' => 80,
            'technologies' => ['PHP', 'Symfony'],
            'tjm_min' => 400,
            'tjm_max' => 600,
            'remote' => true,
            'location' => 'Paris',
            'duration' => '6 mois',
            'start_date' => '2026-09-01',
            'reason' => 'Bonne correspondance.',
            'missing_information' => ['Nom du client final'],
        ]));
        $serializer->returnValue = ['id' => 1, 'title' => $opportunity->getTitle()];

        $entityManager->expects(self::once())->method('flush');

        $response = $controller->__invoke(1);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertSame(['opportunity', 'analysis', 'appliedFields'], array_keys($body));
        self::assertSame($serializer->returnValue, $body['opportunity']);

        self::assertSame(
            ['score', 'relevant', 'technologies', 'tjmMin', 'tjmMax', 'remote', 'location', 'duration', 'startDate', 'reason', 'missingInformation'],
            array_keys($body['analysis']),
        );
        self::assertSame(80, $body['analysis']['score']);
        self::assertTrue($body['analysis']['relevant']);
        self::assertSame(['PHP', 'Symfony'], $body['analysis']['technologies']);
        self::assertSame(400, $body['analysis']['tjmMin']);
        self::assertSame(600, $body['analysis']['tjmMax']);
        self::assertTrue($body['analysis']['remote']);
        self::assertSame('Paris', $body['analysis']['location']);
        self::assertSame('6 mois', $body['analysis']['duration']);
        self::assertSame('2026-09-01', $body['analysis']['startDate']);
        self::assertSame('Bonne correspondance.', $body['analysis']['reason']);
        self::assertSame(['Nom du client final'], $body['analysis']['missingInformation']);

        self::assertContains('score', $body['appliedFields']);
        self::assertContains('scoreReason', $body['appliedFields']);

        // The serializer is invoked on the mutated opportunity, after
        // applyAnalysis() ran, using the opportunity:read normalization group.
        self::assertSame($opportunity, $serializer->lastObject);
        self::assertNull($serializer->lastFormat);
        self::assertSame(['groups' => ['opportunity:read']], $serializer->lastContext);

        // OpportunityAnalyzed must be dispatched via DomainEventBus after a
        // successful, flushed analysis (see App\Controller\OpportunityAnalysisController).
        self::assertCount(1, $capturedEvents);
        $dispatchedEvent = $capturedEvents[0];
        self::assertInstanceOf(\App\Automation\Event\OpportunityAnalyzed::class, $dispatchedEvent);
        self::assertSame($opportunity, $dispatchedEvent->opportunity);
        self::assertSame($body['appliedFields'], $dispatchedEvent->appliedFields);
    }

    public function testInvokeReturnsNullStartDateInAnalysisWhenAnalysisHasNoStartDate(): void
    {
        $opportunity = $this->makeOpportunity();
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['start_date' => null]));

        $response = $controller->__invoke(1);

        self::assertNull($this->decode($response)['analysis']['startDate']);
    }

    // --- Persistence policy: score / scoreReason ----------------------------

    public function testScoreAndScoreReasonAreAlwaysOverwrittenEvenWhenAlreadySet(): void
    {
        $opportunity = $this->makeOpportunity()->setScore(10)->setScoreReason('ancienne raison');
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload([
            'score' => 80,
            'reason' => 'nouvelle raison',
        ]));

        $response = $controller->__invoke(1);

        self::assertSame(80, $opportunity->getScore());
        self::assertSame('nouvelle raison', $opportunity->getScoreReason());
        $applied = $this->decode($response)['appliedFields'];
        self::assertContains('score', $applied);
        self::assertContains('scoreReason', $applied);
    }

    // --- Persistence policy: technologies ------------------------------------

    public function testTechnologiesAreAppliedWhenCurrentlyEmptyAndAnalysisNonEmpty(): void
    {
        $opportunity = $this->makeOpportunity();
        self::assertSame([], $opportunity->getTechnologies());
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['technologies' => ['PHP', 'Symfony']]));

        $response = $controller->__invoke(1);

        self::assertSame(['PHP', 'Symfony'], $opportunity->getTechnologies());
        self::assertContains('technologies', $this->decode($response)['appliedFields']);
    }

    public function testTechnologiesAreNotOverwrittenWhenAlreadyFilled(): void
    {
        $opportunity = $this->makeOpportunity()->setTechnologies(['PHP']);
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['technologies' => ['Go', 'Rust']]));

        $response = $controller->__invoke(1);

        self::assertSame(['PHP'], $opportunity->getTechnologies());
        self::assertNotContains('technologies', $this->decode($response)['appliedFields']);
    }

    public function testTechnologiesAreNotAppliedWhenCurrentlyEmptyAndAnalysisAlsoEmpty(): void
    {
        $opportunity = $this->makeOpportunity();
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['technologies' => []]));

        $response = $controller->__invoke(1);

        self::assertSame([], $opportunity->getTechnologies());
        self::assertNotContains('technologies', $this->decode($response)['appliedFields']);
    }

    // --- Persistence policy: dailyRateMin / dailyRateMax ---------------------

    public function testDailyRateMinAndMaxAreBothAppliedWhenBothCurrentlyNull(): void
    {
        $opportunity = $this->makeOpportunity();
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['tjm_min' => 400, 'tjm_max' => 600]));

        $response = $controller->__invoke(1);

        self::assertSame(400, $opportunity->getDailyRateMin());
        self::assertSame(600, $opportunity->getDailyRateMax());
        $applied = $this->decode($response)['appliedFields'];
        self::assertContains('dailyRateMin', $applied);
        self::assertContains('dailyRateMax', $applied);
    }

    public function testDailyRateMinIsNotOverwrittenWhenAlreadyFilled(): void
    {
        $opportunity = $this->makeOpportunity()->setDailyRateMin(300);
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        // tjm_max left null to isolate the min-overwrite behaviour from the
        // min/max invariant guard tested separately below.
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['tjm_min' => 900, 'tjm_max' => null]));

        $response = $controller->__invoke(1);

        self::assertSame(300, $opportunity->getDailyRateMin());
        self::assertNull($opportunity->getDailyRateMax());
        self::assertNotContains('dailyRateMin', $this->decode($response)['appliedFields']);
    }

    public function testDailyRateMaxIsNotOverwrittenWhenAlreadyFilled(): void
    {
        $opportunity = $this->makeOpportunity()->setDailyRateMax(500);
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['tjm_min' => null, 'tjm_max' => 999]));

        $response = $controller->__invoke(1);

        self::assertSame(500, $opportunity->getDailyRateMax());
        self::assertNull($opportunity->getDailyRateMin());
        self::assertNotContains('dailyRateMax', $this->decode($response)['appliedFields']);
    }

    public function testDailyRateMinIsNotAppliedWhenItWouldExceedExistingMax(): void
    {
        $opportunity = $this->makeOpportunity()->setDailyRateMax(400);
        self::assertNull($opportunity->getDailyRateMin());
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['tjm_min' => 900, 'tjm_max' => null]));

        $response = $controller->__invoke(1);

        self::assertNull($opportunity->getDailyRateMin());
        self::assertSame(400, $opportunity->getDailyRateMax());
        self::assertNotContains('dailyRateMin', $this->decode($response)['appliedFields']);
    }

    public function testDailyRateMaxIsNotAppliedWhenItWouldBeBelowExistingMin(): void
    {
        $opportunity = $this->makeOpportunity()->setDailyRateMin(500);
        self::assertNull($opportunity->getDailyRateMax());
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['tjm_min' => null, 'tjm_max' => 300]));

        $response = $controller->__invoke(1);

        self::assertNull($opportunity->getDailyRateMax());
        self::assertSame(500, $opportunity->getDailyRateMin());
        self::assertNotContains('dailyRateMax', $this->decode($response)['appliedFields']);
    }

    // --- Persistence policy: location / duration / startDate -----------------

    public function testLocationIsAppliedWhenCurrentlyNull(): void
    {
        $opportunity = $this->makeOpportunity();
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['location' => 'Lyon']));

        $response = $controller->__invoke(1);

        self::assertSame('Lyon', $opportunity->getLocation());
        self::assertContains('location', $this->decode($response)['appliedFields']);
    }

    public function testLocationIsNotOverwrittenWhenAlreadyFilled(): void
    {
        $opportunity = $this->makeOpportunity()->setLocation('Paris');
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['location' => 'Lyon']));

        $response = $controller->__invoke(1);

        self::assertSame('Paris', $opportunity->getLocation());
        self::assertNotContains('location', $this->decode($response)['appliedFields']);
    }

    public function testDurationIsAppliedWhenCurrentlyNull(): void
    {
        $opportunity = $this->makeOpportunity();
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['duration' => '3 mois']));

        $response = $controller->__invoke(1);

        self::assertSame('3 mois', $opportunity->getDuration());
        self::assertContains('duration', $this->decode($response)['appliedFields']);
    }

    public function testDurationIsNotOverwrittenWhenAlreadyFilled(): void
    {
        $opportunity = $this->makeOpportunity()->setDuration('6 mois');
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['duration' => '3 mois']));

        $response = $controller->__invoke(1);

        self::assertSame('6 mois', $opportunity->getDuration());
        self::assertNotContains('duration', $this->decode($response)['appliedFields']);
    }

    public function testStartDateIsAppliedWhenCurrentlyNull(): void
    {
        $opportunity = $this->makeOpportunity();
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['start_date' => '2026-09-01']));

        $response = $controller->__invoke(1);

        self::assertInstanceOf(\DateTimeImmutable::class, $opportunity->getStartDate());
        self::assertSame('2026-09-01', $opportunity->getStartDate()->format('Y-m-d'));
        self::assertContains('startDate', $this->decode($response)['appliedFields']);
    }

    public function testStartDateIsNotOverwrittenWhenAlreadyFilled(): void
    {
        $opportunity = $this->makeOpportunity()->setStartDate(new \DateTimeImmutable('2026-01-15'));
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['start_date' => '2026-09-01']));

        $response = $controller->__invoke(1);

        self::assertSame('2026-01-15', $opportunity->getStartDate()->format('Y-m-d'));
        self::assertNotContains('startDate', $this->decode($response)['appliedFields']);
    }

    // --- Persistence policy: remote -------------------------------------------

    public function testRemoteIsNeverModifiedEvenWhenAnalysisSuggestsADifferentValue(): void
    {
        $opportunity = $this->makeOpportunity()->setRemote(false);
        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload(['remote' => true]));

        $response = $controller->__invoke(1);

        self::assertFalse($opportunity->isRemote());
        self::assertNotContains('remote', $this->decode($response)['appliedFields']);
        // The AI suggestion is still surfaced to the caller in the analysis block.
        self::assertTrue($this->decode($response)['analysis']['remote']);
    }

    // --- Persistence policy: fields that must never be touched ---------------

    public function testNoOtherFieldIsEverModified(): void
    {
        $opportunity = $this->makeOpportunity()
            ->setTitle('Titre original')
            ->setDescription('Description originale')
            ->setUrl('https://example.com/mission-originale')
            ->setNotes('Notes originales');

        $originalStatus = $opportunity->getStatus();
        $originalSource = $opportunity->getSource();
        $originalCompany = $opportunity->getCompany();
        $originalContact = $opportunity->getContact();

        [$controller, $repository, , , , $llmProvider] = $this->makeController();

        $repository->method('find')->willReturn($opportunity);
        $llmProvider->method('completeJson')->willReturn($this->validLlmPayload());

        $controller->__invoke(1);

        self::assertSame('Titre original', $opportunity->getTitle());
        self::assertSame('Description originale', $opportunity->getDescription());
        self::assertSame('https://example.com/mission-originale', $opportunity->getUrl());
        self::assertSame('Notes originales', $opportunity->getNotes());
        self::assertSame($originalStatus, $opportunity->getStatus());
        self::assertSame($originalSource, $opportunity->getSource());
        self::assertSame($originalCompany, $opportunity->getCompany());
        self::assertSame($originalContact, $opportunity->getContact());
    }
}

/**
 * Hand-written test double implementing both SerializerInterface (the type
 * the controller's constructor declares) and NormalizerInterface (which is
 * where normalize() actually lives), so it can be injected in place of the
 * real `serializer` service and still respond to the normalize() call the
 * controller makes. See the class doc-comment on
 * OpportunityAnalysisControllerTest for why a plain
 * createMock(SerializerInterface::class) cannot be used here.
 */
final class RecordingNormalizingSerializer implements SerializerInterface, NormalizerInterface
{
    public mixed $lastObject = null;
    public ?string $lastFormat = null;
    /** @var array<string, mixed> */
    public array $lastContext = [];
    public mixed $returnValue = [];

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $this->lastObject = $object;
        $this->lastFormat = $format;
        $this->lastContext = $context;

        return $this->returnValue;
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return true;
    }

    public function serialize(mixed $data, string $format, array $context = []): string
    {
        throw new \LogicException('RecordingNormalizingSerializer::serialize() is not used by these tests.');
    }

    public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
    {
        throw new \LogicException('RecordingNormalizingSerializer::deserialize() is not used by these tests.');
    }
}
