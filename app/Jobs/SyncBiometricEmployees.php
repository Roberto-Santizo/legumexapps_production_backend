<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Singletons\Biometric;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncBiometricEmployees implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employees = Biometric::getEmployees();

        $now = now();

        $employees = collect($employees)->map(fn (array $employee): array => [
            'uuid' => $employee['id'],
            'code' => $employee['code'],
            'name' => $employee['name'],
            'position' => $employee['position'],
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        Employee::upsert($employees, uniqueBy: ['uuid'], update: ['code', 'name', 'updated_at']);

        exit;
    }
}
