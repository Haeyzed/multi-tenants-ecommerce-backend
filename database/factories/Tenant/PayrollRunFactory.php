<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Models\Tenant\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    /**
     * @var class-string<PayrollRun>
     */
    protected $model = PayrollRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'reference' => 'PAY-'.now()->format('Ym').'-'.fake()->unique()->numerify('###'),
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'status' => PayrollRunStatus::Draft,
            'currency' => 'NGN',
            'gross_total' => '0.00',
            'deduction_total' => '0.00',
            'net_total' => '0.00',
            'employee_count' => 0,
        ];
    }

    /**
     * Processed payroll run.
     */
    public function processed(): static
    {
        return $this->state(fn (): array => [
            'status' => PayrollRunStatus::Processed,
            'processed_at' => now(),
        ]);
    }

    /**
     * Paid payroll run.
     */
    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => PayrollRunStatus::Paid,
            'processed_at' => now()->subDay(),
            'paid_at' => now(),
        ]);
    }
}
