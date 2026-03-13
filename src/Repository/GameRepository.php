<?php

namespace App\Repository;

use App\Entity\Game;
use App\Enum\GameStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    public function findActiveForChat(int $chatId): ?Game
    {
        return $this->createQueryBuilder('g')
            ->where('g.chatId = :chatId')
            ->andWhere('g.status IN (:statuses)')
            ->setParameter('chatId', $chatId)
            ->setParameter('statuses', [GameStatus::Waiting->value, GameStatus::Playing->value])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveForUser(int $telegramId): ?Game
    {
        return $this->createQueryBuilder('g')
            ->join('g.players', 'p')
            ->join('p.user', 'u')
            ->where('u.telegramId = :telegramId')
            ->andWhere('g.status = :status')
            ->setParameter('telegramId', $telegramId)
            ->setParameter('status', GameStatus::Playing->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
