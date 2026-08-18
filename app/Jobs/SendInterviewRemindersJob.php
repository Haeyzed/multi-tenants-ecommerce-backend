<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Tenant\HR\InterviewStatus;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Interview;
use App\Services\Tenant\HR\HrSettingsService;
use App\Support\RecruitmentNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Sends interview reminders at tenant-configured hours before scheduled_at.
 */
class SendInterviewRemindersJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $tenantId = null) {}

    public function handle(HrSettingsService $hrSettings, RecruitmentNotifier $notifier): void
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            Log::warning('SendInterviewRemindersJob: tenant id is required');

            return;
        }

        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            Log::warning('SendInterviewRemindersJob: tenant not found', ['tenant_id' => $this->tenantId]);

            return;
        }

        $tenant->run(function () use ($hrSettings, $notifier): void {
            if (! $hrSettings->isRecruitmentEnabled() || ! Schema::hasTable('interviews')) {
                return;
            }

            $hours = $hrSettings->interviewReminderHours();

            if ($hours === []) {
                return;
            }

            $now = now();

            Interview::query()
                ->with(['application.candidate', 'application.jobOpening', 'currentMeeting'])
                ->whereIn('status', [InterviewStatus::Scheduled, InterviewStatus::Rescheduled])
                ->where('scheduled_at', '>', $now)
                ->each(function (Interview $interview) use ($hours, $now, $notifier): void {
                    $sent = array_map('intval', $interview->reminders_sent ?? []);
                    $changed = false;

                    foreach ($hours as $hour) {
                        if (in_array($hour, $sent, true)) {
                            continue;
                        }

                        $remindAt = $interview->scheduled_at->copy()->subHours($hour);

                        if ($remindAt->gt($now) || $remindAt->lt($now->copy()->subHours(2))) {
                            continue;
                        }

                        $payload = $interview->recruitmentNotificationPayload(includeHostUrl: false);
                        $payload['reminder_hours'] = $hour;

                        $notifier->notifyStaff('hr.interview.reminder', $payload);

                        if ($interview->application?->candidate !== null) {
                            $notifier->notifyCandidate($interview->application->candidate, 'hr.interview.reminder', $payload);
                        }

                        $sent[] = $hour;
                        $changed = true;
                    }

                    if ($changed) {
                        $interview->reminders_sent = array_values(array_unique($sent));
                        $interview->save();
                    }
                });
        });
    }
}
