<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Interview round classification.
 */
enum InterviewType: string
{
    case Screening = 'screening';
    case Technical = 'technical';
    case Hr = 'hr';
    case Final = 'final';
    case Other = 'other';
}
