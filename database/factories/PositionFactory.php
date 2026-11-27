<?php

namespace Database\Factories;

use App\Models\LineSku;
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
            'line_sku_id' => fn () => LineSku::query()->inRandomOrder()->value('id'),
            'status' => fake()->boolean(),
        ];
    }
}
