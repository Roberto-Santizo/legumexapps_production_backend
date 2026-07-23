<?php

namespace Database\Factories;

use App\Models\WeeklyPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyPlan>
 */
class WeeklyPlanFactory extends Factory
{
    protected $model = WeeklyPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'week' => fake()->numberBetween(1, 52),
            'year' => fake()->numberBetween(2024, 2030),
        ];
    }
}
