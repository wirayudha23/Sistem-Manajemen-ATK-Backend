<?php

use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CheckoutCartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ReorderCartController;
use App\Http\Controllers\ReorderController;
use App\Http\Controllers\ProductReceivedController;
use App\Http\Controllers\EmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/auth/redirect', [SocialiteController::class, 'redirect']);
Route::post('/auth/callback', [SocialiteController::class, 'callback']);
Route::post('/auth/logout', [SocialiteController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('role')->group(function () {
    Route::post('/auth/authorize', [SocialiteController::class, 'authorize']);
});

Route::apiResource('checkouts', CheckoutController::class);
Route::apiResource('checkout-carts', CheckoutCartController::class);

Route::apiResource('categories', CategoryController::class);
Route::apiResource('products', ProductController::class);
// Route::apiResource('product-received', ProductReceivedController::class);

//middleware
Route::middleware('auth:sanctum')->group(function () {
    // Route::apiResource('categories', CategoryController::class)->middleware('role:baak');
    Route::apiResource('users', UserController::class)->middleware('role:kepala baak,baak');
    Route::apiResource('units', UnitController::class)->middleware('role:baak');
    // Route::apiResource('products', ProductController::class)->middleware('role:baak');


    Route::apiResource('reorder-carts', ReorderCartController::class)->middleware('role:baak');
    Route::apiResource('reorders', ReorderController::class)->middleware('role:baak');

    Route::apiResource('product-received', ProductReceivedController::class)->middleware('role:baak');

    // Route::post('/send-email', [EmailController::class, 'sendReorderEmail'])->middleware('role:baak');
    // Route::apiResource('send-email', EmailController::class)->middleware('role:baak');
});
