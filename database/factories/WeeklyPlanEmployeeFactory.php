<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Position;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyPlanEmployee>
 */
class WeeklyPlanEmployeeFactory extends Factory
{
    protected $model = WeeklyPlanEmployee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position_id' => Position::factory(),
            'employee_id' => Employee::factory(),
            'weekly_plan_id' => WeeklyPlan::factory(),
        ];
    }
}
