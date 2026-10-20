<?php

declare(strict_types=1);

namespace App\Tests\Ai;

use App\Ai\OpportunityAnalysisInputFactory;
use App\Entity\Company;
use App\Entity\Opportunity;
use PHPUnit\Framework\TestCase;

final class OpportunityAnalysisInputFactoryTest extends TestCase
{
    public function testFromOpportunityMapsAllFieldsWithCompany(): void
    {
        $company = (new Company())->setName('Acme Corp');
        $startDate = new \DateTimeImmutable('2026-09-01');

        $opportunity = (new Opportunity())
            ->setTitle('Développeur Symfony senior')
            ->setDescription('Mission longue durée')
            ->setCompany($company)
            ->setTechnologies(['PHP', 'Symfony'])
            ->setDailyRate(600)
            ->setRemote(true)
            ->setLocation('Paris')
            ->setDuration('6 mois')
            ->setStartDate($startDate);

        $input = OpportunityAnalysisInputFactory::fromOpportunity($opportunity);

        self::assertSame('Développeur Symfony senior', $input->title);
        self::assertSame('Mission longue durée', $input->description);
        self::assertSame('Acme Corp', $input->companyName);
        self::assertSame(['PHP', 'Symfony'], $input->technologies);
        self::assertSame(600, $input->dailyRate);
        self::assertTrue($input->remote);
        self::assertSame('Paris', $input->location);
        self::assertSame('6 mois', $input->duration);
        self::assertSame($startDate, $input->startDate);
    }

    public function testFromOpportunityWithoutCompanyReturnsNullCompanyName(): void
    {
        $opportunity = (new Opportunity())->setTitle('Mission sans entreprise');

        $input = OpportunityAnalysisInputFactory::fromOpportunity($opportunity);

        self::assertNull($input->companyName);
    }

    public function testFromOpportunityWithNullableFieldsUnsetMapsToNulls(): void
    {
        $opportunity = (new Opportunity())->setTitle('Mission minimale');

        $input = OpportunityAnalysisInputFactory::fromOpportunity($opportunity);

        self::assertSame('Mission minimale', $input->title);
        self::assertNull($input->description);
        self::assertNull($input->companyName);
        self::assertSame([], $input->technologies);
        self::assertNull($input->dailyRate);
        self::assertFalse($input->remote);
        self::assertNull($input->location);
        self::assertNull($input->duration);
        self::assertNull($input->startDate);
    }
}
