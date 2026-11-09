<?php

namespace App\Services\BiometricQueries;

use App\Interfaces\BiometricQueries\BiometricQueriesServiceInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Override;

class BiometricQueriesService implements BiometricQueriesServiceInterface
{
    #[Override]
    public function getEmployeesByLineCode(string $lineCode): array
    {
        try {
            $baseUrl = config('services.biometric.new_url');
            $response = Http::get("$baseUrl/employees/getEmployeesByLineCode/$lineCode");

            return $this->toList($response);
        } catch (\Throwable $th) {
            return [];
        }
    }

    #[Override]
    public function getAllEmployees(): array
    {
        try {
            $baseUrl = config('services.biometric.url');
            $response = Http::withHeaders(['Authorization' => config('services.biometric.key')])
                ->get("$baseUrl/biometrico/getAllEmployees");

            return $this->toList($response);
        } catch (\Throwable $th) {
            return [];
        }
    }

    /**
     * Decoded JSON list of a successful response; anything else yields an empty array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function toList(Response $response): array
    {
        $data = $response->json();

        if (! $response->successful() || ! is_array($data)) {
            return [];
        }

        return $data;
    }
}
