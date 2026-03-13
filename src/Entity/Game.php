<?php

namespace App\Entity;

use App\Enum\GameStatus;
use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'bigint')]
    private int $chatId;

    #[ORM\Column(type: 'string', enumType: GameStatus::class)]
    private GameStatus $status = GameStatus::Waiting;

    #[ORM\Column(type: 'json')]
    private array $deck = [];

    #[ORM\Column(type: 'json')]
    private array $tableCards = [];

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $tableMessageId = null;

    #[ORM\Column(type: 'smallint')]
    private int $currentPlayerIndex = 0;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $lastCapturePlayerIndex = null;

    #[ORM\Column(type: 'smallint')]
    private int $dealerPlayerIndex = 0;

    #[ORM\Column(type: 'smallint')]
    private int $roundNumber = 1;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $pendingCardRef = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $pendingCaptures = null;

    /** @var Collection<int, Player> */
    #[ORM\OneToMany(targetEntity: Player::class, mappedBy: 'game', cascade: ['persist'])]
    #[ORM\OrderBy(['playerIndex' => 'ASC'])]
    private Collection $players;

    public function __construct(int $chatId)
    {
        $this->chatId = $chatId;
        $this->players = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChatId(): int
    {
        return $this->chatId;
    }

    public function getStatus(): GameStatus
    {
        return $this->status;
    }

    public function setStatus(GameStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getDeck(): array
    {
        return $this->deck;
    }

    public function setDeck(array $deck): static
    {
        $this->deck = $deck;
        return $this;
    }

    public function getTableCards(): array
    {
        return $this->tableCards;
    }

    public function setTableCards(array $tableCards): static
    {
        $this->tableCards = $tableCards;
        return $this;
    }

    public function getTableMessageId(): ?int
    {
        return $this->tableMessageId;
    }

    public function setTableMessageId(?int $tableMessageId): static
    {
        $this->tableMessageId = $tableMessageId;
        return $this;
    }

    public function getCurrentPlayerIndex(): int
    {
        return $this->currentPlayerIndex;
    }

    public function setCurrentPlayerIndex(int $currentPlayerIndex): static
    {
        $this->currentPlayerIndex = $currentPlayerIndex;
        return $this;
    }

    public function getLastCapturePlayerIndex(): ?int
    {
        return $this->lastCapturePlayerIndex;
    }

    public function setLastCapturePlayerIndex(?int $index): static
    {
        $this->lastCapturePlayerIndex = $index;
        return $this;
    }

    public function getDealerPlayerIndex(): int
    {
        return $this->dealerPlayerIndex;
    }

    public function setDealerPlayerIndex(int $dealerPlayerIndex): static
    {
        $this->dealerPlayerIndex = $dealerPlayerIndex;
        return $this;
    }

    public function getRoundNumber(): int
    {
        return $this->roundNumber;
    }

    public function setRoundNumber(int $roundNumber): static
    {
        $this->roundNumber = $roundNumber;
        return $this;
    }

    public function getPendingCardRef(): ?string
    {
        return $this->pendingCardRef;
    }

    public function setPendingCardRef(?string $pendingCardRef): static
    {
        $this->pendingCardRef = $pendingCardRef;
        return $this;
    }

    public function getPendingCaptures(): ?array
    {
        return $this->pendingCaptures;
    }

    public function setPendingCaptures(?array $pendingCaptures): static
    {
        $this->pendingCaptures = $pendingCaptures;
        return $this;
    }

    public function hasPendingCapture(): bool
    {
        return $this->pendingCardRef !== null;
    }

    /** @return Collection<int, Player> */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function addPlayer(Player $player): static
    {
        if (!$this->players->contains($player)) {
            $this->players->add($player);
        }
        return $this;
    }

    public function getPlayer(int $index): ?Player
    {
        foreach ($this->players as $player) {
            if ($player->getPlayerIndex() === $index) {
                return $player;
            }
        }
        return null;
    }

    public function getCurrentPlayer(): ?Player
    {
        return $this->getPlayer($this->currentPlayerIndex);
    }

    public function getOpponent(Player $player): ?Player
    {
        return $this->getPlayer($player->getPlayerIndex() === 0 ? 1 : 0);
    }

    public function getPlayerByUser(User $user): ?Player
    {
        foreach ($this->players as $player) {
            if ($player->getUser()->getTelegramId() === $user->getTelegramId()) {
                return $player;
            }
        }
        return null;
    }

    public function switchTurn(): void
    {
        $this->currentPlayerIndex = $this->currentPlayerIndex === 0 ? 1 : 0;
    }

    public function bothHandsEmpty(): bool
    {
        foreach ($this->players as $player) {
            if (!empty($player->getHand())) {
                return false;
            }
        }
        return true;
    }
}
