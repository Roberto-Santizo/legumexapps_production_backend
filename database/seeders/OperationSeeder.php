<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationSeeder extends Seeder
{
    /**
     * Tables seeded by id, ordered so that foreign keys are always satisfied.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'weekly_plans',
        'weekly_plan_tasks',
        'weekly_plan_task_employees',
        'weekly_plan_task_performance_records',
        'weekly_plan_task_lot_records',
        'weekly_plan_task_product_records',
        'weekly_plan_task_logs',
        'weekly_plan_task_observations',
        'weekly_plan_task_timeouts',
        'draft_weekly_plans',
        'draft_weekly_plan_tasks',
        'packing_material_transactions',
        'packing_material_transaction_items',
    ];

    private const CHUNK_SIZE = 500;

    /**
     * Seed the operational data (weekly plans, tasks and their records) from the JSON files in database/seeders/data/operation.
     * Requires the catalog and the admin user to be seeded first.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::TABLES as $table) {
                $this->seedTable($table);
            }
        });
    }

    /**
     * Upsert a table keeping its original ids and timestamps, then move its id sequence past them.
     */
    private function seedTable(string $table): void
    {
        $jsonColumns = $this->jsonColumns($table);

        $rows = array_map(function (array $row) use ($jsonColumns): array {
            foreach ($jsonColumns as $column) {
                if (isset($row[$column])) {
                    $row[$column] = json_encode($row[$column], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                }
            }

            return $row;
        }, $this->rows($table));

        if ($rows === []) {
            return;
        }

        $columnsToUpdate = array_values(array_diff(array_keys($rows[0]), ['id', 'created_at']));

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            DB::table($table)->upsert($chunk, ['id'], $columnsToUpdate);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), (SELECT MAX(id) FROM {$table}))");
        }
    }

    /**
     * @return array<int, string>
     */
    private function jsonColumns(string $table): array
    {
        return collect(Schema::getColumns($table))
            ->filter(fn (array $column): bool => in_array($column['type_name'], ['json', 'jsonb'], true))
            ->pluck('name')
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $table): array
    {
        return json_decode(
            file_get_contents(database_path("seeders/data/operation/{$table}.json")),
            true,
            flags: JSON_THROW_ON_ERROR
        );
    }
}
