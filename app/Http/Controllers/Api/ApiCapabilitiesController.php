<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiCapabilitiesController extends Controller
{
    public function capabilities(): JsonResponse
    {
        return response()->json([
            'serverType' => 'ablibrarian-lite',
            'syncApiVersion' => '1',
            'capabilities' => ['HISTORY_SYNC', 'POSITION_SYNC', 'STATS', 'ACHIEVEMENTS'],
            'requiresAuth' => true,
            'authMethods' => ['username_password', 'email_otp'],
        ]);
    }
}
