<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalMeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()?->load('role.permissions'),
        ]);
    }
}
