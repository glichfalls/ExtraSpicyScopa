<?php

namespace App\Service;

use App\Entity\Card;
use App\Entity\Game;
use App\Entity\Player;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Enum\Suit;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class ScopaGameService
{
    public const TARGET_SCORE = 12;

    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly CardRepository $cardRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function createGame(int $chatId, User $creator): Game
    {
        $game = new Game($chatId);
        $player = new Player($game, $creator, 0);

        $this->em->persist($game);
        $this->em->persist($player);
        $this->em->flush();

        return $game;
    }

    public function joinGame(Game $game, User $user): Player
    {
        if ($game->getPlayers()->count() >= 2) {
            throw new \RuntimeException('Game is already full.');
        }

        if ($game->getPlayerByUser($user) !== null) {
            throw new \RuntimeException('You are already in this game.');
        }

        $player = new Player($game, $user, 1);
        $this->em->persist($player);

        $game->setStatus(GameStatus::Playing);
        $this->startRound($game);

        $this->em->flush();

        return $player;
    }

    public function startRound(Game $game): void
    {
        // Build full deck
        $deck = [];
        foreach (Suit::cases() as $suit) {
            for ($v = 1; $v <= 10; $v++) {
                $deck[] = $suit->value[0] . $v;
            }
        }
        shuffle($deck);

        // Reset players for new round
        foreach ($game->getPlayers() as $player) {
            $player->resetForNewRound();
        }

        // Deal 4 cards to table
        $tableCards = array_splice($deck, 0, 4);
        $game->setTableCards($tableCards);

        // Deal 3 cards to each player
        foreach ($game->getPlayers() as $player) {
            $hand = array_splice($deck, 0, 3);
            $player->setHand($hand);
        }

        $game->setDeck($deck);
        $game->setLastCapturePlayerIndex(null);
        $game->setPendingCardRef(null);
        $game->setPendingCaptures(null);

        // Non-dealer plays first
        $game->setCurrentPlayerIndex($game->getDealerPlayerIndex() === 0 ? 1 : 0);
    }

    public function deal(Game $game): bool
    {
        $deck = $game->getDeck();
        if (empty($deck)) {
            return false;
        }

        foreach ($game->getPlayers() as $player) {
            $hand = array_splice($deck, 0, 3);
            $player->setHand($hand);
        }

        $game->setDeck($deck);

        return true;
    }

    /**
     * @return array{
     *   captured: bool,
     *   capturedCards: string[],
     *   isScopa: bool,
     *   needsCaptureChoice: bool,
     *   captureOptions: array,
     *   roundOver: bool,
     *   gameOver: bool,
     *   roundScores: ?array,
     *   dealt: bool,
     * }
     */
    public function playCard(Game $game, Player $player, string $cardRef, ?int $captureChoice = null): array
    {
        $playedValue = Card::valueFromRef($cardRef);
        $tableCards = $game->getTableCards();

        // Remove card from hand
        $player->removeFromHand($cardRef);

        // Find valid captures
        $captures = $this->getValidCaptures($playedValue, $tableCards);

        // No captures possible
        if (empty($captures)) {
            $tableCards[] = $cardRef;
            $game->setTableCards($tableCards);
            $game->switchTurn();
            $this->em->flush();

            return $this->afterPlay($game, false, [], false);
        }

        // Multiple capture options — ask the player
        if (count($captures) > 1 && $captureChoice === null) {
            $game->setPendingCardRef($cardRef);
            $game->setPendingCaptures($captures);
            $this->em->flush();

            return [
                'captured' => false,
                'capturedCards' => [],
                'isScopa' => false,
                'needsCaptureChoice' => true,
                'captureOptions' => $captures,
                'roundOver' => false,
                'gameOver' => false,
                'roundScores' => null,
                'dealt' => false,
            ];
        }

        // Single capture or choice made
        $chosen = $captureChoice !== null ? $captures[$captureChoice] : $captures[0];

        // Remove captured cards from table
        $remaining = $tableCards;
        foreach ($chosen as $capturedRef) {
            $key = array_search($capturedRef, $remaining, true);
            if ($key !== false) {
                array_splice($remaining, $key, 1);
            }
        }
        $game->setTableCards($remaining);

        // Add played card + captured cards to player's pile
        $allCaptured = array_merge([$cardRef], $chosen);
        $player->addCapturedCards($allCaptured);

        $game->setLastCapturePlayerIndex($player->getPlayerIndex());

        // Check for scopa (table cleared)
        $isScopa = empty($remaining);

        // Last play of the game doesn't count as scopa
        $isLastPlay = empty($game->getDeck()) && $game->bothHandsEmpty();
        if ($isScopa && !$isLastPlay) {
            $player->incrementScopeCount();
        }

        // Clear pending state
        $game->setPendingCardRef(null);
        $game->setPendingCaptures(null);

        $game->switchTurn();
        $this->em->flush();

        return $this->afterPlay($game, true, $allCaptured, $isScopa && !$isLastPlay);
    }

    public function resolveCaptureChoice(Game $game, int $captureChoice): array
    {
        $cardRef = $game->getPendingCardRef();
        $captures = $game->getPendingCaptures();
        $player = $game->getCurrentPlayer();

        $game->setPendingCardRef(null);
        $game->setPendingCaptures(null);

        return $this->playCard($game, $player, $cardRef, $captureChoice);
    }

    /**
     * Find all valid capture combinations for a played card value.
     * Rule: if a single card matches, you MUST take a single card (not a sum).
     *
     * @return string[][] Array of possible capture sets
     */
    public function getValidCaptures(int $playedValue, array $tableCards): array
    {
        // Single card matches take priority
        $singles = [];
        foreach ($tableCards as $ref) {
            if (Card::valueFromRef($ref) === $playedValue) {
                $singles[] = [$ref];
            }
        }

        if (!empty($singles)) {
            return $singles;
        }

        // Find subsets that sum to played value
        return $this->findSubsetsWithSum($tableCards, $playedValue);
    }

    /**
     * @return array{
     *   scores: array<int, int>,
     *   details: array<int, array>,
     * }
     */
    public function calculateRoundScore(Game $game): array
    {
        $players = [];
        foreach ($game->getPlayers() as $p) {
            $players[$p->getPlayerIndex()] = $p;
        }

        $details = [0 => [], 1 => []];
        $scores = [0 => 0, 1 => 0];

        $captured = [
            0 => $players[0]->getCapturedCards(),
            1 => $players[1]->getCapturedCards(),
        ];

        // Most cards
        $counts = [0 => count($captured[0]), 1 => count($captured[1])];
        if ($counts[0] !== $counts[1]) {
            $winner = $counts[0] > $counts[1] ? 0 : 1;
            $scores[$winner]++;
            $details[0]['carte'] = $counts[0];
            $details[1]['carte'] = $counts[1];
            $details[$winner]['carte_won'] = true;
        }

        // Most denari
        $denariCounts = [0 => 0, 1 => 0];
        foreach ([0, 1] as $i) {
            foreach ($captured[$i] as $ref) {
                if (Card::suitFromRef($ref) === Suit::Denari) {
                    $denariCounts[$i]++;
                }
            }
        }
        if ($denariCounts[0] !== $denariCounts[1]) {
            $winner = $denariCounts[0] > $denariCounts[1] ? 0 : 1;
            $scores[$winner]++;
            $details[0]['denari'] = $denariCounts[0];
            $details[1]['denari'] = $denariCounts[1];
            $details[$winner]['denari_won'] = true;
        }

        // Sette bello (7 of denari)
        foreach ([0, 1] as $i) {
            if (in_array('d7', $captured[$i], true)) {
                $scores[$i]++;
                $details[$i]['settebello'] = true;
                break;
            }
        }

        // Primiera
        $primiera = [0 => 0, 1 => 0];
        foreach ([0, 1] as $i) {
            $bestPerSuit = [];
            foreach ($captured[$i] as $ref) {
                [$suit, $value] = Card::parseRef($ref);
                $pv = Card::PRIMIERA_VALUES[$value] ?? 0;
                $suitKey = $suit->value;
                if (!isset($bestPerSuit[$suitKey]) || $pv > $bestPerSuit[$suitKey]) {
                    $bestPerSuit[$suitKey] = $pv;
                }
            }
            $primiera[$i] = array_sum($bestPerSuit);
            $details[$i]['primiera'] = $primiera[$i];
        }
        if ($primiera[0] !== $primiera[1]) {
            $winner = $primiera[0] > $primiera[1] ? 0 : 1;
            $scores[$winner]++;
            $details[$winner]['primiera_won'] = true;
        }

        // Scope
        foreach ([0, 1] as $i) {
            $scopeCount = $players[$i]->getScopeCount();
            $scores[$i] += $scopeCount;
            $details[$i]['scope'] = $scopeCount;
        }

        return ['scores' => $scores, 'details' => $details];
    }

    public function findActiveGameForChat(int $chatId): ?Game
    {
        return $this->gameRepository->findActiveForChat($chatId);
    }

    public function findActiveGameForUser(int $telegramId): ?Game
    {
        return $this->gameRepository->findActiveForUser($telegramId);
    }

    public function findOrCreateUser(int $telegramId, string $firstName, ?string $username): User
    {
        return $this->userRepository->findOrCreateByTelegramData($telegramId, $firstName, $username);
    }

    public function getCardByFileId(string $fileId): ?Card
    {
        return $this->cardRepository->findOneBy(['telegramFileId' => $fileId]);
    }

    public function getCardByRef(string $ref): ?Card
    {
        [$suit, $value] = Card::parseRef($ref);
        return $this->cardRepository->findBySuitAndValue($suit, $value);
    }

    /** @return Card[] indexed by ref */
    public function getAllCards(): array
    {
        $cards = $this->cardRepository->findAll();
        $indexed = [];
        foreach ($cards as $card) {
            $indexed[$card->getRef()] = $card;
        }
        return $indexed;
    }

    private function afterPlay(Game $game, bool $captured, array $capturedCards, bool $isScopa): array
    {
        $dealt = false;
        $roundOver = false;
        $gameOver = false;
        $roundScores = null;

        if ($game->bothHandsEmpty()) {
            if (!empty($game->getDeck())) {
                // Deal more cards
                $this->deal($game);
                $dealt = true;
                $this->em->flush();
            } else {
                // Round over
                $roundOver = true;

                // Last capturer gets remaining table cards
                if ($game->getLastCapturePlayerIndex() !== null) {
                    $lastPlayer = $game->getPlayer($game->getLastCapturePlayerIndex());
                    $lastPlayer->addCapturedCards($game->getTableCards());
                    $game->setTableCards([]);
                }

                $roundScores = $this->calculateRoundScore($game);

                // Add scores
                foreach ($roundScores['scores'] as $idx => $points) {
                    $game->getPlayer($idx)->addScore($points);
                }

                // Check if game is over
                $p0Score = $game->getPlayer(0)->getScore();
                $p1Score = $game->getPlayer(1)->getScore();

                if ($p0Score >= self::TARGET_SCORE || $p1Score >= self::TARGET_SCORE) {
                    if ($p0Score !== $p1Score) {
                        $gameOver = true;
                        $game->setStatus(GameStatus::Finished);
                    } else {
                        // Tied at target — play another round
                        $this->startNextRound($game);
                        $dealt = true;
                    }
                } else {
                    $this->startNextRound($game);
                    $dealt = true;
                }

                $this->em->flush();
            }
        }

        return [
            'captured' => $captured,
            'capturedCards' => $capturedCards,
            'isScopa' => $isScopa,
            'needsCaptureChoice' => false,
            'captureOptions' => [],
            'roundOver' => $roundOver,
            'gameOver' => $gameOver,
            'roundScores' => $roundScores,
            'dealt' => $dealt,
        ];
    }

    private function startNextRound(Game $game): void
    {
        $game->setDealerPlayerIndex($game->getDealerPlayerIndex() === 0 ? 1 : 0);
        $game->setRoundNumber($game->getRoundNumber() + 1);
        $this->startRound($game);
    }

    /** @return string[][] */
    private function findSubsetsWithSum(array $cards, int $targetSum): array
    {
        $results = [];
        $n = count($cards);

        for ($mask = 1; $mask < (1 << $n); $mask++) {
            $subset = [];
            $sum = 0;
            for ($i = 0; $i < $n; $i++) {
                if ($mask & (1 << $i)) {
                    $subset[] = $cards[$i];
                    $sum += Card::valueFromRef($cards[$i]);
                }
            }
            if ($sum === $targetSum) {
                $results[] = $subset;
            }
        }

        return $results;
    }
}
