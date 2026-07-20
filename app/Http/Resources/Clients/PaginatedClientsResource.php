<?php

namespace App\Http\Resources\Clients;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaginatedClientsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $clients = ClientResource::collection($this->items());

        return [
            'data' =>               $clients,
            'total' =>              $this->total(),
            'currentPage' =>        $this->currentPage(),
            'lastPage' =>           $this->lastPage(),
        ];
    }
}
