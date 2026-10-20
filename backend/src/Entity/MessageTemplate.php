<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\MessageTemplateRepository;
use App\State\MessageTemplateUniqueTitleProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Modèle de message réutilisable (Paramètres > Messages).
 *
 * Le corps peut contenir des indicateurs `{%prenom%}`, `{%nom%}` et
 * `{%entreprise%}`, remplacés par les données d'un contact au rendu :
 * voir App\Messaging\MessageTemplateRenderer.
 */
#[ORM\Entity(repositoryClass: MessageTemplateRepository::class)]
#[ApiResource(
    shortName: 'MessageTemplate',
    description: 'Modèle de message réutilisable, avec indicateurs remplacés par les données d\'un contact.',
    operations: [
        new GetCollection(),
        new Get(),
        // Refus d'un doublon de titre (casse et espaces ignorés) à la création
        // comme à la modification : voir App\State\MessageTemplateUniqueTitleProcessor.
        new Post(processor: MessageTemplateUniqueTitleProcessor::class),
        new Put(processor: MessageTemplateUniqueTitleProcessor::class),
        new Patch(processor: MessageTemplateUniqueTitleProcessor::class),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['messageTemplate:read']],
    denormalizationContext: ['groups' => ['messageTemplate:write']],
)]
#[ApiFilter(OrderFilter::class, properties: ['title'])]
class MessageTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['messageTemplate:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['messageTemplate:read', 'messageTemplate:write'])]
    // Un titre ou un corps fait seulement d'espaces est refusé comme un champ vide.
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['messageTemplate:read', 'messageTemplate:write'])]
    #[Assert\NotBlank(normalizer: 'trim')]
    private string $body;

    #[ORM\Column]
    #[Groups(['messageTemplate:read'])]
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

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
