<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Canlı ortamda harici araç URL’leri (.env ile yapılandırılır).
 */
class UiLinksController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'phpmyadmin_url' => (string) config('panelsar.ui.phpmyadmin_url', ''),
            'adminer_url' => (string) config('panelsar.ui.adminer_url', ''),
        ]);
    }
}
