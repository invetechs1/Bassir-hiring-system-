<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function __invoke()
    {
        $checks = ['app' => 'ok', 'database' => 'unknown', 'storage' => 'unknown', 'timestamp' => now()->toIso8601String()];
        try {
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'error';
        }
        try {
            Storage::put('health.txt', 'ok');
            Storage::delete('health.txt');
            $checks['storage'] = 'ok';
        } catch (\Throwable) {
            $checks['storage'] = 'error';
        }
        return response()->json($checks, $checks['database'] === 'ok' && $checks['storage'] === 'ok' ? 200 : 503);
    }
}
