<?php

namespace App\Enums;

enum LogEvent: string
{
    case Created = 'dibuat';
    case StatusChanged = 'status_berubah';
    case Note = 'catatan';
    case UrgencyChanged = 'urgensi_berubah';
    case Cancelled = 'dibatalkan';
    case Reopened = 'dibuka_kembali';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Laporan dibuat',
            self::StatusChanged => 'Status diubah',
            self::Note => 'Catatan progres',
            self::UrgencyChanged => 'Urgensi diubah',
            self::Cancelled => 'Laporan dibatalkan',
            self::Reopened => 'Laporan dibuka kembali',
        };
    }
}
