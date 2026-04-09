<?php

namespace App\Entity\ResourcesManagement;

use App\Repository\ResourcesManagement\ResourceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ResourceRepository::class)]
#[ORM\Table(name: 'resources')]
#[UniqueEntity(fields: ['resource_code'], message: 'Ce code de ressource existe déjà.')]
class Resource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $resource_id = null;

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Le code de la ressource est obligatoire.")]
    #[Assert\Regex(
        pattern: "/^[a-zA-Z0-9-]+$/",
        message: "Le code ne doit contenir que des lettres, chiffres et tirets."
    )]
    private ?string $resource_code = null;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Le nom de la ressource est obligatoire.")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "Le nom doit comporter au moins {{ limit }} caractères.",
        maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $resource_name = null;

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Veuillez choisir un type de ressource.")]
    private ?string $resource_type = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    #[Assert\NotBlank(message: "Le coût unitaire est obligatoire.")]
    #[Assert\PositiveOrZero(message: "Le coût ne peut pas être négatif.")]
    private ?string $unit_cost = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    #[Assert\NotNull(message: "La quantité totale est obligatoire.")]
    #[Assert\GreaterThanOrEqual(value: 0, message: "La quantité ne peut pas être inférieure à 0.")]
    private ?int $total_quantity = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $available_quantity = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $image_path = null;

    // --- GETTERS & SETTERS ---

    public function getResourceId(): ?int
    {
        return $this->resource_id;
    }

    public function getResourceCode(): ?string
    {
        return $this->resource_code;
    }

    public function setResourceCode(string $resource_code): self
    {
        $this->resource_code = $resource_code;
        return $this;
    }

    public function getResourceName(): ?string
    {
        return $this->resource_name;
    }

    public function setResourceName(string $resource_name): self
    {
        $this->resource_name = $resource_name;
        return $this;
    }

    public function getResourceType(): ?string
    {
        return $this->resource_type;
    }

    public function setResourceType(string $resource_type): self
    {
        $this->resource_type = $resource_type;
        return $this;
    }

    public function getUnitCost(): ?string
    {
        return $this->unit_cost;
    }

    public function setUnitCost(string $unit_cost): self
    {
        $this->unit_cost = $unit_cost;
        return $this;
    }

    public function getTotalQuantity(): ?int
    {
        return $this->total_quantity;
    }

    public function setTotalQuantity(int $total_quantity): self
    {
        $this->total_quantity = $total_quantity;
        return $this;
    }

    public function getAvailableQuantity(): ?int
    {
        return $this->available_quantity;
    }

    public function setAvailableQuantity(int $available_quantity): self
    {
        $this->available_quantity = $available_quantity;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->image_path;
    }

    public function setImagePath(?string $image_path): self
    {
        $this->image_path = $image_path;
        return $this;
    }
}