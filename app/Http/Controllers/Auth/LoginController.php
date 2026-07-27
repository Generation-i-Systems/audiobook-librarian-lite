<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AppConnectLinks;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lite is an API-only service — there is no web-based username/password
 * session login here (auth happens in the mobile app via POST /api/v1/login).
 * This controller exists solely to host the "connect the mobile app" QR
 * landing page at a conventional /login URL.
 */
class LoginController extends Controller
{
    public function showLoginForm(Request $request): View
    {
        $apiUrl = AppConnectLinks::apiBaseUrl($request);

        return view('auth.login', [
            'appConnectApiUrl' => $apiUrl,
            'appConnectUrl' => AppConnectLinks::redirectorUrl($request, $apiUrl),
        ]);
    }
}
