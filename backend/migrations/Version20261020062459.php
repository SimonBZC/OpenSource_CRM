<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renommage de la notion « Tâche » en « Alerte » (FR-8).
 *
 * Renommage pur, sans perte de données : la table `task` devient `alert`, et
 * ses index, contraintes, clé primaire et séquence d'identité prennent les
 * noms que Doctrine génère pour l'entité App\Entity\Alert. Aucun DROP/CREATE :
 * toutes les lignes existantes sont conservées. Seule la description des
 * alertes auto-générées par la règle « score >= 80 » est réécrite : son
 * préfixe « Tâche créée automatiquement… » devient « Alerte créée
 * automatiquement… », comme le texte produit désormais par la règle.
 */
final class Version20261020062459 extends AbstractMigration
{
    // Titre des alertes auto-générées (CreateAlertForHighScoreOpportunityRule::AUTO_ALERT_TITLE),
    // recopié ici : une migration ne dépend pas du code applicatif, qui peut évoluer.
    private const AUTO_TITLE = 'Relancer rapidement — fort potentiel';
    private const OLD_PREFIX = "Tâche créée automatiquement par l'analyse IA";
    private const NEW_PREFIX = "Alerte créée automatiquement par l'analyse IA";

    public function getDescription(): string
    {
        return 'Renomme la table task en alert (index, clés étrangères, clé primaire, séquence), sans perte de données.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task RENAME TO alert');
        $this->addSql('ALTER SEQUENCE task_id_seq RENAME TO alert_id_seq');
        $this->addSql('ALTER INDEX task_pkey RENAME TO alert_pkey');
        $this->addSql('ALTER INDEX idx_527edb259a34590f RENAME TO IDX_17FD46C19A34590F');
        $this->addSql('ALTER INDEX idx_527edb25e7a1254a RENAME TO IDX_17FD46C1E7A1254A');
        $this->addSql('ALTER INDEX idx_task_due_date RENAME TO idx_alert_due_date');
        $this->addSql('ALTER INDEX idx_task_status RENAME TO idx_alert_status');
        $this->addSql('ALTER TABLE alert RENAME CONSTRAINT fk_527edb259a34590f TO FK_17FD46C19A34590F');
        $this->addSql('ALTER TABLE alert RENAME CONSTRAINT fk_527edb25e7a1254a TO FK_17FD46C1E7A1254A');
        $this->addSql(
            'UPDATE alert SET description = :new || SUBSTRING(description FROM CHAR_LENGTH(:old) + 1) WHERE title = :title AND description LIKE :oldPrefix',
            ['new' => self::NEW_PREFIX, 'old' => self::OLD_PREFIX, 'title' => self::AUTO_TITLE, 'oldPrefix' => self::OLD_PREFIX.'%'],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'UPDATE alert SET description = :old || SUBSTRING(description FROM CHAR_LENGTH(:new) + 1) WHERE title = :title AND description LIKE :newPrefix',
            ['new' => self::NEW_PREFIX, 'old' => self::OLD_PREFIX, 'title' => self::AUTO_TITLE, 'newPrefix' => self::NEW_PREFIX.'%'],
        );
        $this->addSql('ALTER TABLE alert RENAME CONSTRAINT FK_17FD46C1E7A1254A TO fk_527edb25e7a1254a');
        $this->addSql('ALTER TABLE alert RENAME CONSTRAINT FK_17FD46C19A34590F TO fk_527edb259a34590f');
        $this->addSql('ALTER INDEX idx_alert_status RENAME TO idx_task_status');
        $this->addSql('ALTER INDEX idx_alert_due_date RENAME TO idx_task_due_date');
        $this->addSql('ALTER INDEX IDX_17FD46C1E7A1254A RENAME TO idx_527edb25e7a1254a');
        $this->addSql('ALTER INDEX IDX_17FD46C19A34590F RENAME TO idx_527edb259a34590f');
        $this->addSql('ALTER INDEX alert_pkey RENAME TO task_pkey');
        $this->addSql('ALTER SEQUENCE alert_id_seq RENAME TO task_id_seq');
        $this->addSql('ALTER TABLE alert RENAME TO task');
    }
}
