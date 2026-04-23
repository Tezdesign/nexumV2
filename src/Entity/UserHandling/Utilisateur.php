<?php

namespace App\Entity\UserHandling;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\ResourcesManagement\ResourceAssignment;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use App\Repository\UserHandling\UtilisateurRepository;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateurs')]
#[ORM\UniqueConstraint(name: "email", columns: ["email"])]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
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

    public function getUserIdentifier(): string
    {
        return (string) ($this->email ?? '');
    }

    /**
     * Kept for backward compatibility with older Symfony internals.
     */
    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getRoles(): array
    {
        $raw = strtolower(trim((string) ($this->role ?? '')));

        $mapped = match ($raw) {
            'admin', 'administrator' => ['ROLE_ADMIN'],
            'manager' => ['ROLE_MANAGER'],
            'employee' => ['ROLE_EMPLOYEE'],
            'finance' => ['ROLE_FINANCE'],
            'hr' => ['ROLE_HR'],
            default => ['ROLE_USER'],
        };

        $mapped[] = 'ROLE_USER';

        return array_values(array_unique($mapped));
    }

    public function eraseCredentials(): void
    {
        // No temporary sensitive data stored on the entity.
    }

    #[ORM\Column(type: 'integer', options: ['default' => 100], nullable: false)]
    private int $score = 100;

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;
        return $this;
    }

    /**
     * Stored as BLOB in the legacy DB. Doctrine hydrates BLOBs as stream resources,
     * so this property can't be typed as string.
     */
    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private $imagelink = null;


    public function getImagelink()
    {
        if ($this->imagelink === null) {
            return null;
        }

        if (is_resource($this->imagelink)) {
            $data = stream_get_contents($this->imagelink);
            $this->imagelink = $data === false ? null : $data;
        }

        return is_string($this->imagelink) ? $this->imagelink : null;
    }

    public function setImagelink($imagelink): self
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
