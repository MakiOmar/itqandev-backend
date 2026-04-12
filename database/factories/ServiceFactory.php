<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'slug' => $slug,
            'content_locale' => null,
            'icon' => 'web',
            'sort_order' => 0,
            'is_published' => true,
            'name' => $this->faker->words(3, true),
            'short_description' => $this->faker->sentence(8),
            'description' => '<p>' . $this->faker->paragraph() . '</p>',
            'process' => ['Step one', 'Step two'],
            'deliverables' => ['Item A', 'Item B'],
        ];
    }
}
