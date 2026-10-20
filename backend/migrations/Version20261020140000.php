<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Opportunités : TJM unique et suppression de l'URL.
 *
 * - ajoute `daily_rate`, rempli avec `daily_rate_max`, à défaut `daily_rate_min` ;
 * - supprime `daily_rate_min`, `daily_rate_max` et `url`.
 *
 * Destructif : les URL existantes et l'écart min/max sont perdus. À lancer
 * sur la vraie base uniquement après sauvegarde.
 *
 * `down` est une réversibilité « au mieux » : il recrée `daily_rate_min` et
 * `daily_rate_max` tous deux égaux à `daily_rate`, et `url` à NULL (les URL
 * supprimées ne sont pas récupérables).
 */
final class Version20261020140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Opportunités : remplace daily_rate_min/daily_rate_max par daily_rate et supprime url.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE opportunity ADD daily_rate INT DEFAULT NULL');
        $this->addSql('UPDATE opportunity SET daily_rate = COALESCE(daily_rate_max, daily_rate_min)');
        $this->addSql('ALTER TABLE opportunity DROP daily_rate_min');
        $this->addSql('ALTER TABLE opportunity DROP daily_rate_max');
        $this->addSql('ALTER TABLE opportunity DROP url');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE opportunity ADD url VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE opportunity ADD daily_rate_min INT DEFAULT NULL');
        $this->addSql('ALTER TABLE opportunity ADD daily_rate_max INT DEFAULT NULL');
        $this->addSql('UPDATE opportunity SET daily_rate_min = daily_rate, daily_rate_max = daily_rate');
        $this->addSql('ALTER TABLE opportunity DROP daily_rate');
    }
}
