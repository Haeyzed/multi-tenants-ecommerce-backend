<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Analytics;

use App\Enums\Tenant\Analytics\DateRangePreset;
use App\Enums\Tenant\Analytics\ReportInterval;
use App\Http\Requests\BaseRequest;
use App\Support\DateRange;
use Illuminate\Validation\Rule;

/**
 * Shared validation for analytics reporting endpoints.
 */
class AnalyticsReportRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'nullable', Rule::enum(DateRangePreset::class)],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'group_by' => ['sometimes', 'nullable', Rule::enum(ReportInterval::class)],
            'seller_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'export' => ['sometimes', 'nullable', 'string', 'in:csv'],
        ];
    }

    /**
     * Resolve the reporting window in the application timezone.
     */
    public function dateRange(): DateRange
    {
        /** @var array{period?: string|null, from?: string|null, to?: string|null} $params */
        $params = $this->only(['period', 'from', 'to']);

        return DateRange::fromParams($params);
    }

    /**
     * Bucket size for time-series reports.
     *
     * Named `reportInterval` because `Request::interval()` is already taken.
     */
    public function reportInterval(): ReportInterval
    {
        return ReportInterval::tryFrom((string) $this->input('group_by')) ?? ReportInterval::Day;
    }

    /**
     * Whether the caller asked for a CSV download.
     */
    public function wantsCsv(): bool
    {
        return $this->input('export') === 'csv';
    }

    /**
     * Row cap for "top N" reports.
     */
    public function limit(int $default = 10): int
    {
        return max(1, min((int) ($this->input('limit') ?? $default), 100));
    }
}
