<?php

namespace App\Singletons;

use Illuminate\Support\Facades\Http;

class Biometric
{
    public static function getEmployees()
    {
        $url = env('BIOMETRICO_URL').'/getAllEmployees';
        $employees = Http::withHeaders(['Authorization' => env('BIOMETRICO_APP_KEY')])->get($url)->collect()->toArray();

        return $employees;
    }
}
