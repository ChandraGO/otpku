<?php

use App\Http\Controllers\Api\CustomerApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.key', 'throttle:customer-api'])->group(function (): void {
    Route::get('/me', [CustomerApiController::class, 'me']);
    Route::get('/balance', [CustomerApiController::class, 'balance']);
    Route::get('/countries', [CustomerApiController::class, 'countries']);
    Route::get('/services', [CustomerApiController::class, 'services']);
    Route::get('/prices', [CustomerApiController::class, 'prices']);
    Route::get('/orders', [CustomerApiController::class, 'orders']);
    Route::post('/orders', [CustomerApiController::class, 'createOrder']);
    Route::get('/orders/{order}', [CustomerApiController::class, 'showOrder']);
    Route::post('/orders/{order}/actions', [CustomerApiController::class, 'action']);
});
