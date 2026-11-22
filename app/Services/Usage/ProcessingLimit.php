<?php

namespace App\Services\Usage;

use App\Enums\UsageKind;
use App\Models\UsageRecord;
use App\Models\User;

/**
 * Daily processing limit per encoder (PROJECT_SPEC §14, cost controls): the
 * transcription minutes used today, against the encoder's own limit or the
 * `ENCODER_DAILY_PROCESSING_MINUTES` default. Checked before processing
 * starts (new record, retry: ChecksDailyProcessingLimit). Admins have no limit. "Today" is the calendar
 * day in the app's timezone.
 */
class ProcessingLimit
{
    /**
     * Minutes allowed per day, or null when there is no limit (admins).
     */
    public function limitFor(User $user): ?int
    {
        if ($user->isAdmin()) {
            return null;
        }

        return $user->daily_processing_minutes_limit ?? (int) config('archive.encoder_daily_processing_minutes');
    }

    public function usedToday(User $user): float
    {
        return round((float) UsageRecord::query()
            ->where('user_id', $user->id)
            ->where('kind', UsageKind::TranscriptionMinutes)
            ->where('occurred_at', '>=', now()->startOfDay())
            ->sum('quantity'), 2);
    }

    /**
     * @return array{limit: int|null, used: float, remaining: float|null}
     */
    public function status(User $user): array
    {
        $limit = $this->limitFor($user);
        $used = $limit === null ? 0.0 : $this->usedToday($user);

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => $limit === null ? null : max(0.0, round($limit - $used, 2)),
        ];
    }

    /**
     * The error to show once today's minutes are used up; null while some are
     * left, or when there is no limit.
     */
    public function exceededMessage(User $user): ?string
    {
        $status = $this->status($user);

        if ($status['remaining'] === null || $status['remaining'] > 0) {
            return null;
        }

        return "You have used today's processing limit ({$status['limit']} minutes). Try again tomorrow, or ask an admin to raise your limit.";
    }
}
