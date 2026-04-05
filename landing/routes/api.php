<?php

use App\Http\Controllers\Api\LicenseValidateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:license-validate')->group(function (): void {
    Route::post('license/validate', LicenseValidateController::class);
});
