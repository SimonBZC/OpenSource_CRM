<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Enum\AlertPriority;
use App\Enum\AlertRuleType;
use App\Repository\AlertRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Réglage d'une règle d'alerte du catalogue fixe (Paramètres > Règles).
 *
 * Une ligne par type (créées par la migration) : pas de création ni de
 * suppression par l'API, seulement la lecture et la modification (PATCH) de
 * l'activation, du délai et de la priorité des alertes produites.
 * L'évaluation est déclenchée à la demande par
 * POST /api/alert_rules/evaluate (voir App\Controller\AlertRuleEvaluationController).
 */
#[ORM\Entity(repositoryClass: AlertRuleRepository::class)]
#[ApiResource(
    shortName: 'AlertRule',
    description: "Règle d'alerte configurable : activation, délai en jours et priorité des alertes créées.",
    operations: [new GetCollection(), new Get(), new Patch()],
    normalizationContext: ['groups' => ['alertRule:read']],
    denormalizationContext: ['groups' => ['alertRule:write']],
    order: ['id' => 'ASC'],
)]
class AlertRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['alertRule:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true, enumType: AlertRuleType::class)]
    #[Groups(['alertRule:read'])]
    #[ApiProperty(writable: false)]
    private AlertRuleType $type;

    #[ORM\Column]
    #[Groups(['alertRule:read', 'alertRule:write'])]
    private bool $enabled = true;

    #[ORM\Column]
    #[Groups(['alertRule:read', 'alertRule:write'])]
    #[Assert\PositiveOrZero(message: 'Le délai doit être un nombre de jours positif ou nul.')]
    // Borne haute : évite un dépassement de la colonne INT et des dates absurdes (10 ans max).
    #[Assert\LessThanOrEqual(value: 3650, message: 'Le délai ne peut pas dépasser 3650 jours.')]
    private int $delayDays = 0;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: AlertPriority::class)]
    #[Groups(['alertRule:read', 'alertRule:write'])]
    private AlertPriority $priority = AlertPriority::MEDIUM;

    public function __construct(AlertRuleType $type)
    {
        $this->type = $type;
        $this->delayDays = $type->defaultDelayDays();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): AlertRuleType
    {
        return $this->type;
    }

    /**
     * Libellé français du type, pour l'affichage.
     */
    #[Groups(['alertRule:read'])]
    #[ApiProperty(writable: false)]
    public function getLabel(): string
    {
        return $this->type->label();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getDelayDays(): int
    {
        return $this->delayDays;
    }

    public function setDelayDays(int $delayDays): static
    {
        $this->delayDays = $delayDays;

        return $this;
    }

    public function getPriority(): AlertPriority
    {
        return $this->priority;
    }

    public function setPriority(AlertPriority $priority): static
    {
        $this->priority = $priority;

        return $this;
    }
}
