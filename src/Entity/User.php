<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'bigint', unique: true)]
    private ?int $telegramId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stickerPackName = null;

    /** @var Collection<int, StickerPack> */
    #[ORM\OneToMany(targetEntity: StickerPack::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $stickerPacks;

    public function __construct()
    {
        $this->stickerPacks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTelegramId(): ?int
    {
        return $this->telegramId;
    }

    public function setTelegramId(int $telegramId): static
    {
        $this->telegramId = $telegramId;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getStickerPackName(): ?string
    {
        return $this->stickerPackName;
    }

    public function setStickerPackName(?string $stickerPackName): static
    {
        $this->stickerPackName = $stickerPackName;
        return $this;
    }

    /** @return Collection<int, StickerPack> */
    public function getStickerPacks(): Collection
    {
        return $this->stickerPacks;
    }

    public function addStickerPack(StickerPack $stickerPack): static
    {
        if (!$this->stickerPacks->contains($stickerPack)) {
            $this->stickerPacks->add($stickerPack);
            $stickerPack->setUser($this);
        }
        return $this;
    }

    public function removeStickerPack(StickerPack $stickerPack): static
    {
        if ($this->stickerPacks->removeElement($stickerPack)) {
            if ($stickerPack->getUser() === $this) {
                $stickerPack->setUser(null);
            }
        }
        return $this;
    }
}
