<?php

namespace App\Entity;

use App\Entity\Enum\FoodSource;
use App\Entity\Enum\FoodUnit;
use App\Repository\FoodRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FoodRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_source_off_id', fields: ['source', 'offId'])]
class Food
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 20, enumType: FoodSource::class)]
    private FoodSource $source;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $offId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $ownerUser = null;

    #[ORM\Column]
    private float $kcalPer100;

    #[ORM\Column]
    private float $proteinPer100;

    #[ORM\Column]
    private float $carbsPer100;

    #[ORM\Column]
    private float $fatPer100;

    #[ORM\Column(nullable: true)]
    private ?float $fiberPer100 = null;

    #[ORM\Column(length: 10, enumType: FoodUnit::class)]
    private FoodUnit $defaultUnit;

    #[ORM\Column(nullable: true)]
    private ?float $unitWeightGrams = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
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

    public function getSource(): FoodSource
    {
        return $this->source;
    }

    public function setSource(FoodSource $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): static
    {
        $this->barcode = $barcode;

        return $this;
    }

    public function getOffId(): ?string
    {
        return $this->offId;
    }

    public function setOffId(?string $offId): static
    {
        $this->offId = $offId;

        return $this;
    }

    public function getOwnerUser(): ?User
    {
        return $this->ownerUser;
    }

    public function setOwnerUser(?User $ownerUser): static
    {
        $this->ownerUser = $ownerUser;

        return $this;
    }

    public function getKcalPer100(): float
    {
        return $this->kcalPer100;
    }

    public function setKcalPer100(float $kcalPer100): static
    {
        $this->kcalPer100 = $kcalPer100;

        return $this;
    }

    public function getProteinPer100(): float
    {
        return $this->proteinPer100;
    }

    public function setProteinPer100(float $proteinPer100): static
    {
        $this->proteinPer100 = $proteinPer100;

        return $this;
    }

    public function getCarbsPer100(): float
    {
        return $this->carbsPer100;
    }

    public function setCarbsPer100(float $carbsPer100): static
    {
        $this->carbsPer100 = $carbsPer100;

        return $this;
    }

    public function getFatPer100(): float
    {
        return $this->fatPer100;
    }

    public function setFatPer100(float $fatPer100): static
    {
        $this->fatPer100 = $fatPer100;

        return $this;
    }

    public function getFiberPer100(): ?float
    {
        return $this->fiberPer100;
    }

    public function setFiberPer100(?float $fiberPer100): static
    {
        $this->fiberPer100 = $fiberPer100;

        return $this;
    }

    public function getDefaultUnit(): FoodUnit
    {
        return $this->defaultUnit;
    }

    public function setDefaultUnit(FoodUnit $defaultUnit): static
    {
        $this->defaultUnit = $defaultUnit;

        return $this;
    }

    public function getUnitWeightGrams(): ?float
    {
        return $this->unitWeightGrams;
    }

    public function setUnitWeightGrams(?float $unitWeightGrams): static
    {
        $this->unitWeightGrams = $unitWeightGrams;

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

    /**
     * Grams represented by one unit of `$unit` for this food, used to convert
     * a logged quantity into grams for macro calculation.
     */
    public function gramsFor(float $quantity, FoodUnit $unit): float
    {
        if (FoodUnit::Unit === $unit) {
            return $quantity * ($this->unitWeightGrams ?? 100.0);
        }

        return $quantity;
    }
}
