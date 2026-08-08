<?php

namespace App\Entity;

use App\Entity\Enum\ActivityLevel;
use App\Entity\Enum\Sex;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private int $dailyCalorieGoal = 2000;

    #[ORM\Column(nullable: true)]
    private ?int $heightCm = null;

    #[ORM\Column(nullable: true)]
    private ?float $weightKg = null;

    #[ORM\Column(nullable: true)]
    private ?int $age = null;

    #[ORM\Column(enumType: Sex::class, nullable: true)]
    private ?Sex $sex = null;

    #[ORM\Column(enumType: ActivityLevel::class, nullable: true)]
    private ?ActivityLevel $activityLevel = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $verificationTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verificationTokenExpiresAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $passwordResetTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordResetTokenExpiresAt = null;

    /**
     * New password hash held here until the user confirms the change via
     * the emailed link — see PasswordController::changePassword().
     */
    #[ORM\Column(nullable: true)]
    private ?string $pendingPasswordHash = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $passwordChangeTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordChangeTokenExpiresAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getDailyCalorieGoal(): int
    {
        return $this->dailyCalorieGoal;
    }

    public function setDailyCalorieGoal(int $dailyCalorieGoal): static
    {
        $this->dailyCalorieGoal = $dailyCalorieGoal;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getHeightCm(): ?int
    {
        return $this->heightCm;
    }

    public function setHeightCm(?int $heightCm): static
    {
        $this->heightCm = $heightCm;

        return $this;
    }

    public function getWeightKg(): ?float
    {
        return $this->weightKg;
    }

    public function setWeightKg(?float $weightKg): static
    {
        $this->weightKg = $weightKg;

        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function getSex(): ?Sex
    {
        return $this->sex;
    }

    public function setSex(?Sex $sex): static
    {
        $this->sex = $sex;

        return $this;
    }

    public function getActivityLevel(): ?ActivityLevel
    {
        return $this->activityLevel;
    }

    public function setActivityLevel(?ActivityLevel $activityLevel): static
    {
        $this->activityLevel = $activityLevel;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getVerificationTokenHash(): ?string
    {
        return $this->verificationTokenHash;
    }

    public function getVerificationTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->verificationTokenExpiresAt;
    }

    public function setVerificationToken(?string $hash, ?\DateTimeImmutable $expiresAt): static
    {
        $this->verificationTokenHash = $hash;
        $this->verificationTokenExpiresAt = $expiresAt;

        return $this;
    }

    public function getPasswordResetTokenHash(): ?string
    {
        return $this->passwordResetTokenHash;
    }

    public function getPasswordResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetTokenExpiresAt;
    }

    public function setPasswordResetToken(?string $hash, ?\DateTimeImmutable $expiresAt): static
    {
        $this->passwordResetTokenHash = $hash;
        $this->passwordResetTokenExpiresAt = $expiresAt;

        return $this;
    }

    public function getPendingPasswordHash(): ?string
    {
        return $this->pendingPasswordHash;
    }

    public function getPasswordChangeTokenHash(): ?string
    {
        return $this->passwordChangeTokenHash;
    }

    public function getPasswordChangeTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordChangeTokenExpiresAt;
    }

    public function setPendingPasswordChange(?string $pendingPasswordHash, ?string $tokenHash, ?\DateTimeImmutable $expiresAt): static
    {
        $this->pendingPasswordHash = $pendingPasswordHash;
        $this->passwordChangeTokenHash = $tokenHash;
        $this->passwordChangeTokenExpiresAt = $expiresAt;

        return $this;
    }
}
