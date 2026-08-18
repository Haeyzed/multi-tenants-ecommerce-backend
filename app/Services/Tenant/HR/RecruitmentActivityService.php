<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\Tenant\RecruitmentActivity;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight recruitment audit log. Omits salary, notes, resumes, and feedback text.
 */
class RecruitmentActivityService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(Model $subject, string $action, ?User $actor = null, array $meta = []): void
    {
        if (! Schema::hasTable('recruitment_activities')) {
            return;
        }

        RecruitmentActivity::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'actor_id' => $actor?->id,
            'meta' => $this->sanitize($meta),
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function sanitize(array $meta): array
    {
        unset(
            $meta['salary'],
            $meta['notes'],
            $meta['cover_letter'],
            $meta['comments'],
            $meta['strengths'],
            $meta['weaknesses'],
            $meta['resume'],
        );

        return $meta;
    }
}
