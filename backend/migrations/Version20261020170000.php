<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Contacts inactifs : ajoute `contact.active` (vrai pour les contacts
 * existants, qui restent tous actifs).
 *
 * Non destructif. `down` supprime la colonne.
 */
final class Version20261020170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Statut actif/inactif sur le contact (tous les contacts existants actifs).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact ADD active BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact DROP active');
    }
}
