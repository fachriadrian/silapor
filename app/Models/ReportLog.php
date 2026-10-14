<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\LogEvent;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Log progres laporan. Bersifat append-only (LOG-04): entri hanya
 * boleh ditambah, tidak boleh diubah atau dihapus.
 */
class ReportLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'report_id',
        'actor_type',
        'actor_id',
        'actor_name',
        'event_type',
        'from_status',
        'to_status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'event_type' => LogEvent::class,
            'from_status' => ReportStatus::class,
            'to_status' => ReportStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Log progres bersifat append-only dan tidak dapat diubah.');
        });

        static::deleting(function () {
            throw new LogicException('Log progres bersifat append-only dan tidak dapat dihapus.');
        });
    }

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
