<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\CategoryController;

Route::get('/', function () {
    return view('login');
})->name('login');

Route::get('/redirect', [SocialiteController::class, 'redirect'])->name('redirect');
Route::get('/callback', [SocialiteController::class, 'callback']);
Route::post('/logout', [SocialiteController::class, 'logout'])->name('logout');
