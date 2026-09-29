<?php

namespace App\Services\Lines;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Imports\LinesImport;
use App\Interfaces\Lines\LinesServiceInterface;
use App\Models\Line;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Override;

class LinesService implements LinesServiceInterface
{
    #[Override]
    public function createLine(array $data)
    {
        $newLine = Line::create($data);

        return $newLine;
    }

    #[Override]
    public function getLines(Request $request, ?string $limit)
    {
        $query = Line::query();
        $skuId = $request->query('skuId');

        if($skuId){
            $query->whereHas('performances', function ($p0) use($skuId) {
                $p0->where('sku_id', $skuId);
            });
        }

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getLineById(string $id)
    {
        throw new \Exception('Not implemented');
    }

    #[Override]
    public function getLineByCode(string $code)
    {
        $line = Line::where('code', '=', $code)->first();
        if (! $line) {
            throw new NotFoundError('La línea no existe');
        }

        return $line;
    }

    #[Override]
    public function updateLineById(string $id, array $data)
    {
        $line = $this->getLineByCode($id);
        $line->update($data);
        $line->save();

        return true;
    }

    #[Override]
    public function deleteLineById(string $id)
    {
        $line = $this->getLineByCode($id);
        $line->delete();

        return true;
    }

    #[Override]
    public function uploadFile(mixed $file)
    {
        $rows = Excel::toCollection(new LinesImport, $file)->first() ?? collect();

        if ($rows->isEmpty()) {
            throw new BadRequestError('El archivo no contiene filas');
        }

        $existingCodes = Line::pluck('code')->flip();

        $now = now();
        $errors = [];
        $codesInFile = [];
        $linesToCreate = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $rowErrors = [];

            $name = trim((string) ($row['nombre'] ?? ''));
            $code = trim((string) ($row['codigo'] ?? ''));
            $shift = trim((string) ($row['turno'] ?? ''));

            if ($name === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'nombre' es obligatorio";
            }

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

            if ($shift === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'turno' es obligatorio";
            } elseif (! is_numeric($shift)) {
                $rowErrors[] = "Línea {$lineNumber}: 'turno' debe ser numérico";
            }

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $linesToCreate[] = [
                'name' => $name,
                'code' => $code,
                'shift' => $shift,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        DB::transaction(function () use ($linesToCreate) {
            Line::insert($linesToCreate);
        });

        return ['created' => count($linesToCreate)];
    }
}
