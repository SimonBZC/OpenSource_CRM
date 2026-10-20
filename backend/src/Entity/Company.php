<?php

declare(strict_types=1);

namespace App\Entity;

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
use App\Enum\OpportunitySource;
use App\Repository\CompanyRepository;
use App\State\CompanyUniqueNameProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ApiResource(
    shortName: 'Company',
    description: 'Entreprise prospectée ou cliente, point central du CRM local.',
    operations: [
        new GetCollection(),
        new Get(),
        // Refus d'un doublon de nom (casse et espaces ignorés) à la création
        // comme à la modification : voir App\State\CompanyUniqueNameProcessor.
        new Post(processor: CompanyUniqueNameProcessor::class),
        new Put(processor: CompanyUniqueNameProcessor::class),
        new Patch(processor: CompanyUniqueNameProcessor::class),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['company:read']],
    denormalizationContext: ['groups' => ['company:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial', 'sector' => 'exact', 'location' => 'partial', 'source' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'createdAt', 'lastInteractionAt'])]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['company:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['company:read', 'company:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    #[Assert\Length(max: 255)]
    #[Assert\Url]
    private ?string $website = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    #[Assert\Length(max: 255)]
    private ?string $sector = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: OpportunitySource::class, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?OpportunitySource $source = null;

    #[ORM\Column]
    #[Groups(['company:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?\DateTimeImmutable $lastInteractionAt = null;

    /** @var Collection<int, Contact> */
    #[ORM\OneToMany(targetEntity: Contact::class, mappedBy: 'company')]
    #[Groups(['company:read'])]
    private Collection $contacts;

    /** @var Collection<int, Opportunity> */
    #[ORM\OneToMany(targetEntity: Opportunity::class, mappedBy: 'company')]
    #[Groups(['company:read'])]
    private Collection $opportunities;

    /** @var Collection<int, Interaction> */
    #[ORM\OneToMany(targetEntity: Interaction::class, mappedBy: 'company')]
    #[Groups(['company:read'])]
    private Collection $interactions;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->contacts = new ArrayCollection();
        $this->opportunities = new ArrayCollection();
        $this->interactions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        // Les espaces en début et fin de nom ne sont jamais significatifs
        // (règle de doublon d'entreprise, voir CompanyRepository::findOneByNameIgnoreCase()).
        $this->name = trim($name);

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getSector(): ?string
    {
        return $this->sector;
    }

    public function setSector(?string $sector): static
    {
        $this->sector = $sector;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getSource(): ?OpportunitySource
    {
        return $this->source;
    }

    public function setSource(?OpportunitySource $source): static
    {
        $this->source = $source;

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

    public function getLastInteractionAt(): ?\DateTimeImmutable
    {
        return $this->lastInteractionAt;
    }

    public function setLastInteractionAt(?\DateTimeImmutable $lastInteractionAt): static
    {
        $this->lastInteractionAt = $lastInteractionAt;

        return $this;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setCompany($this);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        if ($this->contacts->removeElement($contact)) {
            if ($contact->getCompany() === $this) {
                $contact->setCompany(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Opportunity>
     */
    public function getOpportunities(): Collection
    {
        return $this->opportunities;
    }

    public function addOpportunity(Opportunity $opportunity): static
    {
        if (!$this->opportunities->contains($opportunity)) {
            $this->opportunities->add($opportunity);
            $opportunity->setCompany($this);
        }

        return $this;
    }

    public function removeOpportunity(Opportunity $opportunity): static
    {
        if ($this->opportunities->removeElement($opportunity)) {
            if ($opportunity->getCompany() === $this) {
                $opportunity->setCompany(null);
            }
        }

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
            $interaction->setCompany($this);
        }

        return $this;
    }

    public function removeInteraction(Interaction $interaction): static
    {
        if ($this->interactions->removeElement($interaction)) {
            if ($interaction->getCompany() === $this) {
                $interaction->setCompany(null);
            }
        }

        return $this;
    }
}
