<?php

namespace App\Services\PackingMaterials;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Imports\PackingMaterialsImport;
use App\Interfaces\PackingMaterials\PackingMaterialsServiceInterface;
use App\Models\PackingMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Override;

class PackingMaterialsService implements PackingMaterialsServiceInterface
{
    #[Override]
    public function createPackingMaterial(array $data)
    {
        $newPackingMaterial = PackingMaterial::create($data);

        return $newPackingMaterial;
    }

    #[Override]
    public function getPackingMaterials(?string $limit, Request $request)
    {
        $query = PackingMaterial::query();

        if ($request->query('code')) {
            $query->where('code', 'LIKE', '%'.$request->query('code').'%');
        }

        if ($request->query('name')) {
            $query->where('name', 'LIKE', '%'.$request->query('name').'%');
        }

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getPackingMaterialById(string $id)
    {
        $packingMaterial = PackingMaterial::find($id, ['*']);
        if (! $packingMaterial) {
            throw new NotFoundError('El material de empaque no existe');
        }

        return $packingMaterial;
    }

    #[Override]
    public function getPackingMaterialByCode(string $code)
    {
        $packingMaterial = PackingMaterial::where('code', '=', $code)->first();
        if (! $packingMaterial) {
            throw new NotFoundError('El material de empaque no existe');
        }

        return $packingMaterial;
    }

    #[Override]
    public function updatePackingMaterialById(string $id, array $data)
    {
        $packingMaterial = $this->getPackingMaterialByCode($id);
        $packingMaterial->update($data);

        return true;
    }

    #[Override]
    public function deletePackingMaterialById(string $id)
    {
        $packingMaterial = $this->getPackingMaterialByCode($id);
        $packingMaterial->delete();

        return true;
    }

    #[Override]
    public function uploadFile(mixed $file)
    {
        $rows = Excel::toCollection(new PackingMaterialsImport, $file)->first() ?? collect();

        if ($rows->isEmpty()) {
            throw new BadRequestError('El archivo no contiene filas');
        }

        $existingCodes = PackingMaterial::pluck('code')->flip();

        $now = now();
        $errors = [];
        $codesInFile = [];
        $packingMaterialsToCreate = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $rowErrors = [];

            $code = trim((string) ($row['codigo'] ?? ''));
            $name = trim((string) ($row['nombre'] ?? ''));
            $description = trim((string) ($row['descripcion'] ?? ''));

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

            if ($name === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'nombre' es obligatorio";
            }

            if ($description === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'descripcion' es obligatorio";
            }

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $packingMaterialsToCreate[] = [
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        DB::transaction(function () use ($packingMaterialsToCreate) {
            PackingMaterial::insert($packingMaterialsToCreate);
        });

        return ['created' => count($packingMaterialsToCreate)];
    }
}
