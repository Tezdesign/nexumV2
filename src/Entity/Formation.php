<?php

namespace App\Entity;

use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "Le titre doit contenir au moins {{ limit }} caractères.",
        maxMessage: "Le titre ne doit pas dépasser {{ limit }} caractères."
    )]
    private ?string $titre = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    #[Assert\Length(
        min: 10,
        minMessage: "La description doit contenir au moins {{ limit }} caractères."
    )]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Regex(
        pattern: "/\.(mp4|webm|ogg|mov|avi|wmv)$/i",
        message: "La vidéo 1 doit être un fichier vidéo valide."
    )]
    private ?string $video1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Regex(
        pattern: "/\.(mp4|webm|ogg|mov|avi|wmv)$/i",
        message: "La vidéo 2 doit être un fichier vidéo valide."
    )]
    private ?string $video2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Regex(
        pattern: "/\.(mp4|webm|ogg|mov|avi|wmv)$/i",
        message: "La vidéo 3 doit être un fichier vidéo valide."
    )]
    private ?string $video3 = null;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: Participer::class, orphanRemoval: true)]
    private Collection $participations;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: Quiz::class, orphanRemoval: true)]
    private Collection $quizzes;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: Resultat::class, orphanRemoval: true)]
    private Collection $resultats;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
        $this->quizzes = new ArrayCollection();
        $this->resultats = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = trim($titre);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = trim($description);
        return $this;
    }

    public function getVideo1(): ?string
    {
        return $this->video1;
    }

    public function setVideo1(?string $video1): self
    {
        $this->video1 = $video1;
        return $this;
    }

    public function getVideo2(): ?string
    {
        return $this->video2;
    }

    public function setVideo2(?string $video2): self
    {
        $this->video2 = $video2;
        return $this;
    }

    public function getVideo3(): ?string
    {
        return $this->video3;
    }

    public function setVideo3(?string $video3): self
    {
        $this->video3 = $video3;
        return $this;
    }
 

    // ===== RELATIONS =====

    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participer $participation): self
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setFormation($this);
        }
        return $this;
    }

    public function removeParticipation(Participer $participation): self
    {
        if ($this->participations->removeElement($participation)) {
            if ($participation->getFormation() === $this) {
                $participation->setFormation(null);
            }
        }
        return $this;
    }

    public function getQuizzes(): Collection
    {
        return $this->quizzes;
    }

    public function addQuiz(Quiz $quiz): self
    {
        if (!$this->quizzes->contains($quiz)) {
            $this->quizzes->add($quiz);
            $quiz->setFormation($this);
        }
        return $this;
    }

    public function removeQuiz(Quiz $quiz): self
    {
        if ($this->quizzes->removeElement($quiz)) {
            if ($quiz->getFormation() === $this) {
                $quiz->setFormation(null);
            }
        }
        return $this;
    }

    public function getResultats(): Collection
    {
        return $this->resultats;
    }

    public function addResultat(Resultat $resultat): self
    {
        if (!$this->resultats->contains($resultat)) {
            $this->resultats->add($resultat);
            $resultat->setFormation($this);
        }
        return $this;
    }

    public function removeResultat(Resultat $resultat): self
    {
        if ($this->resultats->removeElement($resultat)) {
            if ($resultat->getFormation() === $this) {
                $resultat->setFormation(null);
            }
        }
        return $this;
    }
}