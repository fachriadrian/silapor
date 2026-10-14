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
 *
 * @property ActorType $actor_type
 * @property string $actor_name
 * @property LogEvent $event_type
 * @property ReportStatus|null $from_status
 * @property ReportStatus|null $to_status
 * @property string|null $note
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
     * Nama pelaku sesuai siapa yang melihat. Nama guest/user pelapor
     * disamarkan bagi yang bukan admin atau pemilik laporan; nama staf IT
     * dan sistem selalu tampil.
     */
    public function actorNameFor(?User $viewer, Report $report): string
    {
        if (in_array($this->actor_type, [ActorType::Admin, ActorType::System], true)) {
            return $this->actor_name;
        }

        if ($viewer !== null && ($viewer->isAdmin() || $report->isReportedBy($viewer))) {
            return $this->actor_name;
        }

        return Report::maskName($this->actor_name);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Mencatat satu entri log progres. Jika $actor null, pelakunya dianggap
     * guest dan namanya diambil dari $guestName.
     */
    public static function record(
        Report $report,
        LogEvent $event,
        ?User $actor,
        ?string $note = null,
        ?ReportStatus $from = null,
        ?ReportStatus $to = null,
        ?string $guestName = null,
    ): self {
        if ($actor === null) {
            $actorType = ActorType::Guest;
            $actorName = $guestName ?? 'Tamu';
        } else {
            $actorType = $actor->isAdmin() ? ActorType::Admin : ActorType::User;
            $actorName = $actor->name;
        }

        return $report->logs()->create([
            'actor_type' => $actorType,
            'actor_id' => $actor?->getKey(),
            'actor_name' => $actorName,
            'event_type' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }
}
