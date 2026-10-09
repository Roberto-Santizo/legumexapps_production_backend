<?php

namespace Database\Seeders;

use App\Enums\CaptureFieldDataType;
use App\Enums\CaptureType;
use App\Models\CaptureField;
use App\Models\LineField;
use Illuminate\Database\Seeder;

class CaptureFieldsSeeder extends Seeder
{
    /**
     * Seed the system capture fields of every capture type and make the line fields of calculated fields optional.
     */
    public function run(): void
    {
        foreach ($this->fields() as $field) {
            CaptureField::updateOrCreate(
                ['capture_type' => $field['capture_type'], 'key' => $field['key']],
                [
                    'label' => $field['label'],
                    'data_type' => $field['data_type'],
                    'is_system' => true,
                    'is_calculated' => $field['depends_on'] !== null,
                    'depends_on' => $field['depends_on'],
                    'options' => $field['options'],
                ]
            );
        }

        LineField::query()
            ->where('is_required', true)
            ->whereHas('captureField', fn ($query) => $query->where('is_calculated', true))
            ->update(['is_required' => false]);
    }

    /**
     * @return array<int, array{capture_type: ?CaptureType, key: string, label: string, data_type: CaptureFieldDataType, depends_on: ?array<int, string>, options: ?array<int, string>}>
     */
    private function fields(): array
    {
        return [
            $this->field(null, 'observations', 'Observaciones', CaptureFieldDataType::Text),

            $this->field(CaptureType::Pallet, 'pallet_number', 'Tarima #', CaptureFieldDataType::Integer),
            $this->field(CaptureType::Pallet, 'lot', 'Lote', CaptureFieldDataType::Text),
            $this->field(CaptureType::Pallet, 'recorded_at', 'Hora', CaptureFieldDataType::Time),
            $this->field(CaptureType::Pallet, 'boxes', 'Cajas', CaptureFieldDataType::Integer),
            $this->field(CaptureType::Pallet, 'liters', 'Litros', CaptureFieldDataType::Number),
            $this->field(CaptureType::Pallet, 'status', 'Estado', CaptureFieldDataType::Select, options: ['APROBADO', 'RECHAZADO']),
            $this->field(CaptureType::Pallet, 'ticket_weight', 'Peso boleta', CaptureFieldDataType::Number, ['boxes']),
            $this->field(CaptureType::Pallet, 'scale_weight', 'Peso báscula', CaptureFieldDataType::Number),
            $this->field(CaptureType::Pallet, 'tare', 'Tara', CaptureFieldDataType::Number),
            $this->field(CaptureType::Pallet, 'net_weight', 'Peso neto', CaptureFieldDataType::Number, ['scale_weight', 'tare']),
            $this->field(CaptureType::Pallet, 'difference', 'Diferencial', CaptureFieldDataType::Number, ['net_weight', 'ticket_weight']),

            $this->field(CaptureType::Lot, 'entry_date', 'Fecha de ingreso', CaptureFieldDataType::Date),
            $this->field(CaptureType::Lot, 'lot', 'Lote (GRN)', CaptureFieldDataType::Text),
            $this->field(CaptureType::Lot, 'recorded_at', 'Hora', CaptureFieldDataType::Time),
            $this->field(CaptureType::Lot, 'intake_lbs', 'Peso de libras al ingreso', CaptureFieldDataType::Number),
            $this->field(CaptureType::Lot, 'applied_raw_lbs', 'MP aplicada', CaptureFieldDataType::Number),
            $this->field(CaptureType::Lot, 'trimmed_lbs', 'Libras recortadas', CaptureFieldDataType::Number),
            $this->field(CaptureType::Lot, 'overripe_lbs', 'Libras sobremaduro', CaptureFieldDataType::Number),
            $this->field(CaptureType::Lot, 'recovery_pct', '% Recuperación', CaptureFieldDataType::Number, ['trimmed_lbs', 'applied_raw_lbs']),
            $this->field(CaptureType::Lot, 'overripe_pct', '% Sobremadurez', CaptureFieldDataType::Number, ['overripe_lbs', 'applied_raw_lbs']),
            $this->field(CaptureType::Lot, 'grn_balance', 'Saldo GRN', CaptureFieldDataType::Number, ['intake_lbs', 'applied_raw_lbs']),

            $this->field(CaptureType::Product, 'raw_material', 'Materia prima', CaptureFieldDataType::Text),
            $this->field(CaptureType::Product, 'raw_lbs', 'Libras materia prima', CaptureFieldDataType::Number),
            $this->field(CaptureType::Product, 'classified_lbs', 'Libras clasificadas', CaptureFieldDataType::Number),
            $this->field(CaptureType::Product, 'trimmed_lbs', 'Libras recortadas', CaptureFieldDataType::Number),
            $this->field(CaptureType::Product, 'rejected_lbs', 'Rechazo', CaptureFieldDataType::Number),
            $this->field(CaptureType::Product, 'packed_lbs', 'Empacado', CaptureFieldDataType::Number),
            $this->field(CaptureType::Product, 'recovery_pct', '% Recuperación', CaptureFieldDataType::Number, ['trimmed_lbs', 'raw_lbs']),
            $this->field(CaptureType::Product, 'rejection_pct', '% Rechazo', CaptureFieldDataType::Number, ['rejected_lbs', 'raw_lbs']),
        ];
    }

    /**
     * @param  array<int, string>|null  $dependsOn
     * @param  array<int, string>|null  $options
     * @return array{capture_type: ?CaptureType, key: string, label: string, data_type: CaptureFieldDataType, depends_on: ?array<int, string>, options: ?array<int, string>}
     */
    private function field(?CaptureType $captureType, string $key, string $label, CaptureFieldDataType $dataType, ?array $dependsOn = null, ?array $options = null): array
    {
        return [
            'capture_type' => $captureType,
            'key' => $key,
            'label' => $label,
            'data_type' => $dataType,
            'depends_on' => $dependsOn,
            'options' => $options,
        ];
    }
}
