<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Interviewer hire recommendation.
 */
enum InterviewRecommendation: string
{
    case StrongHire = 'strong_hire';
    case Hire = 'hire';
    case Neutral = 'neutral';
    case NoHire = 'no_hire';
    case StrongNoHire = 'strong_no_hire';
}
