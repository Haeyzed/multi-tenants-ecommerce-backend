<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\HR\PublicHoliday;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Public holiday calendar used by overtime classification.
 */
class PublicHolidayService
{
    /**
     * @param  array{search?: string|null, year?: int|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, PublicHoliday>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return PublicHoliday::query()
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{observed_on: string, name: string, repeats_annually?: bool}  $data
     *
     * @throws ValidationException
     */
    public function store(array $data): PublicHoliday
    {
        $this->assertUniqueDate($data['observed_on']);

        return PublicHoliday::query()->create([
            'observed_on' => $data['observed_on'],
            'name' => $data['name'],
            'repeats_annually' => (bool) ($data['repeats_annually'] ?? false),
        ]);
    }

    public function show(PublicHoliday $holiday): PublicHoliday
    {
        return $holiday;
    }

    /**
     * @param  array{observed_on?: string, name?: string, repeats_annually?: bool}  $data
     *
     * @throws ValidationException
     */
    public function update(PublicHoliday $holiday, array $data): PublicHoliday
    {
        if (isset($data['observed_on'])) {
            $this->assertUniqueDate($data['observed_on'], $holiday->id);
        }

        $holiday->fill($data);
        $holiday->save();

        return $holiday;
    }

    public function destroy(PublicHoliday $holiday): void
    {
        $holiday->delete();
    }

    /**
     * @throws ValidationException
     */
    protected function assertUniqueDate(string $date, ?int $ignoreId = null): void
    {
        $exists = PublicHoliday::query()
            ->whereDate('observed_on', $date)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'observed_on' => ['A public holiday already exists on this date.'],
            ]);
        }
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
