<?php

namespace App\Services\Clients;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Imports\ClientsImport;
use App\Interfaces\Clients\ClientsServiceInterface;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Override;

class ClientsService implements ClientsServiceInterface
{
    #[Override]
    public function createClient(array $data)
    {
        $newClient = Client::create($data);
        return $newClient;
    }

    #[Override]
    public function getClients(?string $limit)
    {
        $query = Client::query();

        if($limit) return $query->paginate($limit);
        

        return $query->get();
    }

    #[Override]
    public function getClientById(string $id)
    {
        $client = Client::find($id, ['*']);
        if(!$client) throw new NotFoundError("El cliente no existe");
        return $client;
    }

    #[Override]
    public function updateClientById(string $id, array $data)
    {
        $client = $this->getClientById($id);
        $client->update($data);
        return true;
    }

    #[Override]
    public function deleteClientById(string $id)
    {
        $client = $this->getClientById($id);
        $client->delete();
        return true;
    }

    #[Override]
    public function uploadFile(mixed $file)
    {
        $rows = Excel::toCollection(new ClientsImport, $file)->first() ?? collect();

        if ($rows->isEmpty()) {
            throw new BadRequestError('El archivo no contiene filas');
        }

        $existingNames = Client::pluck('name')->map(fn (string $name) => mb_strtolower(trim($name)))->flip();

        $now = now();
        $errors = [];
        $namesInFile = [];
        $clientsToCreate = [];

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

            $clientsToCreate[] = [
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        DB::transaction(function () use ($clientsToCreate) {
            Client::insert($clientsToCreate);
        });

        return ['created' => count($clientsToCreate)];
    }
}
