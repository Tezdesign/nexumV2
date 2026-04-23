<?php

namespace App\Entity\Projects;

use App\Repository\Projects\ProjectFileRepository;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectFileRepository::class)]
#[ORM\Table(name: 'project_file')]
#[ORM\Index(name: 'idx_project_file_project', columns: ['project_id'])]
#[ORM\Index(name: 'idx_project_file_uploaded_by', columns: ['uploaded_by'])]
class ProjectFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $project_id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $uploaded_by = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $original_name = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $public_id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $resource_type = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $format = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $bytes = null;

    #[ORM\Column(type: 'string', length: 1000)]
    private ?string $secure_url = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $created_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject_id(): ?int
    {
        return $this->project_id;
    }

    public function setProject_id(int $project_id): self
    {
        $this->project_id = $project_id;

        return $this;
    }

    public function getUploaded_by(): ?int
    {
        return $this->uploaded_by;
    }

    public function setUploaded_by(int $uploaded_by): self
    {
        $this->uploaded_by = $uploaded_by;

        return $this;
    }

    public function getOriginal_name(): ?string
    {
        return $this->original_name;
    }

    public function setOriginal_name(string $original_name): self
    {
        $this->original_name = $original_name;

        return $this;
    }

    public function getPublic_id(): ?string
    {
        return $this->public_id;
    }

    public function setPublic_id(string $public_id): self
    {
        $this->public_id = $public_id;

        return $this;
    }

    public function getResource_type(): ?string
    {
        return $this->resource_type;
    }

    public function setResource_type(string $resource_type): self
    {
        $this->resource_type = $resource_type;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getBytes(): ?int
    {
        return $this->bytes;
    }

    public function setBytes(?int $bytes): self
    {
        $this->bytes = $bytes;

        return $this;
    }

    public function getSecure_url(): ?string
    {
        return $this->secure_url;
    }

    public function setSecure_url(string $secure_url): self
    {
        $this->secure_url = $secure_url;

        return $this;
    }

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(?\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }
}
