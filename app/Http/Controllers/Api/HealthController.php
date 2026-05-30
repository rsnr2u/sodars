<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    use RespondsWithApi;

    public function __invoke(): JsonResponse
    {
        DB::select('select 1');

        return $this->success([
            'app' => config('app.name'),
            'database' => config('database.default'),
            'timestamp' => now()->toISOString(),
        ], 'SODARS API is healthy.');
    }
}
