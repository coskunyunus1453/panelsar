<?php

use App\Http\Controllers\Api\LicenseValidateController;
use App\Http\Controllers\Api\LicensingCheckoutController;
use App\Http\Controllers\Api\PaytrLicensingCallbackController;
use App\Http\Controllers\Api\PublicLandingApiController;
use App\Http\Controllers\Api\StripeLicensingWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/public')->group(function (): void {
    Route::get('settings', [PublicLandingApiController::class, 'settings'])->middleware('throttle:api');
    Route::get('i18n/config', [PublicLandingApiController::class, 'i18nConfig'])->middleware('throttle:api');
    Route::get('i18n/overrides', [PublicLandingApiController::class, 'i18nOverrides'])->middleware('throttle:api');
});

Route::prefix('v1')->middleware('throttle:license-validate')->group(function (): void {
    Route::post('license/validate', LicenseValidateController::class);
});

Route::prefix('v1/licensing')->group(function (): void {
    Route::post('checkout', [LicensingCheckoutController::class, 'start'])
        ->middleware('throttle:licensing-checkout');
    Route::get('orders/{orderRef}', [LicensingCheckoutController::class, 'orderStatus'])
        ->middleware('throttle:licensing-order');
});

Route::post('v1/webhooks/stripe-licensing', StripeLicensingWebhookController::class);
Route::post('v1/webhooks/paytr-licensing', PaytrLicensingCallbackController::class);
