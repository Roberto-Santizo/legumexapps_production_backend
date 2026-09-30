<?php

namespace App\Services\Skus;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Imports\SkusImport;
use App\Interfaces\Skus\SkusServiceInterface;
use App\Models\Client;
use App\Models\Sku;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Override;

class SkusService implements SkusServiceInterface
{
    #[Override]
    public function createSku(array $data)
    {
        $newSku = Sku::create($data);

        return $newSku;
    }

    #[Override]
    public function getSkus(?string $limit)
    {
        $query = Sku::query();

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getSkuById(string $id)
    {
        $sku = Sku::find($id, ['*']);
        if (! $sku) {
            throw new NotFoundError('El SKU no existe');
        }

        return $sku;

    }

    #[Override]
    public function getSkuByCode(string $code)
    {
        $sku = Sku::where('code', '=', $code)->first();
        if (! $sku) {
            throw new NotFoundError('El SKU no existe');
        }

        return $sku;

    }

    #[Override]
    public function updateSkuById(string $id, array $data)
    {
        $sku = $this->getSkuByCode($id);
        $sku->update($data);

        return true;
    }

    #[Override]
    public function deleteSkuById(string $id)
    {
        $sku = $this->getSkuByCode($id);
        $sku->delete();

        return true;
    }

    #[Override]
    public function uploadFile(mixed $file)
    {
        $rows = Excel::toCollection(new SkusImport, $file)->first() ?? collect();

        if ($rows->isEmpty()) {
            throw new BadRequestError('El archivo no contiene filas');
        }

        $existingCodes = Sku::pluck('code')->flip();
        $clientIdsByName = Client::get(['id', 'name'])
            ->groupBy(fn (Client $client) => mb_strtolower(trim($client->name)))
            ->map(fn ($clients) => $clients->pluck('id'));

        $now = now();
        $errors = [];
        $codesInFile = [];
        $skusToCreate = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $rowErrors = [];

            $code = trim((string) ($row['codigo'] ?? ''));
            $productName = trim((string) ($row['nombre_producto'] ?? ''));
            $presentation = trim((string) ($row['presentacion'] ?? ''));
            $boxesPerPallet = trim((string) ($row['cajas_por_pallet'] ?? ''));
            $clientName = trim((string) ($row['cliente'] ?? ''));

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

            if ($presentation !== '' && ! is_numeric($presentation)) {
                $rowErrors[] = "Línea {$lineNumber}: 'presentacion' debe ser numérico";
            }

            if ($boxesPerPallet !== '' && filter_var($boxesPerPallet, FILTER_VALIDATE_INT) === false) {
                $rowErrors[] = "Línea {$lineNumber}: 'cajas_por_pallet' debe ser un número entero";
            }

            $clientIds = $clientIdsByName->get(mb_strtolower($clientName));

            if ($clientName === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'cliente' es obligatorio";
            } elseif (! $clientIds) {
                $rowErrors[] = "Línea {$lineNumber}: el cliente '{$clientName}' no existe";
            } elseif ($clientIds->count() > 1) {
                $rowErrors[] = "Línea {$lineNumber}: hay más de un cliente con el nombre '{$clientName}'";
            }

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $skusToCreate[] = [
                'code' => $code,
                'product_name' => $productName,
                'presentation' => $presentation === '' ? null : $presentation,
                'boxes_per_pallet' => $boxesPerPallet === '' ? null : (int) $boxesPerPallet,
                'client_id' => $clientIds->first(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        DB::transaction(function () use ($skusToCreate) {
            Sku::insert($skusToCreate);
        });

        return ['created' => count($skusToCreate)];
    }
}
