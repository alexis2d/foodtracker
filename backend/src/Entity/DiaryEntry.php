<?php

namespace App\Entity;

use App\Entity\Enum\FoodUnit;
use App\Entity\Enum\MealType;
use App\Repository\DiaryEntryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DiaryEntryRepository::class)]
class DiaryEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Food::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Food $food;

    #[ORM\Column]
    private float $quantity;

    #[ORM\Column(length: 10, enumType: FoodUnit::class)]
    private FoodUnit $unit;

    #[ORM\Column(length: 20, enumType: MealType::class)]
    private MealType $mealType;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $consumedAt;

    // Snapshotted at write time so editing a Food's macros later never
    // retroactively changes a previously logged day's totals.
    #[ORM\Column]
    private float $kcalAtLogging;

    #[ORM\Column]
    private float $proteinAtLogging;

    #[ORM\Column]
    private float $carbsAtLogging;

    #[ORM\Column]
    private float $fatAtLogging;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

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

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnit(): FoodUnit
    {
        return $this->unit;
    }

    public function setUnit(FoodUnit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getMealType(): MealType
    {
        return $this->mealType;
    }

    public function setMealType(MealType $mealType): static
    {
        $this->mealType = $mealType;

        return $this;
    }

    public function getConsumedAt(): \DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function setConsumedAt(\DateTimeImmutable $consumedAt): static
    {
        $this->consumedAt = $consumedAt;

        return $this;
    }

    public function getKcalAtLogging(): float
    {
        return $this->kcalAtLogging;
    }

    public function getProteinAtLogging(): float
    {
        return $this->proteinAtLogging;
    }

    public function getCarbsAtLogging(): float
    {
        return $this->carbsAtLogging;
    }

    public function getFatAtLogging(): float
    {
        return $this->fatAtLogging;
    }

    public function setSnapshot(float $kcal, float $protein, float $carbs, float $fat): static
    {
        $this->kcalAtLogging = $kcal;
        $this->proteinAtLogging = $protein;
        $this->carbsAtLogging = $carbs;
        $this->fatAtLogging = $fat;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
