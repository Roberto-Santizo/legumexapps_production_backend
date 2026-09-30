<?php

namespace App\Services\Timeouts;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Imports\TimeoutsImport;
use App\Interfaces\Timeouts\TimeoutsServiceInterface;
use App\Models\Timeout;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Override;

class TimeoutsService implements TimeoutsServiceInterface
{
    #[Override]
    public function createTimeout(array $data)
    {
        $newTimeout = Timeout::create($data);

        return $newTimeout;
    }

    #[Override]
    public function getTimeouts(?string $limit)
    {
        $query = Timeout::query();

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getTimeoutById(string $id)
    {
        $timeout = Timeout::find($id, ['*']);
        if (! $timeout) {
            throw new NotFoundError('El tiempo muerto no existe');
        }

        return $timeout;
    }

    #[Override]
    public function updateTimeoutById(string $id, array $data)
    {
        $timeout = $this->getTimeoutById($id);
        $timeout->update($data);

        return true;
    }

    #[Override]
    public function deleteTimeoutById(string $id)
    {
        $timeout = $this->getTimeoutById($id);
        $timeout->delete();

        return true;
    }

    #[Override]
    public function uploadFile(mixed $file)
    {
        $rows = Excel::toCollection(new TimeoutsImport, $file)->first() ?? collect();

        if ($rows->isEmpty()) {
            throw new BadRequestError('El archivo no contiene filas');
        }

        $existingNames = Timeout::pluck('name')->map(fn (string $name) => mb_strtolower(trim($name)))->flip();

        $now = now();
        $errors = [];
        $namesInFile = [];
        $timeoutsToCreate = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $rowErrors = [];

            $name = trim((string) ($row['nombre'] ?? ''));
            $normalizedName = mb_strtolower($name);

            if ($name === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'nombre' es obligatorio";
            } elseif ($existingNames->has($normalizedName)) {
                $rowErrors[] = "Línea {$lineNumber}: el nombre '{$name}' ya existe";
            } elseif (isset($namesInFile[$normalizedName])) {
                $rowErrors[] = "Línea {$lineNumber}: el nombre '{$name}' está repetido en el archivo";
            }

            if ($name !== '') {
                $namesInFile[$normalizedName] = true;
            }

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $timeoutsToCreate[] = [
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        DB::transaction(function () use ($timeoutsToCreate) {
            Timeout::insert($timeoutsToCreate);
        });

        return ['created' => count($timeoutsToCreate)];
    }
}
