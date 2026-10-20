<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BackedEnumFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
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
use App\Enum\AlertPriority;
use App\Enum\AlertStatus;
use App\Repository\AlertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
#[ORM\Index(columns: ['due_date'], name: 'idx_alert_due_date')]
#[ORM\Index(columns: ['status'], name: 'idx_alert_status')]
#[ApiResource(
    shortName: 'Alert',
    description: 'Alerte opérationnelle liée (ou non) à une opportunité et/ou un contact.',
    operations: [new GetCollection(), new Get(), new Post(), new Put(), new Patch(), new Delete()],
    normalizationContext: ['groups' => ['alert:read']],
    denormalizationContext: ['groups' => ['alert:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['opportunity' => 'exact', 'contact' => 'exact'])]
#[ApiFilter(BackedEnumFilter::class, properties: ['status', 'priority'])]
#[ApiFilter(DateFilter::class, properties: ['dueDate'])]
#[ApiFilter(OrderFilter::class, properties: ['dueDate', 'createdAt', 'priority'])]
class Alert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['alert:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['alert:read', 'alert:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['alert:read', 'alert:write'])]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['alert:read', 'alert:write'])]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: AlertStatus::class)]
    #[Groups(['alert:read', 'alert:write'])]
    private AlertStatus $status = AlertStatus::TODO;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: AlertPriority::class)]
    #[Groups(['alert:read', 'alert:write'])]
    private AlertPriority $priority = AlertPriority::MEDIUM;

    #[ORM\Column]
    #[Groups(['alert:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['alert:read', 'alert:write'])]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\ManyToOne(targetEntity: Opportunity::class, inversedBy: 'alerts')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['alert:read', 'alert:write'])]
    private ?Opportunity $opportunity = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['alert:read', 'alert:write'])]
    private ?Contact $contact = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getStatus(): AlertStatus
    {
        return $this->status;
    }

    public function setStatus(AlertStatus $status): static
    {
        $this->status = $status;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getOpportunity(): ?Opportunity
    {
        return $this->opportunity;
    }

    public function setOpportunity(?Opportunity $opportunity): static
    {
        $this->opportunity = $opportunity;

        return $this;
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

    public function isOverdue(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->dueDate !== null
            && $this->status !== AlertStatus::DONE
            && $this->status !== AlertStatus::CANCELLED
            && $this->dueDate < $now;
    }
}
