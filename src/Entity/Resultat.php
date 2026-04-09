<?php

namespace App\Entity;

use App\Repository\ResultatRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\UserHandling\Utilisateur as User;

#[ORM\Entity(repositoryClass: ResultatRepository::class)]
class Resultat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'resultats')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formation $formation = null;

    #[ORM\Column]
    private ?int $score = null;

    #[ORM\Column]
    private ?int $total = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $datePassage = null;
#[ORM\ManyToOne]
#[ORM\JoinColumn(nullable: false)]
private ?User $user = null;

public function getUser(): ?User
{
    return $this->user;
}

public function setUser(?User $user): self
{
    $this->user = $user;
    return $this;
}
    public function __construct()
    {
        $this->datePassage = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;
        return $this;
    }

    public function getDatePassage(): ?\DateTimeInterface
    {
        return $this->datePassage;
    }

    public function setDatePassage(\DateTimeInterface $datePassage): self
    {
        $this->datePassage = $datePassage;
        return $this;
    }
}