<?php

use App\Http\Controllers\WhmcsSsoRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/sso/whmcs', [WhmcsSsoRedirectController::class, 'redirect'])->name('whmcs.sso.redirect');

Route::get('/{any?}', function () {
    $indexPath = public_path('index.html');

    if (!is_file($indexPath)) {
        abort(503, 'Frontend build not found.');
    }

    return response()->file($indexPath);
})->where('any', '^(?!api|sanctum|up).*$');
