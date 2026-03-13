<?php

namespace App\Enum;

enum Suit: string
{
    case Denari = 'denari';
    case Coppe = 'coppe';
    case Spade = 'spade';
    case Bastoni = 'bastoni';

    public function emoji(): string
    {
        return match ($this) {
            self::Denari => "\u{1FA99}",
            self::Coppe => "\u{1F3C6}",
            self::Spade => "\u{2694}\u{FE0F}",
            self::Bastoni => "\u{1FAB5}",
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Denari => 'Denari',
            self::Coppe => 'Coppe',
            self::Spade => 'Spade',
            self::Bastoni => 'Bastoni',
        };
    }
}
