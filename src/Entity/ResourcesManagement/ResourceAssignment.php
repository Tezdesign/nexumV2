<?php

namespace App\Entity\ResourcesManagement;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\UserHandling\Utilisateur;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ResourcesManagement\ResourceAssignmentRepository;

#[ORM\Entity(repositoryClass: ResourceAssignmentRepository::class)]
#[ORM\Table(name: 'resource_assignment')]
class ResourceAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $assignment_id = null;

    public function getAssignment_id(): ?int
    {
        return $this->assignment_id;
    }

    public function setAssignment_id(int $assignment_id): self
    {
        $this->assignment_id = $assignment_id;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $resource_id = null;

    public function getResource_id(): ?int
    {
        return $this->resource_id;
    }

    public function setResource_id(int $resource_id): self
    {
        $this->resource_id = $resource_id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $project_code = null;

    public function getProject_code(): ?string
    {
        return $this->project_code;
    }

    public function setProject_code(string $project_code): self
    {
        $this->project_code = $project_code;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $client_code = null;

    public function getClient_code(): ?string
    {
        return $this->client_code;
    }

    public function setClient_code(?string $client_code): self
    {
        $this->client_code = $client_code;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $quantity = null;

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $assignment_date = null;

    public function getAssignment_date(): ?\DateTimeInterface
    {
        return $this->assignment_date;
    }

    public function setAssignment_date(\DateTimeInterface $assignment_date): self
    {
        $this->assignment_date = $assignment_date;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $return_date = null;

    public function getReturn_date(): ?\DateTimeInterface
    {
        return $this->return_date;
    }

    public function setReturn_date(?\DateTimeInterface $return_date): self
    {
        $this->return_date = $return_date;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $total_cost = null;

    public function getTotal_cost(): ?float
    {
        return $this->total_cost;
    }

    public function setTotal_cost(?float $total_cost): self
    {
        $this->total_cost = $total_cost;
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

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $penalty_days_applied = null;

    public function getPenalty_days_applied(): ?int
    {
        return $this->penalty_days_applied;
    }

    public function setPenalty_days_applied(int $penalty_days_applied): self
    {
        $this->penalty_days_applied = $penalty_days_applied;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $bonus_applied = null;

    public function isBonus_applied(): ?bool
    {
        return $this->bonus_applied;
    }

    public function setBonus_applied(bool $bonus_applied): self
    {
        $this->bonus_applied = $bonus_applied;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'resourceAssignments')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private ?Utilisateur $utilisateur = null;

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getAssignmentId(): ?int
    {
        return $this->assignment_id;
    }

    public function getResourceId(): ?int
    {
        return $this->resource_id;
    }

    public function setResourceId(int $resource_id): static
    {
        $this->resource_id = $resource_id;

        return $this;
    }

    public function getProjectCode(): ?string
    {
        return $this->project_code;
    }

    public function setProjectCode(string $project_code): static
    {
        $this->project_code = $project_code;

        return $this;
    }

    public function getClientCode(): ?string
    {
        return $this->client_code;
    }

    public function setClientCode(?string $client_code): static
    {
        $this->client_code = $client_code;

        return $this;
    }

    public function getAssignmentDate(): ?\DateTime
    {
        return $this->assignment_date;
    }

    public function setAssignmentDate(\DateTime $assignment_date): static
    {
        $this->assignment_date = $assignment_date;

        return $this;
    }

    public function getReturnDate(): ?\DateTime
    {
        return $this->return_date;
    }

    public function setReturnDate(?\DateTime $return_date): static
    {
        $this->return_date = $return_date;

        return $this;
    }

    public function getTotalCost(): ?string
    {
        return $this->total_cost;
    }

    public function setTotalCost(?string $total_cost): static
    {
        $this->total_cost = $total_cost;

        return $this;
    }

    public function getPenaltyDaysApplied(): ?int
    {
        return $this->penalty_days_applied;
    }

    public function setPenaltyDaysApplied(int $penalty_days_applied): static
    {
        $this->penalty_days_applied = $penalty_days_applied;

        return $this;
    }

    public function isBonusApplied(): ?bool
    {
        return $this->bonus_applied;
    }

    public function setBonusApplied(bool $bonus_applied): static
    {
        $this->bonus_applied = $bonus_applied;

        return $this;
    }

}
