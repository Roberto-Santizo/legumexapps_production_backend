<?php

namespace Database\Factories;

use App\Models\Line;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Line>
 */
class LineFactory extends Factory
{
    protected $model = Line::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->bothify('LN-###'),
            'shift' => fake()->numberBetween(1, 3),
        ];
    }
}
