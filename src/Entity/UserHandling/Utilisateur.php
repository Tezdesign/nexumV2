<?php

namespace App\Entity\UserHandling;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\ResourcesManagement\ResourceAssignment;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\UserHandling\UtilisateurRepository;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateurs')]
#[ORM\UniqueConstraint(name: "email", columns: ["email"])]
class Utilisateur
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
    private ?string $nom = null;

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $prenom = null;

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $email = null;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $telephone = null;

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $role = null;

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $departement = null;

    public function getDepartement(): ?string
    {
        return $this->departement;
    }

    public function setDepartement(?string $departement): self
    {
        $this->departement = $departement;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $statut = null;

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $date_inscription = null;

    public function getDate_inscription(): ?\DateTimeInterface
    {
        return $this->date_inscription;
    }

    public function setDate_inscription(\DateTimeInterface $date_inscription): self
    {
        $this->date_inscription = $date_inscription;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $password = null;

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    #[ORM\Column(type: 'blob', nullable: true)]
    private ?string $imagelink = null;

    public function getImagelink(): ?string
    {
        return $this->imagelink;
    }

    public function setImagelink(?string $imagelink): self
    {
        $this->imagelink = $imagelink;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $face_id = null;

    public function getFace_id(): ?string
    {
        return $this->face_id;
    }

    public function setFace_id(?string $face_id): self
    {
        $this->face_id = $face_id;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $score = null;

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: ResourceAssignment::class, mappedBy: 'utilisateur')]
    private Collection $resourceAssignments;

    public function __construct()
    {
        $this->resourceAssignments = new ArrayCollection();
    }

    /**
     * @return Collection<int, ResourceAssignment>
     */
    public function getResourceAssignments(): Collection
    {
        if (!$this->resourceAssignments instanceof Collection) {
            $this->resourceAssignments = new ArrayCollection();
        }
        return $this->resourceAssignments;
    }

    public function addResourceAssignment(ResourceAssignment $resourceAssignment): self
    {
        if (!$this->getResourceAssignments()->contains($resourceAssignment)) {
            $this->getResourceAssignments()->add($resourceAssignment);
        }
        return $this;
    }

    public function removeResourceAssignment(ResourceAssignment $resourceAssignment): self
    {
        $this->getResourceAssignments()->removeElement($resourceAssignment);
        return $this;
    }

    public function getDateInscription(): ?\DateTime
    {
        return $this->date_inscription;
    }

    public function setDateInscription(\DateTime $date_inscription): static
    {
        $this->date_inscription = $date_inscription;

        return $this;
    }

    public function getFaceId(): ?string
    {
        return $this->face_id;
    }

    public function setFaceId(?string $face_id): static
    {
        $this->face_id = $face_id;

        return $this;
    }

}
