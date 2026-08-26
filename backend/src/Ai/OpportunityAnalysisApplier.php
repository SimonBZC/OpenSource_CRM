<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Dto\OpportunityAnalysisResult;
use App\Entity\Opportunity;

/**
 * Applies the AI analysis result onto the Opportunity entity, following
 * the persistence policy below. The goal is to let the AI enrich an
 * opportunity without ever overwriting data a user entered by hand.
 *
 * - score / scoreReason: ALWAYS overwritten with analysis.score /
 *   analysis.reason — scoring is the very purpose of this analysis.
 * - technologies: applied ONLY if the current array is empty AND the
 *   analysis provides a non-empty list.
 * - dailyRateMin / dailyRateMax: applied ONLY if currently null and the
 *   corresponding analysis value is non-null. If applying only one of
 *   the two would break the entity's dailyRateMin <= dailyRateMax
 *   invariant (e.g. only min is null and the suggested min exceeds the
 *   existing max), that field is left untouched rather than writing an
 *   invalid combination.
 * - location / duration / startDate: applied ONLY if currently null and
 *   the analysis value is non-null.
 * - remote: NEVER modified automatically. `remote` is a non-nullable
 *   boolean defaulting to false, so there is no way to distinguish
 *   "never set" from "deliberately set to false" by a user. Overwriting
 *   it automatically could silently destroy a manual entry. The value
 *   suggested by the AI is still returned in the `analysis` part of the
 *   response so the caller can decide to apply it explicitly.
 * - No other field (title, description, status, source, url, notes,
 *   company, contact, system dates) is ever touched here.
 *
 * This service is intentionally stateless and dependency-free (no
 * EntityManager, no repository): it only mutates the Opportunity instance
 * it is given. Flushing/persisting is the caller's responsibility (see
 * App\Controller\OpportunityAnalysisController), which also decides what
 * happens after (validation, event dispatch...).
 */
final class OpportunityAnalysisApplier
{
    /**
     * @return list<string> names of the Opportunity fields actually written
     */
    public function apply(Opportunity $opportunity, OpportunityAnalysisResult $analysis): array
    {
        $appliedFields = [];

        $opportunity->setScore($analysis->score);
        $appliedFields[] = 'score';

        $opportunity->setScoreReason($analysis->reason);
        $appliedFields[] = 'scoreReason';

        if ([] === $opportunity->getTechnologies() && [] !== $analysis->technologies) {
            $opportunity->setTechnologies($analysis->technologies);
            $appliedFields[] = 'technologies';
        }

        $currentMin = $opportunity->getDailyRateMin();
        $currentMax = $opportunity->getDailyRateMax();

        if (null === $currentMin && null !== $analysis->tjmMin) {
            $effectiveMax = $currentMax;
            if (null === $effectiveMax || $analysis->tjmMin <= $effectiveMax) {
                $opportunity->setDailyRateMin($analysis->tjmMin);
                $appliedFields[] = 'dailyRateMin';
            }
        }

        if (null === $currentMax && null !== $analysis->tjmMax) {
            $effectiveMin = $opportunity->getDailyRateMin();
            if (null === $effectiveMin || $effectiveMin <= $analysis->tjmMax) {
                $opportunity->setDailyRateMax($analysis->tjmMax);
                $appliedFields[] = 'dailyRateMax';
            }
        }

        if (null === $opportunity->getLocation() && null !== $analysis->location) {
            $opportunity->setLocation($analysis->location);
            $appliedFields[] = 'location';
        }

        if (null === $opportunity->getDuration() && null !== $analysis->duration) {
            $opportunity->setDuration($analysis->duration);
            $appliedFields[] = 'duration';
        }

        if (null === $opportunity->getStartDate() && null !== $analysis->startDate) {
            $opportunity->setStartDate($analysis->startDate);
            $appliedFields[] = 'startDate';
        }

        // remote is intentionally never written here (see class PHPDoc).

        return $appliedFields;
    }
}
