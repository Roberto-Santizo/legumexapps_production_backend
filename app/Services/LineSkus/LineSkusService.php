<?php

namespace App\Services\LineSkus;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Imports\LineSkusImport;
use App\Interfaces\LineSkus\LineSkusServiceInterface;
use App\Models\Line;
use App\Models\LineSku;
use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Override;

class LineSkusService implements LineSkusServiceInterface
{
    #[Override]
    public function createLineSku(array $data)
    {
        $newLineSku = LineSku::create($data);

        return $newLineSku;
    }

    #[Override]
    public function getLineSkus(?string $limit, Request $request)
    {
        $query = LineSku::query();

        if ($request->query('sku')) {
            $query->whereHas('sku', function ($p0) use ($request) {
                $p0->where('code', 'LIKE', '%'.$request->query('sku').'%');
            });
        }

        if ($request->query('line_id')) {
            $query->where('line_id', $request->query('line_id'));
        }

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getLineSkuById(string $id)
    {
        $lineSku = LineSku::find($id, ['*']);
        if (! $lineSku) {
            throw new NotFoundError('El SKU de línea no existe');
        }

        return $lineSku;
    }

    #[Override]
    public function updateLineSkuById(string $id, array $data)
    {
        $lineSku = $this->getLineSkuById($id);
        $lineSku->update($data);

        return true;
    }

    #[Override]
    public function deleteLineSkuById(string $id)
    {
        $lineSku = $this->getLineSkuById($id);
        $lineSku->delete();

        return true;
    }

    #[Override]
    public function uploadFile(mixed $file)
    {
        $rows = Excel::toCollection(new LineSkusImport, $file)->first() ?? collect();

        if ($rows->isEmpty()) {
            throw new BadRequestError('El archivo no contiene filas');
        }

        $skuIdsByCode = Sku::pluck('id', 'code');
        $lineIdsByCode = Line::pluck('id', 'code');
        $existingPairs = LineSku::get(['sku_id', 'line_id'])->mapWithKeys(fn (LineSku $lineSku) => ["{$lineSku->sku_id}-{$lineSku->line_id}" => true]);

        $paymentMethodValues = ['1' => true, 'SI' => true, 'SÍ' => true, '0' => false, 'NO' => false];

        $now = now();
        $errors = [];
        $pairsInFile = [];
        $lineSkusToCreate = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $rowErrors = [];

            $skuCode = trim((string) ($row['sku'] ?? ''));
            $lineCode = trim((string) ($row['linea'] ?? ''));
            $lbsPerformance = trim((string) ($row['rendimiento_lbs'] ?? ''));
            $acceptedPercentage = trim((string) ($row['porcentaje_aceptado'] ?? ''));
            $paymentMethod = mb_strtoupper(trim((string) ($row['metodo_pago'] ?? '')));

            $skuId = $skuIdsByCode->get($skuCode);
            $lineId = $lineIdsByCode->get($lineCode);

            if ($skuCode === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'sku' es obligatorio";
            } elseif (! $skuId) {
                $rowErrors[] = "Línea {$lineNumber}: el sku con código '{$skuCode}' no existe";
            }

            if ($lineCode === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'linea' es obligatorio";
            } elseif (! $lineId) {
                $rowErrors[] = "Línea {$lineNumber}: la línea con código '{$lineCode}' no existe";
            }

            if ($skuId && $lineId) {
                $pairKey = "{$skuId}-{$lineId}";

                if ($existingPairs->has($pairKey) || isset($pairsInFile[$pairKey])) {
                    $rowErrors[] = "Línea {$lineNumber}: el sku '{$skuCode}' ya está asignado a la línea '{$lineCode}'";
                }

                $pairsInFile[$pairKey] = true;
            }

            if ($lbsPerformance === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'rendimiento_lbs' es obligatorio";
            } elseif (! is_numeric($lbsPerformance)) {
                $rowErrors[] = "Línea {$lineNumber}: 'rendimiento_lbs' debe ser numérico";
            }

            if ($acceptedPercentage === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'porcentaje_aceptado' es obligatorio";
            } elseif (! is_numeric($acceptedPercentage)) {
                $rowErrors[] = "Línea {$lineNumber}: 'porcentaje_aceptado' debe ser numérico";
            }

            if ($paymentMethod === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'metodo_pago' es obligatorio";
            } elseif (! array_key_exists($paymentMethod, $paymentMethodValues)) {
                $rowErrors[] = "Línea {$lineNumber}: 'metodo_pago' debe ser 1, 0, SI o NO";
            }

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $lineSkusToCreate[] = [
                'sku_id' => $skuId,
                'line_id' => $lineId,
                'lbs_performance' => $lbsPerformance,
                'accepted_percentage' => $acceptedPercentage,
                'payment_method' => $paymentMethodValues[$paymentMethod],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        DB::transaction(function () use ($lineSkusToCreate) {
            LineSku::insert($lineSkusToCreate);
        });

        return ['created' => count($lineSkusToCreate)];
    }
}
