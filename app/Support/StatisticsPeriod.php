<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Rentang waktu untuk halaman statistik. Batas hari dihitung menurut zona
 * waktu tampilan (WIB), lalu disimpan sebagai UTC agar cocok dengan database.
 */
class StatisticsPeriod
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $label,
    ) {}

    /**
     * @param  string  $key  '7', '30', '90', 'year', atau 'custom'
     */
    public static function make(string $key, ?string $from = null, ?string $to = null): self
    {
        $timezone = (string) config('silapor.display_timezone', 'Asia/Jakarta');
        $now = CarbonImmutable::now($timezone);

        if ($key === 'custom') {
            $start = self::parseDate($from, $timezone) ?? $now->subDays(29)->startOfDay();
            $end = self::parseDate($to, $timezone)?->endOfDay() ?? $now->endOfDay();

            if ($end->lessThan($start)) {
                [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
            }

            $label = $start->translatedFormat('j M Y').' – '.$end->translatedFormat('j M Y');
        } elseif ($key === 'year') {
            $start = $now->startOfYear();
            $end = $now->endOfDay();
            $label = 'Tahun '.$now->format('Y').' (sampai hari ini)';
        } else {
            $days = in_array($key, ['7', '30', '90'], true) ? (int) $key : 30;
            $start = $now->subDays($days - 1)->startOfDay();
            $end = $now->endOfDay();
            $label = "{$days} hari terakhir";
        }

        return new self($start->setTimezone('UTC'), $end->setTimezone('UTC'), $label);
    }

    private static function parseDate(?string $value, string $timezone): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', trim($value), $timezone);
        } catch (Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable ? $date->startOfDay() : null;
    }
}
