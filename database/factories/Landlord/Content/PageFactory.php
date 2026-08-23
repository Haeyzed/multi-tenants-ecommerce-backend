<?php

declare(strict_types=1);

namespace Database\Factories\Landlord\Content;

use App\Enums\Content\ContentStatus;
use App\Models\Landlord\Content\Page;
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
            'status' => ContentStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ContentStatus::Published,
            'published_at' => now()->subHour(),
        ]);
    }
}
