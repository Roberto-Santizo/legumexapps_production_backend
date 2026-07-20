<?php

namespace App\Interfaces\Clients;

interface ClientsServiceInterface
{
    public function createClient(array $data);

    public function getClients(?string $limit);

    public function getClientById(string $id);

    public function updateClientById(string $id, array $data);

    public function deleteClientById(string $id);
}
