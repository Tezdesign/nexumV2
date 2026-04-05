<?php

namespace App\Entity\Projects;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\Projects\ProjectAssignmentRepository;

#[ORM\Entity(repositoryClass: ProjectAssignmentRepository::class)]
#[ORM\Table(name: 'project_assignments')]
class ProjectAssignment
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
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

    #[ORM\Id]
    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $user_id = null;

    public function getUser_id(): ?int
    {
        return $this->user_id;
    }

    public function setUser_id(int $user_id): self
    {
        $this->user_id = $user_id;
        return $this;
    }

    public function getProjectId(): ?int
    {
        return $this->project_id;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): static
    {
        $this->user_id = $user_id;

        return $this;
    }

}
