<?php

declare(strict_types=1);

namespace Database\Factories\Landlord\Cms;

use App\Enums\Cms\CmsContentStatus;
use App\Models\Landlord\Cms\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'excerpt' => fake()->optional()->sentence(),
            'content' => fake()->optional()->paragraphs(2, true),
            'status' => CmsContentStatus::Draft,
            'published_at' => null,
            'author_id' => null,
            'blog_category_id' => null,
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
