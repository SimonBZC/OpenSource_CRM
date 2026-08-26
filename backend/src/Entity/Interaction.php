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
use App\Enum\InteractionType;
use App\Repository\InteractionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InteractionRepository::class)]
#[ORM\Index(columns: ['occurred_at'], name: 'idx_interaction_occurred_at')]
#[ApiResource(
    shortName: 'Interaction',
    description: "Trace d'un échange (email, appel, LinkedIn, RDV...) avec un contact/une entreprise, éventuellement liée à une opportunité.",
    operations: [new GetCollection(), new Get(), new Post(), new Put(), new Patch(), new Delete()],
    normalizationContext: ['groups' => ['interaction:read']],
    denormalizationContext: ['groups' => ['interaction:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['company' => 'exact', 'contact' => 'exact', 'opportunity' => 'exact'])]
#[ApiFilter(BackedEnumFilter::class, properties: ['type'])]
#[ApiFilter(DateFilter::class, properties: ['occurredAt'])]
#[ApiFilter(OrderFilter::class, properties: ['occurredAt', 'createdAt'])]
class Interaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['interaction:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: InteractionType::class)]
    #[Groups(['interaction:read', 'interaction:write'])]
    #[Assert\NotNull]
    private InteractionType $type;

    #[ORM\Column]
    #[Groups(['interaction:read', 'interaction:write'])]
    #[Assert\NotNull]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['interaction:read', 'interaction:write'])]
    private ?string $content = null;

    #[ORM\ManyToOne(targetEntity: Contact::class, inversedBy: 'interactions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['interaction:read', 'interaction:write'])]
    private ?Contact $contact = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'interactions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['interaction:read', 'interaction:write'])]
    private ?Company $company = null;

    #[ORM\ManyToOne(targetEntity: Opportunity::class, inversedBy: 'interactions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['interaction:read', 'interaction:write'])]
    private ?Opportunity $opportunity = null;

    #[ORM\Column]
    #[Groups(['interaction:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): InteractionType
    {
        return $this->type;
    }

    public function setType(InteractionType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): static
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

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

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
