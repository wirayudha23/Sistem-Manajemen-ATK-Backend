<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialiteController;

Route::get('/', function () {
    return view('login');
})->name('login');

Route::get('/redirect', [SocialiteController::class, 'redirect'])->name('redirect');
Route::get('/callback', [SocialiteController::class, 'callback']);
Route::post('/logout', [SocialiteController::class, 'logout'])->name('logout');

// // Middleware 'auth' untuk memastikan pengguna sudah login
// Route::middleware(['auth'])->group(function () {

//     // Grup untuk role "kepala baak"
//     Route::middleware(['role:kepala baak'])->group(function () {
//         Route::get('/dashboard-kepala', function () {
//             return view('dashboard-kepala');
//         })->name('dashboard.kepala');
//     });

//     // Grup untuk role "baak"
//     Route::middleware(['role:baak'])->group(function () {
//         Route::get('/dashboard-baak', function () {
//             return view('dashboard-baak');
//         })->name('dashboard.baak');
//     });

//     // Grup untuk role "dosen"
//     // Route::middleware(['role:dosen'])->group(function () {
//     //     Route::get('/dashboard-dosen', function () {
//     //         return view('dashboard-dosen');
//     //     })->name('dashboard.dosen');
//     // });

//     Route::middleware(RoleMiddleware::class . ':dosen')->group(function () {
//         Route::get('/dashboard-dosen', function () {
//             return view('dashboard-dosen');
//         })->name('dashboard.dosen');
//     });

// });

