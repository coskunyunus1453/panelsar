<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LandingMediaController extends Controller
{
    private const ALLOWED_EXT = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'ico'];

    public function show(string $ext, string $base): BinaryFileResponse
    {
        $ext = strtolower($ext);
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            abort(404);
        }
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $base)) {
            abort(404);
        }

        $file = $base.'.'.$ext;
        $relative = 'landing/'.$file;
        if (! Storage::disk('public')->exists($relative)) {
            abort(404);
        }

        $absolute = Storage::disk('public')->path($relative);

        return response()->file($absolute);
    }
}
