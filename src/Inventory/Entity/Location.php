<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use App\Inventory\Enum\LocationKind;
use App\Inventory\Repository\LocationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: LocationRepository::class)]
#[ORM\Table(name: 'inventory_location')]
class Location
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name;

    #[ORM\Column(length: 16, enumType: LocationKind::class)]
    private LocationKind $kind;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?self $parent;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $children;

    public function __construct(string $name, LocationKind $kind, ?self $parent = null)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->kind = $kind;
        $this->parent = $parent;
        $this->children = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getKind(): LocationKind
    {
        return $this->kind;
    }

    public function setKind(LocationKind $kind): void
    {
        $this->kind = $kind;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * Chemin complet, du site jusqu'à l'emplacement : « Campus › Bâtiment B › Salle 104 ».
     */
    public function getPath(): string
    {
        return null === $this->parent ? $this->name : $this->parent->getPath().' › '.$this->name;
    }

    #[Assert\Callback]
    public function validateHierarchy(ExecutionContextInterface $context): void
    {
        $expected = $this->kind->parentKind();

        if (null === $expected && null !== $this->parent) {
            $context->buildViolation('Un site ne peut pas être rattaché à un autre emplacement.')
                ->atPath('parent')->addViolation();
        }

        if (null !== $expected && $this->parent?->getKind() !== $expected) {
            $context->buildViolation(\sprintf('Un emplacement de type « %s » doit être rattaché à un emplacement de type « %s ».', $this->kind->label(), $expected->label()))
                ->atPath('parent')->addViolation();
        }
    }
}
