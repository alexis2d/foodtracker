<?php

namespace App\Entity;

use App\Repository\MealRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MealRepository::class)]
class Meal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $ownerUser;

    #[ORM\OneToOne(targetEntity: Food::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Food $food;

    /**
     * @var Collection<int, MealIngredient>
     */
    #[ORM\OneToMany(mappedBy: 'meal', targetEntity: MealIngredient::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $ingredients;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->ingredients = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getOwnerUser(): User
    {
        return $this->ownerUser;
    }

    public function setOwnerUser(User $ownerUser): static
    {
        $this->ownerUser = $ownerUser;

        return $this;
    }

    public function getFood(): Food
    {
        return $this->food;
    }

    public function setFood(Food $food): static
    {
        $this->food = $food;

        return $this;
    }

    /**
     * @return Collection<int, MealIngredient>
     */
    public function getIngredients(): Collection
    {
        return $this->ingredients;
    }

    public function addIngredient(MealIngredient $ingredient): static
    {
        if (!$this->ingredients->contains($ingredient)) {
            $this->ingredients->add($ingredient);
            $ingredient->setMeal($this);
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
