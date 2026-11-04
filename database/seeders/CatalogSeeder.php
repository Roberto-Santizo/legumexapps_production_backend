<?php

namespace Database\Seeders;

use App\Models\CaptureField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeeder extends Seeder
{
    /**
     * Tables seeded by id, ordered so that foreign keys are always satisfied.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'clients',
        'timeouts',
        'raw_materials',
        'packing_materials',
        'stock_keeping_units',
        'sku_raw_materials',
        'sku_packing_materials',
        'lines',
        'line_stock_keeping_units',
    ];

    private const CHUNK_SIZE = 500;

    /**
     * Seed the production catalog from the JSON files in database/seeders/data/catalog.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::TABLES as $table) {
                $this->seedTable($table);
            }

            $this->seedCaptureFields();
        });
    }

    /**
     * Upsert a table keeping its original ids and move its id sequence past them.
     */
    private function seedTable(string $table): void
    {
        $now = now();

        $rows = array_map(
            fn (array $row): array => [...$row, 'created_at' => $now, 'updated_at' => $now],
            $this->rows($table)
        );

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
     * Capture fields are matched by capture type and key because CaptureFieldsSeeder may have assigned different ids.
     */
    private function seedCaptureFields(): void
    {
        foreach ($this->rows('capture_fields') as $field) {
            CaptureField::updateOrCreate(
                ['capture_type' => $field['capture_type'], 'key' => $field['key']],
                $field
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $table): array
    {
        return json_decode(
            file_get_contents(database_path("seeders/data/catalog/{$table}.json")),
            true,
            flags: JSON_THROW_ON_ERROR
        );
    }
}
