<?php

namespace App\Entity\Tasks;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\Tasks\TaskRepository;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
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
    private ?string $title = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
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

    public function getStartDate(): ?\DateTime
    {
        return $this->start_date;
    }

    public function setStartDate(?\DateTime $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getDueDate(): ?\DateTime
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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTime $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
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

}
