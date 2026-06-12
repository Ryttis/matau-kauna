<?php

namespace App\Services;

use App\Models\Problem;
use Carbon\Carbon;

class ProblemDeadlineMonitorService
{
    public const STATUS_APPROACHING = 'approaching';

    public const STATUS_OVERDUE = 'overdue';

    public const REMIND_DAYS_BEFORE = 2;

    public function __construct(
        private ProblemAssignmentNotifier $notifier
    ) {}

    public function run(): void
    {
        $today = Carbon::today();
        $approachingEnd = $today->copy()->addDays(self::REMIND_DAYS_BEFORE);
        $sentTodayStart = $today->copy()->startOfDay();

        $withDeadline = Problem::query()
            ->notificationActive()
            ->whereNotNull('completion_deadline')
            ->whereNull('merged_into_problem_id')
            ->get();

        foreach ($withDeadline as $problem) {
            $this->updateDeadlineStatus($problem, $today, $approachingEnd);
        }

        $overdue = Problem::query()
            ->notificationActive()
            ->whereNotNull('completion_deadline')
            ->where('completion_deadline', '<', $today)
            ->whereNull('merged_into_problem_id')
            ->get();

        foreach ($overdue as $problem) {
            if ($this->shouldSendReminder($problem, $sentTodayStart)) {
                $this->sendReminder($problem, self::STATUS_OVERDUE);
            }
        }

        $approaching = Problem::query()
            ->notificationActive()
            ->whereNotNull('completion_deadline')
            ->whereBetween('completion_deadline', [$today, $approachingEnd])
            ->whereNull('merged_into_problem_id')
            ->get();

        foreach ($approaching as $problem) {
            if ($this->shouldSendReminder($problem, $sentTodayStart)) {
                $this->sendReminder($problem, self::STATUS_APPROACHING);
            }
        }
    }

    private function updateDeadlineStatus(Problem $problem, Carbon $today, Carbon $approachingEnd): void
    {
        $deadline = $problem->completion_deadline;
        if (! $deadline) {
            return;
        }
        $newStatus = null;
        if ($deadline->lt($today)) {
            $newStatus = self::STATUS_OVERDUE;
        } elseif ($deadline->lte($approachingEnd)) {
            $newStatus = self::STATUS_APPROACHING;
        }
        if ($problem->deadline_status !== $newStatus) {
            $problem->deadline_status = $newStatus;
            $problem->save();
        }
    }

    private function shouldSendReminder(Problem $problem, Carbon $sentTodayStart): bool
    {
        if ($problem->deadline_reminder_sent_at === null) {
            return true;
        }

        return $problem->deadline_reminder_sent_at->lt($sentTodayStart);
    }

    private function sendReminder(Problem $problem, string $status): void
    {
        $problem->deadline_reminder_sent_at = now();
        $problem->save();

        $this->notifier->notifyDeadlineReminder($problem, $status === self::STATUS_OVERDUE ? 'overdue' : 'approaching');
    }
}
