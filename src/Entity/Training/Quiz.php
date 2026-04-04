<?php

namespace App\Entity\Training;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\Training\QuizRepository;

#[ORM\Entity(repositoryClass: QuizRepository::class)]
#[ORM\Table(name: 'quiz')]
class Quiz
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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $question = null;

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(?string $question): self
    {
        $this->question = $question;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $r1 = null;

    public function getR1(): ?string
    {
        return $this->r1;
    }

    public function setR1(?string $r1): self
    {
        $this->r1 = $r1;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $r2 = null;

    public function getR2(): ?string
    {
        return $this->r2;
    }

    public function setR2(?string $r2): self
    {
        $this->r2 = $r2;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $r3 = null;

    public function getR3(): ?string
    {
        return $this->r3;
    }

    public function setR3(?string $r3): self
    {
        $this->r3 = $r3;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $image = null;

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $formation_id = null;

    public function getFormation_id(): ?int
    {
        return $this->formation_id;
    }

    public function setFormation_id(?int $formation_id): self
    {
        $this->formation_id = $formation_id;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $correct = null;

    public function getCorrect(): ?int
    {
        return $this->correct;
    }

    public function setCorrect(int $correct): self
    {
        $this->correct = $correct;
        return $this;
    }

    public function getFormationId(): ?int
    {
        return $this->formation_id;
    }

    public function setFormationId(?int $formation_id): static
    {
        $this->formation_id = $formation_id;

        return $this;
    }

}
