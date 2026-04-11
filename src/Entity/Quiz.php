<?php

namespace App\Entity;

use App\Repository\QuizRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuizRepository::class)]
class Quiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: "La question est obligatoire")]
    #[Assert\Length(
        min: 5,
        minMessage: "La question doit contenir au moins {{ limit }} caractères"
    )]
    private ?string $question = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Réponse 1 obligatoire")]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $r1 = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Réponse 2 obligatoire")]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $r2 = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Réponse 3 obligatoire")]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $r3 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\File(
        maxSize: "2M",
        mimeTypes: ["image/jpeg", "image/png", "image/webp"],
        mimeTypesMessage: "Veuillez uploader une image valide (jpg, png, webp)"
    )]
    private ?string $image = null;

    #[ORM\ManyToOne(inversedBy: 'quizzes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Formation obligatoire")]
    private ?Formation $formation = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "Veuillez choisir la bonne réponse")]
    #[Assert\Range(
        min: 1,
        max: 3,
        notInRangeMessage: "La réponse correcte doit être entre 1 et 3"
    )]
    private ?int $correct = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(string $question): self
    {
        $this->question = $question;
        return $this;
    }

    public function getR1(): ?string
    {
        return $this->r1;
    }

    public function setR1(string $r1): self
    {
        $this->r1 = $r1;
        return $this;
    }

    public function getR2(): ?string
    {
        return $this->r2;
    }

    public function setR2(string $r2): self
    {
        $this->r2 = $r2;
        return $this;
    }

    public function getR3(): ?string
    {
        return $this->r3;
    }

    public function setR3(string $r3): self
    {
        $this->r3 = $r3;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;
        return $this;
    }

    public function getCorrect(): ?int
    {
        return $this->correct;
    }

    public function setCorrect(int $correct): self
    {
        $this->correct = $correct;
        return $this;
    }
}