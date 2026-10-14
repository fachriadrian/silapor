<?php

namespace App\Enums;

enum ReportStatus: string
{
    case New = 'baru';
    case InProgress = 'diproses';
    case OnHold = 'ditunda';
    case Completed = 'selesai';
    case Cancelled = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::InProgress => 'Diproses',
            self::OnHold => 'Ditunda',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Warna badge (nama warna Flux UI).
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'sky',
            self::InProgress => 'amber',
            self::OnHold => 'zinc',
            self::Completed => 'green',
            self::Cancelled => 'red',
        };
    }

    /**
     * Status akhir: laporan tidak lagi memerlukan tindakan.
     * Laporan berstatus ini disembunyikan dari dashboard admin (DSH-04).
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * Apakah laporan masih boleh dibatalkan oleh pelapor (CNL-01, CNL-04).
     */
    public function isCancellable(): bool
    {
        return ! $this->isFinal();
    }

    /**
     * Transisi status yang diizinkan (lihat diagram status di PRD bagian 7.1).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::InProgress, self::Completed, self::Cancelled],
            self::InProgress => [self::OnHold, self::Completed, self::Cancelled],
            self::OnHold => [self::InProgress, self::Cancelled],
            self::Completed => [self::InProgress], // dibuka kembali oleh admin
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function finalValues(): array
    {
        return [self::Completed->value, self::Cancelled->value];
    }

    /**
     * Opsi untuk dropdown/filter (nilai => label).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
