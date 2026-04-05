<?php

namespace App\Entity\ResourcesManagement;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ResourcesManagement\ResourceRepository;

#[ORM\Entity(repositoryClass: ResourceRepository::class)]
#[ORM\Table(name: 'resources')]
class Resource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
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
    private ?string $resource_code = null;

    public function getResource_code(): ?string
    {
        return $this->resource_code;
    }

    public function setResource_code(string $resource_code): self
    {
        $this->resource_code = $resource_code;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $resource_name = null;

    public function getResource_name(): ?string
    {
        return $this->resource_name;
    }

    public function setResource_name(string $resource_name): self
    {
        $this->resource_name = $resource_name;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $resource_type = null;

    public function getResource_type(): ?string
    {
        return $this->resource_type;
    }

    public function setResource_type(string $resource_type): self
    {
        $this->resource_type = $resource_type;
        return $this;
    }

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    private ?string $unit_cost = null;

    public function getUnit_cost(): ?string
    {
        return $this->unit_cost;
    }

    public function setUnit_cost(string $unit_cost): self
    {
        $this->unit_cost = $unit_cost;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $total_quantity = null;

    public function getTotal_quantity(): ?int
    {
        return $this->total_quantity;
    }

    public function setTotal_quantity(int $total_quantity): self
    {
        $this->total_quantity = $total_quantity;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $available_quantity = null;

    public function getAvailable_quantity(): ?int
    {
        return $this->available_quantity;
    }

    public function setAvailable_quantity(int $available_quantity): self
    {
        $this->available_quantity = $available_quantity;
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
    private ?string $image_path = null;

    public function getImage_path(): ?string
    {
        return $this->image_path;
    }

    public function setImage_path(?string $image_path): self
    {
        $this->image_path = $image_path;
        return $this;
    }

    public function getResourceId(): ?int
    {
        return $this->resource_id;
    }

    public function getResourceCode(): ?string
    {
        return $this->resource_code;
    }

    public function setResourceCode(string $resource_code): static
    {
        $this->resource_code = $resource_code;

        return $this;
    }

    public function getResourceName(): ?string
    {
        return $this->resource_name;
    }

    public function setResourceName(string $resource_name): static
    {
        $this->resource_name = $resource_name;

        return $this;
    }

    public function getResourceType(): ?string
    {
        return $this->resource_type;
    }

    public function setResourceType(string $resource_type): static
    {
        $this->resource_type = $resource_type;

        return $this;
    }

    public function getUnitCost(): ?string
    {
        return $this->unit_cost;
    }

    public function setUnitCost(string $unit_cost): static
    {
        $this->unit_cost = $unit_cost;

        return $this;
    }

    public function getTotalQuantity(): ?int
    {
        return $this->total_quantity;
    }

    public function setTotalQuantity(int $total_quantity): static
    {
        $this->total_quantity = $total_quantity;

        return $this;
    }

    public function getAvailableQuantity(): ?int
    {
        return $this->available_quantity;
    }

    public function setAvailableQuantity(int $available_quantity): static
    {
        $this->available_quantity = $available_quantity;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->image_path;
    }

    public function setImagePath(?string $image_path): static
    {
        $this->image_path = $image_path;

        return $this;
    }

}
