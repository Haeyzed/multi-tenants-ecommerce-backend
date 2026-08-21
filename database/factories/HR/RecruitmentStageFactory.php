<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Models\HR\RecruitmentStage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RecruitmentStage>
 */
class RecruitmentStageFactory extends Factory
{
    /**
     * @var class-string<RecruitmentStage>
     */
    protected $model = RecruitmentStage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'kind' => JobApplicationStatus::Screening,
            'sort_order' => fake()->numberBetween(1, 20),
            'is_default' => false,
            'is_terminal' => false,
        ];
    }
}
