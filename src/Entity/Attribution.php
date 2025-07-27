<?php

namespace App\Entity;

use App\Repository\AttributionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttributionRepository::class)]
class Attribution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateAttribution = null;

    #[ORM\ManyToOne(inversedBy: 'attributions')]
    private ?Equipement $equipement = null;

    #[ORM\ManyToOne(inversedBy: 'attributions')]
    private ?User $utilisateur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateAttribution(): ?\DateTimeImmutable
    {
        return $this->dateAttribution;
    }

    public function setDateAttribution(\DateTimeImmutable $dateAttribution): static
    {
        $this->dateAttribution = $dateAttribution;

        return $this;
    }

    public function getEquipement(): ?Equipement
    {
        return $this->equipement;
    }

    public function setEquipement(?Equipement $equipement): static
    {
        $this->equipement = $equipement;

        return $this;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function __toString(): string
    {
        return $this->equipement . ' → ' . $this->utilisateur . ' (' . $this->dateAttribution->format('d/m/Y') . ')';
    }
}
