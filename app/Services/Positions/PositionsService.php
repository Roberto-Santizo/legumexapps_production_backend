<?php

namespace App\Services\Positions;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Imports\PositionsImport;
use App\Interfaces\Positions\PositionsServiceInterface;
use App\Models\Activity;
use App\Models\Line;
use App\Models\LineSku;
use App\Models\LineSkuActivity;
use App\Models\Position;
use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Override;

class PositionsService implements PositionsServiceInterface
{
    #[Override]
    public function createPosition(array $data)
    {
        $this->ensureActivityAssignedToLineSku($data['line_sku_id'], $data['activity_id']);

        $position = Position::create($data);

        return $position;
    }

    #[Override]
    public function getPositions(?string $limit, Request $request)
    {
        $query = Position::query()->with(['lineSku.line', 'lineSku.sku', 'activity']);

        if ($request->query('lineCode')) {
            $query->whereHas('lineSku.line', function ($p0) use ($request) {
                $p0->where('code', '=', $request->query('lineCode'));
            });
        }

        if ($request->query('line')) {
            $query->whereHas('lineSku.line', function ($p0) use ($request) {
                $p0->where('name', 'LIKE', '%'.$request->query('line').'%');
            });
        }

        if ($request->query('sku')) {
            $query->whereHas('lineSku.sku', function ($p0) use ($request) {
                $p0->where('code', 'LIKE', '%'.$request->query('sku').'%');
            });
        }

        if ($request->filled('line_sku_id')) {
            $query->where('line_sku_id', $request->query('line_sku_id'));
        }

        if ($request->query('code')) {
            $query->where('code', 'LIKE', '%'.$request->query('code').'%');
        }

        if ($request->query('activity')) {
            $query->whereHas('activity', fn ($q) => $q->where('name', 'LIKE', '%'.mb_strtoupper($request->query('activity')).'%'));
        }

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getPositionById(string $id)
    {
        $position = Position::find($id);
        if (! $position) {
            throw new NotFoundError('El puesto no existe');
        }

        return $position;
    }

    #[Override]
    public function updatePositionById(array $data, string $id)
    {
        $position = $this->getPositionById($id);
        $this->ensureActivityAssignedToLineSku($data['line_sku_id'], $data['activity_id']);
        $position->update($data);

        return true;
    }

    /**
     * A position's activity must be assigned to its line SKU in line_sku_activities.
     *
     * @throws BadRequestError
     */
    private function ensureActivityAssignedToLineSku(int $lineSkuId, int $activityId): void
    {
        $isAssigned = LineSkuActivity::where('line_sku_id', $lineSkuId)
            ->where('activity_id', $activityId)
            ->exists();

        if ($isAssigned) {
            return;
        }

        $activityName = Activity::whereKey($activityId)->value('name');
        $lineSku = LineSku::with(['line', 'sku'])->find($lineSkuId);

        throw new BadRequestError("La actividad '{$activityName}' no está asignada al SKU '{$lineSku->sku->code}' de la línea '{$lineSku->line->code}'");
    }

    #[Override]
    public function deletePositionById(string $id)
    {
        $position = $this->getPositionById($id);
        $position->delete();

        return true;
    }

    #[Override]
    public function uploadFile(mixed $file)
    {
        $rows = Excel::toCollection(new PositionsImport, $file)->first() ?? collect();

        if ($rows->isEmpty()) {
            throw new BadRequestError('El archivo no contiene filas');
        }

        $lineIdsByCode = Line::pluck('id', 'code');
        $skuIdsByCode = Sku::pluck('id', 'code');
        $lineSkuIdsByPair = LineSku::get(['id', 'line_id', 'sku_id'])
            ->mapWithKeys(fn (LineSku $lineSku): array => ["{$lineSku->line_id}|{$lineSku->sku_id}" => $lineSku->id]);
        $activityIdsByName = Activity::pluck('id', 'name');
        $assignedLineSkuActivities = LineSkuActivity::get(['line_sku_id', 'activity_id'])
            ->mapWithKeys(fn (LineSkuActivity $lineSkuActivity): array => ["{$lineSkuActivity->line_sku_id}|{$lineSkuActivity->activity_id}" => true]);
        $existingCodes = Position::get(['line_sku_id', 'code'])
            ->mapWithKeys(fn (Position $position): array => ["{$position->line_sku_id}|{$position->code}" => true]);

        $now = now();
        $errors = [];
        $codesInFile = [];
        $positionsToCreate = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $rowErrors = [];

            $code = trim((string) ($row['codigo'] ?? ''));
            $activity = mb_strtoupper(trim((string) ($row['actividad'] ?? '')));
            $lineCode = trim((string) ($row['linea'] ?? ''));
            $skuCode = trim((string) ($row['sku'] ?? ''));

            $lineId = $lineIdsByCode->get($lineCode);
            $skuId = $skuIdsByCode->get($skuCode);
            $lineSkuId = ($lineId && $skuId) ? $lineSkuIdsByPair->get("{$lineId}|{$skuId}") : null;

            if ($code === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'codigo' es obligatorio";
            } elseif ($lineSkuId && $existingCodes->has("{$lineSkuId}|{$code}")) {
                $rowErrors[] = "Línea {$lineNumber}: el código '{$code}' ya existe en el sku '{$skuCode}' de la línea '{$lineCode}'";
            } elseif ($lineSkuId && isset($codesInFile["{$lineSkuId}|{$code}"])) {
                $rowErrors[] = "Línea {$lineNumber}: el código '{$code}' está repetido en el archivo para el sku '{$skuCode}' de la línea '{$lineCode}'";
            }

            if ($code !== '' && $lineSkuId) {
                $codesInFile["{$lineSkuId}|{$code}"] = true;
            }

            $activityId = $activityIdsByName->get($activity);

            if ($activity === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'actividad' es obligatorio";
            } elseif (! $activityId) {
                $rowErrors[] = "Línea {$lineNumber}: la actividad '{$activity}' no existe";
            }

            if ($lineCode === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'linea' es obligatorio";
            } elseif (! $lineId) {
                $rowErrors[] = "Línea {$lineNumber}: la línea con código '{$lineCode}' no existe";
            }

            if ($skuCode === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'sku' es obligatorio";
            } elseif (! $skuId) {
                $rowErrors[] = "Línea {$lineNumber}: el sku con código '{$skuCode}' no existe";
            } elseif ($lineId && ! $lineSkuId) {
                $rowErrors[] = "Línea {$lineNumber}: el sku '{$skuCode}' no está asignado a la línea '{$lineCode}'";
            }

            if ($lineSkuId && $activityId && ! $assignedLineSkuActivities->has("{$lineSkuId}|{$activityId}")) {
                $rowErrors[] = "Línea {$lineNumber}: la actividad '{$activity}' no está asignada al sku '{$skuCode}' de la línea '{$lineCode}'";
            }

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $positionsToCreate[] = [
                'code' => $code,
                'activity_id' => $activityId,
                'line_sku_id' => $lineSkuId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        DB::transaction(function () use ($positionsToCreate) {
            Position::insert($positionsToCreate);
        });

        return ['created' => count($positionsToCreate)];
    }
}
