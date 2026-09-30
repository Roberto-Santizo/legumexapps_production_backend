<?php

namespace App\Interfaces\Clients;

use Illuminate\Http\Request;

interface ClientsServiceInterface
{
    public function createClient(array $data);

    public function getClients(?string $limit, Request $request);

    public function getClientById(string $id);

    public function updateClientById(string $id, array $data);

    public function deleteClientById(string $id);

    public function uploadFile(mixed $file);
}
