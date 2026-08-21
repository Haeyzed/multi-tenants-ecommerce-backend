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
     * Retrieve a paginated list of resources.
     *
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
     * Create a resource.
     *
     * @param  array{observed_on: string, name: string, repeats_annually?: bool}  $data
     * @return PublicHoliday
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

    /**
     * Retrieve a single resource.
     *
     * @param  PublicHoliday  $holiday
     * @return PublicHoliday
     */
    public function show(PublicHoliday $holiday): PublicHoliday
    {
        return $holiday;
    }

    /**
     * Update a resource.
     *
     * @param  PublicHoliday  $holiday
     * @param  array{observed_on?: string, name?: string, repeats_annually?: bool}  $data
     * @return PublicHoliday
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

    /**
     * Delete a resource.
     *
     * @param  PublicHoliday  $holiday
     * @return void
     */
    public function destroy(PublicHoliday $holiday): void
    {
        $holiday->delete();
    }

    /**
     * Assert unique date.
     *
     * @param  string  $date
     * @param  ?int  $ignoreId
     * @return void
     *
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
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
