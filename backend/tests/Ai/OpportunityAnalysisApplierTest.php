<?php

declare(strict_types=1);

namespace App\Tests\Ai;

use App\Ai\Dto\OpportunityAnalysisResult;
use App\Ai\OpportunityAnalysisApplier;
use App\Entity\Opportunity;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires directs et isolés de App\Ai\OpportunityAnalysisApplier
 * (extraite de l'ancienne méthode privée applyAnalysis() du contrôleur ;
 * voir aussi les tests de politique de persistance couvrant le même
 * comportement via le contrôleur dans
 * tests/Controller/OpportunityAnalysisControllerTest.php).
 */
final class OpportunityAnalysisApplierTest extends TestCase
{
    private function makeOpportunity(): Opportunity
    {
        return (new Opportunity())->setTitle('Développeur Symfony senior');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeAnalysis(array $overrides = []): OpportunityAnalysisResult
    {
        $defaults = [
            'score' => 80,
            'relevant' => true,
            'technologies' => ['PHP', 'Symfony'],
            'tjmMin' => null,
            'tjmMax' => null,
            'remote' => true,
            'location' => null,
            'duration' => null,
            'startDate' => null,
            'reason' => 'Bonne correspondance.',
            'missingInformation' => [],
        ];
        $values = array_merge($defaults, $overrides);

        return new OpportunityAnalysisResult(...$values);
    }

    // --- score / scoreReason : toujours écrasés -------------------------------

    public function testScoreAndScoreReasonAreAlwaysOverwritten(): void
    {
        $opportunity = $this->makeOpportunity()->setScore(10)->setScoreReason('ancienne raison');
        $applier = new OpportunityAnalysisApplier();

        $applied = $applier->apply($opportunity, $this->makeAnalysis(['score' => 95, 'reason' => 'nouvelle raison']));

        self::assertSame(95, $opportunity->getScore());
        self::assertSame('nouvelle raison', $opportunity->getScoreReason());
        self::assertContains('score', $applied);
        self::assertContains('scoreReason', $applied);
    }

    // --- technologies ----------------------------------------------------------

    public function testTechnologiesAppliedOnlyWhenCurrentlyEmptyAndAnalysisNonEmpty(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity();
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['technologies' => ['Go', 'Rust']]));
        self::assertSame(['Go', 'Rust'], $opportunity->getTechnologies());
        self::assertContains('technologies', $applied);
    }

    public function testTechnologiesNotOverwrittenWhenAlreadyFilled(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity()->setTechnologies(['PHP']);
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['technologies' => ['Go', 'Rust']]));
        self::assertSame(['PHP'], $opportunity->getTechnologies());
        self::assertNotContains('technologies', $applied);
    }

    public function testTechnologiesNotAppliedWhenAnalysisListIsEmpty(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity();
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['technologies' => []]));
        self::assertSame([], $opportunity->getTechnologies());
        self::assertNotContains('technologies', $applied);
    }

    // --- dailyRateMin / dailyRateMax -------------------------------------------

    public function testBothDailyRatesAppliedWhenBothCurrentlyNull(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity();
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['tjmMin' => 400, 'tjmMax' => 600]));

        self::assertSame(400, $opportunity->getDailyRateMin());
        self::assertSame(600, $opportunity->getDailyRateMax());
        self::assertContains('dailyRateMin', $applied);
        self::assertContains('dailyRateMax', $applied);
    }

    public function testDailyRateMinNotOverwrittenWhenAlreadyFilled(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity()->setDailyRateMin(300);
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['tjmMin' => 900, 'tjmMax' => null]));

        self::assertSame(300, $opportunity->getDailyRateMin());
        self::assertNull($opportunity->getDailyRateMax());
        self::assertNotContains('dailyRateMin', $applied);
    }

    public function testDailyRateMaxNotOverwrittenWhenAlreadyFilled(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity()->setDailyRateMax(500);
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['tjmMin' => null, 'tjmMax' => 999]));

        self::assertSame(500, $opportunity->getDailyRateMax());
        self::assertNull($opportunity->getDailyRateMin());
        self::assertNotContains('dailyRateMax', $applied);
    }

    public function testDailyRateMinNotAppliedWhenItWouldExceedExistingMax(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity()->setDailyRateMax(400);
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['tjmMin' => 900, 'tjmMax' => null]));

        self::assertNull($opportunity->getDailyRateMin());
        self::assertSame(400, $opportunity->getDailyRateMax());
        self::assertNotContains('dailyRateMin', $applied);
    }

    public function testDailyRateMaxNotAppliedWhenItWouldBeBelowExistingMin(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity()->setDailyRateMin(500);
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['tjmMin' => null, 'tjmMax' => 300]));

        self::assertNull($opportunity->getDailyRateMax());
        self::assertSame(500, $opportunity->getDailyRateMin());
        self::assertNotContains('dailyRateMax', $applied);
    }

    public function testDailyRateMinAppliedAtExactBoundaryOfExistingMax(): void
    {
        // tjmMin == currentMax est autorisé (invariant min <= max, pas min < max).
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity()->setDailyRateMax(400);
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['tjmMin' => 400, 'tjmMax' => null]));

        self::assertSame(400, $opportunity->getDailyRateMin());
        self::assertContains('dailyRateMin', $applied);
    }

    // --- location / duration / startDate ---------------------------------------

    public function testLocationAppliedWhenCurrentlyNull(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity();
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['location' => 'Lyon']));

        self::assertSame('Lyon', $opportunity->getLocation());
        self::assertContains('location', $applied);
    }

    public function testLocationNotOverwrittenWhenAlreadyFilled(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity()->setLocation('Paris');
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['location' => 'Lyon']));

        self::assertSame('Paris', $opportunity->getLocation());
        self::assertNotContains('location', $applied);
    }

    public function testDurationAppliedWhenCurrentlyNull(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity();
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['duration' => '3 mois']));

        self::assertSame('3 mois', $opportunity->getDuration());
        self::assertContains('duration', $applied);
    }

    public function testDurationNotOverwrittenWhenAlreadyFilled(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity()->setDuration('6 mois');
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['duration' => '3 mois']));

        self::assertSame('6 mois', $opportunity->getDuration());
        self::assertNotContains('duration', $applied);
    }

    public function testStartDateAppliedWhenCurrentlyNull(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity();
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['startDate' => new \DateTimeImmutable('2026-09-01')]));

        self::assertInstanceOf(\DateTimeImmutable::class, $opportunity->getStartDate());
        self::assertSame('2026-09-01', $opportunity->getStartDate()->format('Y-m-d'));
        self::assertContains('startDate', $applied);
    }

    public function testStartDateNotOverwrittenWhenAlreadyFilled(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity()->setStartDate(new \DateTimeImmutable('2026-01-15'));
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['startDate' => new \DateTimeImmutable('2026-09-01')]));

        self::assertSame('2026-01-15', $opportunity->getStartDate()->format('Y-m-d'));
        self::assertNotContains('startDate', $applied);
    }

    // --- remote : jamais écrit ---------------------------------------------------

    public function testRemoteIsNeverModified(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity()->setRemote(false);
        $applied = $applier->apply($opportunity, $this->makeAnalysis(['remote' => true]));

        self::assertFalse($opportunity->isRemote());
        self::assertNotContains('remote', $applied);
    }

    // --- appliedFields : contenu exact -----------------------------------------

    public function testAppliedFieldsListsExactlyTheFieldsActuallyWritten(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity();
        $applied = $applier->apply($opportunity, $this->makeAnalysis([
            'technologies' => ['PHP'],
            'tjmMin' => 400,
            'tjmMax' => 600,
            'location' => 'Lyon',
            'duration' => '3 mois',
            'startDate' => new \DateTimeImmutable('2026-09-01'),
        ]));

        self::assertSame(
            ['score', 'scoreReason', 'technologies', 'dailyRateMin', 'dailyRateMax', 'location', 'duration', 'startDate'],
            $applied,
        );
    }

    public function testAppliedFieldsContainsOnlyScoreAndScoreReasonWhenNothingElseApplies(): void
    {
        $applier = new OpportunityAnalysisApplier();

        $opportunity = $this->makeOpportunity()
            ->setTechnologies(['PHP'])
            ->setDailyRateMin(300)
            ->setDailyRateMax(500)
            ->setLocation('Paris')
            ->setDuration('6 mois')
            ->setStartDate(new \DateTimeImmutable('2026-01-15'));

        $applied = $applier->apply($opportunity, $this->makeAnalysis());

        self::assertSame(['score', 'scoreReason'], $applied);
    }
}
