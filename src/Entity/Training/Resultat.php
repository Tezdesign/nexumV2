<?php

namespace App\Entity\Training;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\Training\ResultatRepository;

#[ORM\Entity(repositoryClass: ResultatRepository::class)]
#[ORM\Table(name: 'resultat')]
class Resultat
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

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $formation_id = null;

    public function getFormation_id(): ?int
    {
        return $this->formation_id;
    }

    public function setFormation_id(int $formation_id): self
    {
        $this->formation_id = $formation_id;
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

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $total = null;

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date_passage = null;

    public function getDate_passage(): ?\DateTimeInterface
    {
        return $this->date_passage;
    }

    public function setDate_passage(?\DateTimeInterface $date_passage): self
    {
        $this->date_passage = $date_passage;
        return $this;
    }

    public function getFormationId(): ?int
    {
        return $this->formation_id;
    }

    public function setFormationId(int $formation_id): static
    {
        $this->formation_id = $formation_id;

        return $this;
    }

    public function getDatePassage(): ?\DateTime
    {
        return $this->date_passage;
    }

    public function setDatePassage(?\DateTime $date_passage): static
    {
        $this->date_passage = $date_passage;

        return $this;
    }

}
