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
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
    case Resigned = 'resigned';

    /**
     * Statuses this employment status may move to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::Inactive, self::OnLeave, self::Suspended, self::Terminated, self::Resigned],
            self::Inactive => [self::Active, self::Terminated, self::Resigned],
            self::OnLeave => [self::Active, self::Terminated, self::Resigned],
            self::Suspended => [self::Active, self::Terminated, self::Resigned],
            self::Terminated, self::Resigned => [],
        };
    }

    /**
     * Whether this status ends employment.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Terminated, self::Resigned], true);
    }

    /**
     * Whether this status may change to the given status.
     */
    public function canTransitionTo(self $status): bool
    {
        return $this === $status || in_array($status, $this->allowedTransitions(), true);
    }
}
