<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Employment lifecycle status for a tenant employee profile.
 */
enum EmploymentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Terminated = 'terminated';
    case OnLeave = 'on_leave';

    /**
     * Statuses this employment status may move to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::Inactive, self::Terminated, self::OnLeave],
            self::Inactive => [self::Active, self::Terminated],
            self::OnLeave => [self::Active, self::Terminated],
            self::Terminated => [],
        };
    }

    /**
     * Whether this status may change to the given status.
     */
    public function canTransitionTo(self $status): bool
    {
        return $this === $status || in_array($status, $this->allowedTransitions(), true);
    }
}
