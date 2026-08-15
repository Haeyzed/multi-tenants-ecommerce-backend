<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Tenant\Analytics\DateRangePreset;
use Carbon\CarbonImmutable;

/**
 * Inclusive reporting window resolved in the application timezone.
 */
final class DateRange
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly DateRangePreset $preset,
    ) {}

    /**
     * Build a range from request parameters.
     *
     * `custom` uses the supplied from/to dates; any other preset ignores them.
     * Missing bounds on a custom range fall back to the last 30 days.
     *
     * @param  array{period?: string|null, from?: string|null, to?: string|null}  $params
     */
    public static function fromParams(array $params = []): self
    {
        $preset = DateRangePreset::tryFrom((string) ($params['period'] ?? '')) ?? DateRangePreset::Last30Days;
        $timezone = self::timezone();
        $now = CarbonImmutable::now($timezone);

        if ($preset === DateRangePreset::Custom) {
            $from = filled($params['from'] ?? null)
                ? CarbonImmutable::parse((string) $params['from'], $timezone)->startOfDay()
                : null;
            $to = filled($params['to'] ?? null)
                ? CarbonImmutable::parse((string) $params['to'], $timezone)->endOfDay()
                : null;

            if ($from !== null && $to !== null) {
                return $from->greaterThan($to)
                    ? new self($to->startOfDay(), $from->endOfDay(), $preset)
                    : new self($from, $to, $preset);
            }

            if ($from !== null) {
                return new self($from, $now->endOfDay(), $preset);
            }

            if ($to !== null) {
                return new self($to->subDays(29)->startOfDay(), $to, $preset);
            }

            $preset = DateRangePreset::Last30Days;
        }

        [$from, $to] = match ($preset) {
            DateRangePreset::Today => [$now->startOfDay(), $now->endOfDay()],
            DateRangePreset::Yesterday => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            DateRangePreset::Last7Days => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            DateRangePreset::ThisMonth => [$now->startOfMonth(), $now->endOfMonth()],
            DateRangePreset::LastMonth => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };

        return new self($from, $to, $preset);
    }

    /**
     * Range covering the same duration immediately before this one.
     */
    public function previous(): self
    {
        $length = $this->from->diffInSeconds($this->to);

        return new self(
            $this->from->subSeconds($length + 1),
            $this->from->subSecond(),
            $this->preset,
        );
    }

    /**
     * Bounds formatted for query bindings.
     *
     * @return array{0: string, 1: string}
     */
    public function bounds(): array
    {
        return [
            $this->from->toDateTimeString(),
            $this->to->toDateTimeString(),
        ];
    }

    /**
     * @return array{period: string, from: string, to: string, timezone: string}
     */
    public function toArray(): array
    {
        return [
            'period' => $this->preset->value,
            'from' => $this->from->toDateTimeString(),
            'to' => $this->to->toDateTimeString(),
            'timezone' => self::timezone(),
        ];
    }

    private static function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }
}
