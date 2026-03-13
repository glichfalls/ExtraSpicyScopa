<?php

namespace App\Repository;

use App\Entity\Card;
use App\Enum\Suit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Card>
 */
class CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
    }

    public function findBySuitAndValue(Suit $suit, int $value): ?Card
    {
        return $this->findOneBy(['suit' => $suit, 'value' => $value]);
    }

    /** @return Card[] */
    public function findAllIndexed(): array
    {
        $cards = $this->findAll();
        $indexed = [];

        foreach ($cards as $card) {
            $key = $card->getSuit()->value . '_' . $card->getValue();
            $indexed[$key] = $card;
        }

        return $indexed;
    }

    public function seedAll(): void
    {
        $em = $this->getEntityManager();

        foreach (Suit::cases() as $suit) {
            for ($value = 1; $value <= 10; $value++) {
                if ($this->findBySuitAndValue($suit, $value) === null) {
                    $em->persist(new Card($suit, $value));
                }
            }
        }

        $em->flush();
    }
}
