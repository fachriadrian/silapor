<?php

namespace App\Enums;

enum ActorType: string
{
    case Guest = 'guest';
    case User = 'user';
    case Admin = 'admin';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Guest => 'Tamu',
            self::User => 'Pengguna',
            self::Admin => 'Staf IT',
            self::System => 'Sistem',
        };
    }
}
