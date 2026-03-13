<?php

namespace App\Entity;

use App\Repository\PlayerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
class Player
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Game::class, inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'smallint')]
    private int $playerIndex;

    #[ORM\Column(type: 'json')]
    private array $hand = [];

    #[ORM\Column(type: 'json')]
    private array $capturedCards = [];

    #[ORM\Column(type: 'smallint')]
    private int $scopeCount = 0;

    #[ORM\Column(type: 'smallint')]
    private int $score = 0;

    public function __construct(Game $game, User $user, int $playerIndex)
    {
        $this->game = $game;
        $this->user = $user;
        $this->playerIndex = $playerIndex;
        $game->addPlayer($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPlayerIndex(): int
    {
        return $this->playerIndex;
    }

    public function getHand(): array
    {
        return $this->hand;
    }

    public function setHand(array $hand): static
    {
        $this->hand = $hand;
        return $this;
    }

    public function removeFromHand(string $cardRef): static
    {
        $this->hand = array_values(array_filter(
            $this->hand,
            fn(string $ref) => $ref !== $cardRef
        ));
        return $this;
    }

    public function hasInHand(string $cardRef): bool
    {
        return in_array($cardRef, $this->hand, true);
    }

    public function getCapturedCards(): array
    {
        return $this->capturedCards;
    }

    public function setCapturedCards(array $capturedCards): static
    {
        $this->capturedCards = $capturedCards;
        return $this;
    }

    public function addCapturedCards(array $cardRefs): static
    {
        $this->capturedCards = array_merge($this->capturedCards, $cardRefs);
        return $this;
    }

    public function getScopeCount(): int
    {
        return $this->scopeCount;
    }

    public function incrementScopeCount(): static
    {
        $this->scopeCount++;
        return $this;
    }

    public function setScopeCount(int $scopeCount): static
    {
        $this->scopeCount = $scopeCount;
        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;
        return $this;
    }

    public function addScore(int $points): static
    {
        $this->score += $points;
        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->user->getFirstName();
    }

    public function resetForNewRound(): void
    {
        $this->hand = [];
        $this->capturedCards = [];
        $this->scopeCount = 0;
    }
}
