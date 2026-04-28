<?php

namespace App\Entity\Tasks;

use Doctrine\ORM\Mapping as ORM;

use App\Repository\Tasks\TaskRepository;
use App\Support\PlainTextSanitizer;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[Assert\Callback([self::class, 'validateDueOnOrAfterStart'])]
#[ORM\Table(name: 'tasks')]
#[ORM\Index(name: "fk_tasks_created_by", columns: ["created_by"])]
#[ORM\Index(name: "idx_tasks_assigned_to", columns: ["assigned_to"])]
#[ORM\Index(name: "idx_tasks_due_date", columns: ["due_date"])]
#[ORM\Index(name: "idx_tasks_priority", columns: ["priority"])]
#[ORM\Index(name: "idx_tasks_project", columns: ["project_id"])]
#[ORM\Index(name: "idx_tasks_status", columns: ["status"])]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Title is required.')]
    #[Assert\Length(max: 255, maxMessage: 'Title cannot exceed {{ limit }} characters.')]
    private ?string $title = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = PlainTextSanitizer::toLine($title);
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 65535, maxMessage: 'Description is too long.')]
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = PlainTextSanitizer::toBlock($description);
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\NotBlank(groups: ['task_quick_create'], message: 'Status is required.')]
    #[Assert\Choice(choices: ['todo', 'in_progress', 'done'], message: 'Choose a valid status.')]
    private ?string $status = null;

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\NotBlank(groups: ['task_quick_create'], message: 'Priority is required.')]
    #[Assert\Choice(choices: ['high', 'medium', 'low'], message: 'Choose a valid priority.')]
    private ?string $priority = null;

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(?string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: true)]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $start_date = null;

    public function getStart_date(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function setStart_date(?\DateTimeInterface $start_date): self
    {
        $this->start_date = $start_date;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: true)]
    #[Assert\NotNull(groups: ['task_quick_create'], message: 'Due date is required.')]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $due_date = null;

    public function getDue_date(): ?\DateTimeInterface
    {
        return $this->due_date;
    }

    public function setDue_date(?\DateTimeInterface $due_date): self
    {
        $this->due_date = $due_date;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero(message: 'Estimated time must be zero or positive.')]
    private ?int $estimated_time = null;

    public function getEstimated_time(): ?int
    {
        return $this->estimated_time;
    }

    public function setEstimated_time(?int $estimated_time): self
    {
        $this->estimated_time = $estimated_time;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero(message: 'Actual time must be zero or positive.')]
    private ?int $actual_time = null;

    public function getActual_time(): ?int
    {
        return $this->actual_time;
    }

    public function setActual_time(?int $actual_time): self
    {
        $this->actual_time = $actual_time;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Gedmo\Timestampable(on: 'create')]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(?\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $updated_at = null;

    public function getUpdated_at(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdated_at(?\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    #[Assert\Positive(message: 'Project id must be a positive number.')]
    private ?int $project_id = null;

    public function getProject_id(): ?int
    {
        return $this->project_id;
    }

    public function setProject_id(int $project_id): self
    {
        $this->project_id = $project_id;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive(message: 'Assignee id must be a positive number.')]
    private ?int $assigned_to = null;

    public function getAssigned_to(): ?int
    {
        return $this->assigned_to;
    }

    public function setAssigned_to(?int $assigned_to): self
    {
        $this->assigned_to = $assigned_to;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    #[Assert\Positive(message: 'Creator id must be a positive number.')]
    private ?int $created_by = null;

    public function getCreated_by(): ?int
    {
        return $this->created_by;
    }

    public function setCreated_by(int $created_by): self
    {
        $this->created_by = $created_by;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function setStartDate(?\DateTime $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->due_date;
    }

    public function setDueDate(?\DateTime $due_date): static
    {
        $this->due_date = $due_date;

        return $this;
    }

    public function getEstimatedTime(): ?int
    {
        return $this->estimated_time;
    }

    public function setEstimatedTime(?int $estimated_time): static
    {
        $this->estimated_time = $estimated_time;

        return $this;
    }

    public function getActualTime(): ?int
    {
        return $this->actual_time;
    }

    public function setActualTime(?int $actual_time): static
    {
        $this->actual_time = $actual_time;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTime $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTime $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getProjectId(): ?int
    {
        return $this->project_id;
    }

    public function setProjectId(int $project_id): static
    {
        $this->project_id = $project_id;

        return $this;
    }

    public function getAssignedTo(): ?int
    {
        return $this->assigned_to;
    }

    public function setAssignedTo(?int $assigned_to): static
    {
        $this->assigned_to = $assigned_to;

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->created_by;
    }

    public function setCreatedBy(int $created_by): static
    {
        $this->created_by = $created_by;

        return $this;
    }

    public static function validateDueOnOrAfterStart(self $task, ExecutionContextInterface $context): void
    {
        $start = $task->start_date;
        $due = $task->due_date;
        if ($start === null || $due === null) {
            return;
        }

        $startDay = \DateTimeImmutable::createFromInterface($start)->setTime(0, 0);
        $dueDay = \DateTimeImmutable::createFromInterface($due)->setTime(0, 0);
        if ($dueDay < $startDay) {
            $context->buildViolation('Due date must be on or after the start date.')
                ->atPath('due_date')
                ->addViolation();
        }
    }

}
