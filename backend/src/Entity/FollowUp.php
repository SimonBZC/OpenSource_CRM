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
use App\Enum\FollowUpStatus;
use App\Repository\FollowUpRepository;
use App\State\FollowUpDeletionGuardProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FollowUpRepository::class)]
#[ORM\Index(columns: ['scheduled_at'], name: 'idx_follow_up_scheduled_at')]
#[ORM\Index(columns: ['status'], name: 'idx_follow_up_status')]
#[ApiResource(
    shortName: 'FollowUp',
    description: 'Relance planifiée auprès d\'une entreprise/contact, éventuellement liée à une opportunité.',
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Patch(),
        // Une relance terminée ne peut pas être supprimée : voir App\State\FollowUpDeletionGuardProcessor.
        new Delete(processor: FollowUpDeletionGuardProcessor::class),
    ],
    normalizationContext: ['groups' => ['followUp:read']],
    denormalizationContext: ['groups' => ['followUp:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['company' => 'exact', 'contact' => 'exact', 'opportunity' => 'exact'])]
#[ApiFilter(BackedEnumFilter::class, properties: ['status'])]
#[ApiFilter(DateFilter::class, properties: ['scheduledAt'])]
#[ApiFilter(OrderFilter::class, properties: ['scheduledAt', 'createdAt', 'status'])]
class FollowUp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['followUp:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['followUp:read', 'followUp:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['followUp:read', 'followUp:write'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['followUp:read', 'followUp:write'])]
    #[Assert\NotNull]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: FollowUpStatus::class)]
    #[Groups(['followUp:read', 'followUp:write'])]
    private FollowUpStatus $status = FollowUpStatus::TODO;

    #[ORM\Column(nullable: true)]
    #[Groups(['followUp:read', 'followUp:write'])]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column]
    #[Groups(['followUp:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['followUp:read', 'followUp:write'])]
    private ?Company $company = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['followUp:read', 'followUp:write'])]
    private ?Contact $contact = null;

    #[ORM\ManyToOne(targetEntity: Opportunity::class, inversedBy: 'followUps')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['followUp:read', 'followUp:write'])]
    private ?Opportunity $opportunity = null;

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

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getStatus(): FollowUpStatus
    {
        return $this->status;
    }

    public function setStatus(FollowUpStatus $status): static
    {
        $this->status = $status;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

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

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): static
    {
        $this->contact = $contact;

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

    public function isOverdue(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->status === FollowUpStatus::TODO && $this->scheduledAt < $now;
    }
}
