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
        'positions',
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
            $this->seedLineFields();
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
     * Line fields reference their capture field by capture type and key, since capture field ids are not stable.
     */
    private function seedLineFields(): void
    {
        $captureFieldIds = CaptureField::query()
            ->get(['id', 'capture_type', 'key'])
            ->mapWithKeys(fn (CaptureField $field): array => [$this->captureFieldKey($field->capture_type?->value, $field->key) => $field->id]);

        $now = now();

        $rows = array_map(fn (array $field): array => [
            'line_id' => $field['line_id'],
            'capture_field_id' => $captureFieldIds[$this->captureFieldKey($field['capture_type'], $field['key'])],
            'is_required' => $field['is_required'],
            'order' => $field['order'],
            'label' => $field['label'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->rows('line_fields'));

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            DB::table('line_fields')->upsert($chunk, ['line_id', 'capture_field_id'], ['is_required', 'order', 'label', 'updated_at']);
        }
    }

    private function captureFieldKey(?string $captureType, string $key): string
    {
        return ($captureType ?? '*').'|'.$key;
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
