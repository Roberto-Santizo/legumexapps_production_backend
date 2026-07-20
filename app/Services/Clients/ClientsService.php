<?php

namespace App\Services\Clients;

use App\Errors\NotFoundError;
use App\Interfaces\Clients\ClientsServiceInterface;
use App\Models\Client;
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
}
