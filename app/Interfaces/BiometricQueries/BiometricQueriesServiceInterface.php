<?php

namespace App\Interfaces\BiometricQueries;

interface BiometricQueriesServiceInterface
{
    /**
     * Employees registered in the biometric system for the given line code.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEmployeesByLineCode(string $lineCode): array;

    /**
     * Every employee registered in the legacy biometric system.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllEmployees(): array;
}
