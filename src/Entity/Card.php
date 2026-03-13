<?php

namespace App\Entity;

use App\Enum\Suit;
use App\Repository\CardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardRepository::class)]
#[ORM\UniqueConstraint(name: 'card_suit_value', columns: ['suit', 'value'])]
class Card
{
    private const VALUE_NAMES = [
        1 => 'Asso',
        2 => 'Due',
        3 => 'Tre',
        4 => 'Quattro',
        5 => 'Cinque',
        6 => 'Sei',
        7 => 'Sette',
        8 => 'Fante',
        9 => 'Cavallo',
        10 => 'Re',
    ];

    private const SHORT_LABELS = [
        1 => 'A', 2 => '2', 3 => '3', 4 => '4', 5 => '5',
        6 => '6', 7 => '7', 8 => 'F', 9 => 'C', 10 => 'R',
    ];

    public const PRIMIERA_VALUES = [
        7 => 21, 6 => 18, 1 => 16, 5 => 15, 4 => 14,
        3 => 13, 2 => 12, 8 => 10, 9 => 10, 10 => 10,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', enumType: Suit::class)]
    private Suit $suit;

    #[ORM\Column(type: 'smallint')]
    private int $value;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telegramFileId = null;

    public function __construct(Suit $suit, int $value)
    {
        $this->suit = $suit;
        $this->value = $value;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSuit(): Suit
    {
        return $this->suit;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function getTelegramFileId(): ?string
    {
        return $this->telegramFileId;
    }

    public function setTelegramFileId(?string $telegramFileId): static
    {
        $this->telegramFileId = $telegramFileId;
        return $this;
    }

    public function getValueName(): string
    {
        return self::VALUE_NAMES[$this->value];
    }

    public function getDisplayName(): string
    {
        return sprintf('%s di %s', $this->getValueName(), $this->suit->label());
    }

    public function getWikimediaFilename(): string
    {
        $suitOffset = match ($this->suit) {
            Suit::Denari => 0,
            Suit::Coppe => 10,
            Suit::Spade => 20,
            Suit::Bastoni => 30,
        };

        $number = str_pad((string) ($suitOffset + $this->value), 2, '0', STR_PAD_LEFT);
        $suitName = strtolower($this->suit->label());

        // Special case: file 40 has capital B
        if ($suitOffset + $this->value === 40) {
            $suitName = 'Bastoni';
        }

        $valueName = self::VALUE_NAMES[$this->value];

        return sprintf('%s %s di %s.jpg', $number, $valueName, $suitName);
    }

    public function getRef(): string
    {
        return $this->suit->value[0] . $this->value;
    }

    public function getShortName(): string
    {
        return self::SHORT_LABELS[$this->value] . $this->suit->emoji();
    }

    public function getPrimieraValue(): int
    {
        return self::PRIMIERA_VALUES[$this->value];
    }

    public static function parseRef(string $ref): array
    {
        $suit = Suit::fromChar($ref[0]);
        $value = (int) substr($ref, 1);

        return [$suit, $value];
    }

    public static function valueFromRef(string $ref): int
    {
        return (int) substr($ref, 1);
    }

    public static function suitFromRef(string $ref): Suit
    {
        return Suit::fromChar($ref[0]);
    }

    public static function shortNameFromRef(string $ref): string
    {
        [$suit, $value] = self::parseRef($ref);
        return self::SHORT_LABELS[$value] . $suit->emoji();
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }
}
