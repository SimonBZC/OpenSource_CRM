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
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Index(columns: ['due_date'], name: 'idx_task_due_date')]
#[ORM\Index(columns: ['status'], name: 'idx_task_status')]
#[ApiResource(
    shortName: 'Task',
    description: 'Tâche opérationnelle liée (ou non) à une opportunité et/ou un contact.',
    operations: [new GetCollection(), new Get(), new Post(), new Put(), new Patch(), new Delete()],
    normalizationContext: ['groups' => ['task:read']],
    denormalizationContext: ['groups' => ['task:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['opportunity' => 'exact', 'contact' => 'exact'])]
#[ApiFilter(BackedEnumFilter::class, properties: ['status', 'priority'])]
#[ApiFilter(DateFilter::class, properties: ['dueDate'])]
#[ApiFilter(OrderFilter::class, properties: ['dueDate', 'createdAt', 'priority'])]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['task:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['task:read', 'task:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['task:read', 'task:write'])]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['task:read', 'task:write'])]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: TaskStatus::class)]
    #[Groups(['task:read', 'task:write'])]
    private TaskStatus $status = TaskStatus::TODO;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: TaskPriority::class)]
    #[Groups(['task:read', 'task:write'])]
    private TaskPriority $priority = TaskPriority::MEDIUM;

    #[ORM\Column]
    #[Groups(['task:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['task:read', 'task:write'])]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\ManyToOne(targetEntity: Opportunity::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['task:read', 'task:write'])]
    private ?Opportunity $opportunity = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['task:read', 'task:write'])]
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

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPriority(): TaskPriority
    {
        return $this->priority;
    }

    public function setPriority(TaskPriority $priority): static
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
            && $this->status !== TaskStatus::DONE
            && $this->status !== TaskStatus::CANCELLED
            && $this->dueDate < $now;
    }
}
