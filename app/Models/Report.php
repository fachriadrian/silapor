<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Enums\ReportUrgency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ReportStatus $status
 * @property ReportUrgency $urgency
 * @property User|null $reporter
 * @property IssueType|null $issueType
 */
class Report extends Model
{
    /**
     * Field yang diisi pelapor. 'code', 'status', 'completed_at', dan
     * 'cancelled_at' sengaja TIDAK mass-assignable: hanya diatur oleh
     * Action (pembuatan kode, perubahan status) agar tidak bisa dimanipulasi
     * lewat input form.
     */
    protected $fillable = [
        'category_id',
        'issue_type_id',
        'title',
        'description',
        'location',
        'urgency',
        'reporter_user_id',
        'guest_name',
        'guest_contact',
    ];

    protected $attributes = [
        'status' => ReportStatus::New->value,
        'urgency' => ReportUrgency::Medium->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'urgency' => ReportUrgency::class,
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------
    // Relasi
    // ---------------------------------------------------------------

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<IssueType, $this>
     */
    public function issueType(): BelongsTo
    {
        return $this->belongsTo(IssueType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /**
     * @return HasMany<ReportLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ReportLog::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    // ---------------------------------------------------------------
    // Accessor
    // ---------------------------------------------------------------

    /**
     * Kode untuk tampilan, mis. "#WF001".
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function displayCode(): Attribute
    {
        return Attribute::get(fn () => '#'.$this->code);
    }

    /**
     * Nama pelapor lengkap (user terdaftar atau guest).
     *
     * @return Attribute<string, never>
     */
    protected function reporterName(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->reporter !== null) {
                return $this->reporter->name;
            }

            return $this->guest_name ?? 'Tanpa nama';
        });
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    public function isReportedBy(?User $user): bool
    {
        return $user !== null
            && $this->reporter_user_id !== null
            && (int) $this->reporter_user_id === (int) $user->getKey();
    }

    public function isCancellable(): bool
    {
        return $this->status->isCancellable();
    }

    /**
     * Nama pelapor sesuai siapa yang melihat: admin dan pemilik laporan melihat
     * nama lengkap, selain itu disamarkan (mis. "Budi S.").
     */
    public function reporterNameFor(?User $viewer): string
    {
        if ($viewer !== null && ($viewer->isAdmin() || $this->isReportedBy($viewer))) {
            return $this->reporter_name;
        }

        return self::maskName($this->reporter_name);
    }

    protected static function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) < 2) {
            return $parts[0] ?? 'Pelapor';
        }

        $lastInitial = mb_strtoupper(mb_substr(end($parts), 0, 1));

        return $parts[0].' '.$lastInitial.'.';
    }

    // ---------------------------------------------------------------
    // Scope
    // ---------------------------------------------------------------

    /**
     * Laporan yang masih perlu tindakan (bukan Selesai/Dibatalkan).
     *
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ReportStatus::finalValues());
    }

    /**
     * Laporan Selesai/Dibatalkan (arsip).
     *
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereIn('status', ReportStatus::finalValues());
    }

    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Pencarian berdasarkan kode (dengan/tanpa '#'), judul, atau lokasi.
     *
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = ltrim(trim((string) $term), '#');

        if ($term === '') {
            return $query;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        return $query->where(
            /** @param Builder<Report> $q */
            function (Builder $q) use ($escaped): void {
                $q->where('code', 'like', $escaped.'%')
                    ->orWhere('title', 'like', '%'.$escaped.'%')
                    ->orWhere('location', 'like', '%'.$escaped.'%');
            }
        );
    }
}
