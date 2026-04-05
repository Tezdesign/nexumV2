<?php

namespace App\Entity\Projects;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\Projects\ProjectRepository;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'projects')]
class Project
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
    private ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
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
    private ?\DateTimeInterface $end_date = null;

    public function getEnd_date(): ?\DateTimeInterface
    {
        return $this->end_date;
    }

    public function setEnd_date(?\DateTimeInterface $end_date): self
    {
        $this->end_date = $end_date;
        return $this;
    }

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $budget = null;

    public function getBudget(): ?string
    {
        return $this->budget;
    }

    public function setBudget(?string $budget): self
    {
        $this->budget = $budget;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $progress = null;

    public function getProgress(): ?int
    {
        return $this->progress;
    }

    public function setProgress(?int $progress): self
    {
        $this->progress = $progress;
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

    public function getStartDate(): ?\DateTime
    {
        return $this->start_date;
    }

    public function setStartDate(?\DateTime $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->end_date;
    }

    public function setEndDate(?\DateTime $end_date): static
    {
        $this->end_date = $end_date;

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

    public function getCreatedBy(): ?int
    {
        return $this->created_by;
    }

    public function setCreatedBy(int $created_by): static
    {
        $this->created_by = $created_by;

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

}
