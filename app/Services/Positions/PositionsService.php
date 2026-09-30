<?php

namespace App\Services\Positions;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Imports\PositionsImport;
use App\Interfaces\Positions\PositionsServiceInterface;
use App\Models\Line;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Override;

class PositionsService implements PositionsServiceInterface
{
    #[Override]
    public function createPosition(array $data)
    {
        $position = Position::create($data);

        return $position;
    }

    #[Override]
    public function getPositions(?string $limit, Request $request)
    {
        $query = Position::query();

        if ($request->query('lineCode')) {
            $query->whereHas('line', function ($p0) use ($request) {
                $p0->where('code', '=', $request->query('lineCode'));
            });
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
        $position->update($data);

        return true;
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

        $existingCodes = Position::pluck('code')->flip();
        $lineIdsByCode = Line::pluck('id', 'code');

        $now = now();
        $errors = [];
        $codesInFile = [];
        $positionsToCreate = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $rowErrors = [];

            $code = trim((string) ($row['codigo'] ?? ''));
            $activity = trim((string) ($row['actividad'] ?? ''));
            $lineCode = trim((string) ($row['linea'] ?? ''));

            if ($code === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'codigo' es obligatorio";
            } elseif ($existingCodes->has($code)) {
                $rowErrors[] = "Línea {$lineNumber}: el código '{$code}' ya existe";
            } elseif (isset($codesInFile[$code])) {
                $rowErrors[] = "Línea {$lineNumber}: el código '{$code}' está repetido en el archivo";
            }

            if ($code !== '') {
                $codesInFile[$code] = true;
            }

            if ($activity === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'actividad' es obligatorio";
            }

            $lineId = $lineIdsByCode->get($lineCode);

            if ($lineCode === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'linea' es obligatorio";
            } elseif (! $lineId) {
                $rowErrors[] = "Línea {$lineNumber}: la línea con código '{$lineCode}' no existe";
            }

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $positionsToCreate[] = [
                'code' => $code,
                'activity' => $activity,
                'line_id' => $lineId,
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
