<?php

namespace App\Repository;

use App\Entity\StickerPack;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StickerPack>
 */
class StickerPackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StickerPack::class);
    }

    public function findByName(string $name): ?StickerPack
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function findByUser(User $user): ?StickerPack
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function save(StickerPack $stickerPack, bool $flush = true): void
    {
        $this->getEntityManager()->persist($stickerPack);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
