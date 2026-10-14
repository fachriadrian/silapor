<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Category extends Model
{
    /**
     * 'last_seq' sengaja tidak mass-assignable: hanya diubah oleh
     * proses pembuatan kode laporan (di dalam transaksi).
     */
    protected $fillable = [
        'name',
        'prefix',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_seq' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // KODE-05: prefix tidak boleh diubah setelah dipakai oleh laporan.
        static::updating(function (Category $category) {
            if ($category->isDirty('prefix') && $category->reports()->exists()) {
                throw new LogicException('Prefix kategori tidak dapat diubah setelah dipakai oleh laporan.');
            }
        });
    }

    /**
     * Prefix selalu disimpan huruf kapital tanpa spasi.
     *
     * @return Attribute<string, string>
     */
    protected function prefix(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(trim($value)),
        );
    }

    /**
     * @return HasMany<IssueType, $this>
     */
    public function issueTypes(): HasMany
    {
        return $this->hasMany(IssueType::class);
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
