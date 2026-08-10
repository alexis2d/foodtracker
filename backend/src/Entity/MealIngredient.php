<?php

namespace App\Entity;

use App\Entity\Enum\FoodUnit;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MealIngredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Meal::class, inversedBy: 'ingredients')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Meal $meal;

    #[ORM\ManyToOne(targetEntity: Food::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Food $food;

    #[ORM\Column]
    private float $quantity;

    #[ORM\Column(length: 10, enumType: FoodUnit::class)]
    private FoodUnit $unit;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMeal(): Meal
    {
        return $this->meal;
    }

    public function setMeal(Meal $meal): static
    {
        $this->meal = $meal;

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
}
