<?php

declare(strict_types=1);

namespace Database\Factories\Landlord\Cms;

use App\Enums\Cms\CmsContentStatus;
use App\Models\Landlord\Cms\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(2),
            'content' => fake()->optional()->paragraphs(2, true),
            'status' => CmsContentStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => CmsContentStatus::Published,
            'published_at' => now()->subHour(),
        ]);
    }
}
