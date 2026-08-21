<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\HR\Interview;
use App\Models\HR\InterviewMeeting;
use App\Models\Tenant\User;

/**
 * Meeting mutations are recruitment-manage only. Assigned interviewers may view.
 */
class InterviewMeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.recruitment.view')
            || $user->can('hr.recruitment.manage')
            || $user->can('hr.recruitment.feedback')
            || $user->can('hr.view');
    }

    public function view(User $user, InterviewMeeting $meeting): bool
    {
        return $user->can('view', $meeting->interview);
    }

    public function create(User $user, Interview $interview): bool
    {
        return $user->can('hr.recruitment.manage');
    }

    public function update(User $user, Interview $interview): bool
    {
        return $this->create($user, $interview);
    }

    public function delete(User $user, Interview $interview): bool
    {
        return $this->create($user, $interview);
    }
}
