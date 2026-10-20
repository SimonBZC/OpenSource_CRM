<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BackedEnumFilter;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Automation\State\OpportunityCreateProcessor;
use App\Enum\OpportunitySource;
use App\Enum\OpportunityStatus;
use App\Filter\OpportunitySearchFilter;
use App\Filter\TechnologyFilter;
use App\Repository\OpportunityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OpportunityRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['status'], name: 'idx_opportunity_status')]
#[ORM\Index(columns: ['source'], name: 'idx_opportunity_source')]
#[ApiResource(
    shortName: 'Opportunity',
    description: "Opportunité de mission freelance : cœur du pipeline de prospection (statut, TJM, technologies, scoring).",
    operations: [
        new GetCollection(),
        new Get(),
        // Décore le processor de persistance par défaut pour émettre
        // l'événement applicatif OpportunityCreated après une création
        // réussie (voir App\Automation\State\OpportunityCreateProcessor).
        // Scopé à cette seule opération Post : Put/Patch/Delete gardent le
        // comportement/processor standard d'API Platform.
        new Post(processor: OpportunityCreateProcessor::class),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['opportunity:read']],
    denormalizationContext: ['groups' => ['opportunity:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial', 'description' => 'partial', 'company' => 'exact', 'contact' => 'exact'])]
#[ApiFilter(BackedEnumFilter::class, properties: ['status', 'source'])]
#[ApiFilter(RangeFilter::class, properties: ['score', 'dailyRate'])]
#[ApiFilter(BooleanFilter::class, properties: ['remote'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'startDate'])]
#[ApiFilter(TechnologyFilter::class)]
#[ApiFilter(OpportunitySearchFilter::class)]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'updatedAt', 'score', 'dailyRate', 'startDate', 'title'])]
class Opportunity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['opportunity:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'opportunities')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    private ?Company $company = null;

    #[ORM\ManyToOne(targetEntity: Contact::class, inversedBy: 'opportunities')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    private ?Contact $contact = null;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: OpportunitySource::class)]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    private OpportunitySource $source = OpportunitySource::MANUAL;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: OpportunityStatus::class)]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    private OpportunityStatus $status = OpportunityStatus::NEW;

    /** TJM unique de la mission (€ / jour). */
    #[ORM\Column(nullable: true)]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    #[Assert\PositiveOrZero(message: 'Le TJM doit être positif ou nul.')]
    private ?int $dailyRate = null;

    #[ORM\Column]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    private bool $remote = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    #[Assert\Length(max: 100)]
    private ?string $duration = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    private ?\DateTimeImmutable $startDate = null;

    /** @var array<int, string> */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    private array $technologies = [];

    #[ORM\Column(nullable: true)]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    private ?int $score = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    private ?string $scoreReason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['opportunity:read', 'opportunity:write'])]
    private ?string $notes = null;

    #[ORM\Column]
    #[Groups(['opportunity:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['opportunity:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Interaction> */
    #[ORM\OneToMany(targetEntity: Interaction::class, mappedBy: 'opportunity')]
    #[Groups(['opportunity:read'])]
    private Collection $interactions;

    /** @var Collection<int, FollowUp> */
    #[ORM\OneToMany(targetEntity: FollowUp::class, mappedBy: 'opportunity')]
    #[Groups(['opportunity:read'])]
    private Collection $followUps;

    /** @var Collection<int, Alert> */
    #[ORM\OneToMany(targetEntity: Alert::class, mappedBy: 'opportunity')]
    #[Groups(['opportunity:read'])]
    private Collection $alerts;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->interactions = new ArrayCollection();
        $this->followUps = new ArrayCollection();
        $this->alerts = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    /**
     * Projection en lecture seule du nom de l'entreprise liée, pour éviter
     * un aller-retour client supplémentaire sans exposer l'objet Company
     * complet (et donc sans risque de cycle de sérialisation).
     */
    #[Groups(['opportunity:read'])]
    #[ApiProperty(writable: false)]
    public function getCompanyName(): ?string
    {
        return $this->company?->getName();
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getSource(): OpportunitySource
    {
        return $this->source;
    }

    public function setSource(OpportunitySource $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getStatus(): OpportunityStatus
    {
        return $this->status;
    }

    public function setStatus(OpportunityStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDailyRate(): ?int
    {
        return $this->dailyRate;
    }

    public function setDailyRate(?int $dailyRate): static
    {
        $this->dailyRate = $dailyRate;

        return $this;
    }

    public function isRemote(): bool
    {
        return $this->remote;
    }

    public function setRemote(bool $remote): static
    {
        $this->remote = $remote;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getDuration(): ?string
    {
        return $this->duration;
    }

    public function setDuration(?string $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getTechnologies(): array
    {
        return $this->technologies;
    }

    /**
     * @param array<int, string> $technologies
     */
    public function setTechnologies(array $technologies): static
    {
        $this->technologies = $technologies;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getScoreReason(): ?string
    {
        return $this->scoreReason;
    }

    public function setScoreReason(?string $scoreReason): static
    {
        $this->scoreReason = $scoreReason;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Interaction>
     */
    public function getInteractions(): Collection
    {
        return $this->interactions;
    }

    public function addInteraction(Interaction $interaction): static
    {
        if (!$this->interactions->contains($interaction)) {
            $this->interactions->add($interaction);
            $interaction->setOpportunity($this);
        }

        return $this;
    }

    public function removeInteraction(Interaction $interaction): static
    {
        if ($this->interactions->removeElement($interaction)) {
            if ($interaction->getOpportunity() === $this) {
                $interaction->setOpportunity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FollowUp>
     */
    public function getFollowUps(): Collection
    {
        return $this->followUps;
    }

    public function addFollowUp(FollowUp $followUp): static
    {
        if (!$this->followUps->contains($followUp)) {
            $this->followUps->add($followUp);
            $followUp->setOpportunity($this);
        }

        return $this;
    }

    public function removeFollowUp(FollowUp $followUp): static
    {
        if ($this->followUps->removeElement($followUp)) {
            if ($followUp->getOpportunity() === $this) {
                $followUp->setOpportunity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Alert>
     */
    public function getAlerts(): Collection
    {
        return $this->alerts;
    }

    public function addAlert(Alert $alert): static
    {
        if (!$this->alerts->contains($alert)) {
            $this->alerts->add($alert);
            $alert->setOpportunity($this);
        }

        return $this;
    }

    public function removeAlert(Alert $alert): static
    {
        if ($this->alerts->removeElement($alert)) {
            if ($alert->getOpportunity() === $this) {
                $alert->setOpportunity(null);
            }
        }

        return $this;
    }
}
