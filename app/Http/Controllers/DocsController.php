<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class DocsController extends Controller
{
    public function openapi(): Response
    {
        $openApiPath = base_path('docs/openapi.json');

        if (!File::exists($openApiPath)) {
            abort(404, 'OpenAPI specification not found');
        }

        return response(File::get($openApiPath), 200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        ]);
    }
}
