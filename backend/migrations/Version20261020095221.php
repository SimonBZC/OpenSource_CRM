<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Recalage des dates enregistrées en « faux UTC ».
 *
 * Avant la correction du frontend, les formulaires envoyaient l'heure locale
 * saisie suffixée `+00:00` : `follow_up.scheduled_at` et `alert.due_date`
 * contiennent donc l'heure de Paris au lieu de l'heure UTC. Cette migration
 * les convertit avec `(col AT TIME ZONE 'Europe/Paris') AT TIME ZONE 'UTC'`,
 * ce qui gère l'heure d'été ligne par ligne.
 *
 * Seules les lignes créées avant la mise en service de la correction sont
 * concernées. Les alertes auto-générées par la règle « score >= 80 » sont
 * exclues (déjà en vrai UTC), ainsi que les alertes sans échéance.
 * `created_at` et `completed_at`, déjà en vrai UTC, ne sont pas touchés : la
 * sélection est donc identique en `down()`, qui applique la conversion inverse.
 *
 * Limites :
 * - Correctif ponctuel destiné uniquement à la vraie base de Simon, non
 *   idempotent : sur une base neuve ou restaurée contenant des dates déjà en
 *   vrai UTC créées avant la date limite (ex. fixtures), il décalerait à tort.
 * - À lancer avant toute modification d'une ancienne ligne (créée avant la
 *   date limite) via le formulaire corrigé.
 * - `down()` n'est pas un inverse exact pour les heures tombant dans le trou
 *   du passage à l'heure d'été (heures locales inexistantes).
 * - Valeurs injectées en littéraux SQL (pas de paramètres liés) pour que
 *   `--dry-run` / `--write-sql` affichent la requête exacte.
 */
final class Version20261020095221 extends AbstractMigration
{
    // Constantes écrites en dur : une migration ne dépend pas du code applicatif.
    // Mise en service de la correction : 2026-10-20 11h25 heure de Paris, soit 09h25 UTC.
    private const CUTOFF = '2026-10-20 09:25:00';
    // Titre des alertes auto-générées (CreateAlertForHighScoreOpportunityRule::AUTO_ALERT_TITLE).
    private const AUTO_TITLE = 'Relancer rapidement — fort potentiel';
    private const LOCAL_TIMEZONE = 'Europe/Paris';

    public function getDescription(): string
    {
        return 'Recale en vrai UTC les dates de relance et d\'échéance d\'alerte saisies en heure de Paris avant la correction du fuseau.';
    }

    public function up(Schema $schema): void
    {
        $this->convert(self::LOCAL_TIMEZONE, 'UTC');
    }

    public function down(Schema $schema): void
    {
        $this->convert('UTC', self::LOCAL_TIMEZONE);
    }

    /**
     * Réinterprète les dates sélectionnées comme exprimées dans $from et les
     * réécrit dans $to.
     */
    private function convert(string $from, string $to): void
    {
        $this->addSql(sprintf(
            "UPDATE follow_up SET scheduled_at = (scheduled_at AT TIME ZONE '%s') AT TIME ZONE '%s' WHERE created_at < '%s'",
            $from, $to, self::CUTOFF,
        ));
        $this->addSql(sprintf(
            "UPDATE alert SET due_date = (due_date AT TIME ZONE '%s') AT TIME ZONE '%s' WHERE created_at < '%s' AND due_date IS NOT NULL AND title <> '%s'",
            $from, $to, self::CUTOFF, self::AUTO_TITLE,
        ));
    }
}
