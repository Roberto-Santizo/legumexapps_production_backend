<?php

namespace Database\Factories;

use App\Models\Line;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('POS-###'),
            'activity' => fake()->words(3, true),
            'line_id' => Line::factory(),
            'status' => fake()->boolean(),
        ];
    }
}
