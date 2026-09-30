<?php

namespace App\Services\RawMaterials;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Imports\RawMaterialsImport;
use App\Interfaces\RawMaterials\RawMaterialsServiceInterface;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Override;

class RawMaterialsService implements RawMaterialsServiceInterface
{
    #[Override]
    public function createRawMaterial(array $data)
    {
        $newRawMaterial = RawMaterial::create($data);

        return $newRawMaterial;
    }

    #[Override]
    public function getRawMaterials(?string $limit, Request $request)
    {
        $query = RawMaterial::query();

        if ($request->query('code')) {
            $query->where('code', 'LIKE', '%'.$request->query('code').'%');
        }

        if ($request->query('product_name')) {
            $query->where('product_name', 'LIKE', '%'.$request->query('product_name').'%');
        }

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getRawMaterialById(string $id)
    {
        $rawMaterial = RawMaterial::find($id, ['*']);
        if (! $rawMaterial) {
            throw new NotFoundError('La materia prima no existe');
        }

        return $rawMaterial;
    }

    #[Override]
    public function getRawMaterialByCode(string $code)
    {
        $rawMaterial = RawMaterial::where('code', '=', $code)->first();
        if (! $rawMaterial) {
            throw new NotFoundError('La materia prima no existe');
        }

        return $rawMaterial;
    }

    #[Override]
    public function updateRawMaterialById(string $id, array $data)
    {
        $rawMaterial = $this->getRawMaterialByCode($id);
        $rawMaterial->update($data);

        return true;
    }

    #[Override]
    public function deleteRawMaterialById(string $id)
    {
        $rawMaterial = $this->getRawMaterialByCode($id);
        $rawMaterial->delete();

        return true;
    }

    #[Override]
    public function uploadFile(mixed $file)
    {
        $rows = Excel::toCollection(new RawMaterialsImport, $file)->first() ?? collect();

        if ($rows->isEmpty()) {
            throw new BadRequestError('El archivo no contiene filas');
        }

        $existingCodes = RawMaterial::pluck('code')->flip();

        $now = now();
        $errors = [];
        $codesInFile = [];
        $rawMaterialsToCreate = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $rowErrors = [];

            $code = trim((string) ($row['codigo'] ?? ''));
            $productName = trim((string) ($row['nombre_producto'] ?? ''));

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

            if ($productName === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'nombre_producto' es obligatorio";
            }

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $rawMaterialsToCreate[] = [
                'code' => $code,
                'product_name' => $productName,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        DB::transaction(function () use ($rawMaterialsToCreate) {
            RawMaterial::insert($rawMaterialsToCreate);
        });

        return ['created' => count($rawMaterialsToCreate)];
    }
}
