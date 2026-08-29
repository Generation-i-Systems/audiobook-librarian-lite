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
            // Names must match the client's BackendCapability enum. POSITION_SYNC and
            // ACHIEVEMENTS are the older spellings of HISTORY_SYNC and BADGES, still sent so
            // clients that only know those keep working.
            'capabilities' => [
                'HISTORY_SYNC',
                'POSITION_SYNC',
                'STATS',
                'BOOKMARKS_SYNC',
                'BADGES',
                'ACHIEVEMENTS',
            ],
            'requiresAuth' => true,
            'authMethods' => ['username_password', 'email_otp'],
        ]);
    }
}
