<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryExcelController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UnitExcelController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductExcelController;
use App\Http\Controllers\StudyProgramController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserExcelController;
use App\Http\Controllers\PurposeController;
use App\Http\Controllers\CheckoutCartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CheckoutExcelController;
use App\Http\Controllers\ReorderCartController;
use App\Http\Controllers\ReorderController;
use App\Http\Controllers\ReorderWhatsappController;
use App\Http\Controllers\ProductReceivedController;
use App\Http\Controllers\FundTransactionController;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\ReportExcelController;
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


// Route::apiResource('categories', CategoryController::class);
// Route::apiResource('units', UnitController::class);
// Route::apiResource('products', ProductController::class);
// Route::apiResource('study-programs', StudyProgramController::class);
// Route::apiResource('users', UserController::class);
// Route::apiResource('purposes', PurposeController::class);
// Route::apiResource('checkout-carts', CheckoutCartController::class);
// Route::apiResource('checkouts', CheckoutController::class);
// Route::apiResource('reorder-carts', ReorderCartController::class);
// Route::apiResource('reorders', ReorderController::class);
// Route::post('/reorders/{reorder}/send', [ReorderWhatsappController::class, 'send']);
// Route::post('/reorders/{reorder}/cancel', [ReorderWhatsappController::class, 'cancel'])->name('reorders.cancelWhatsapp');
// Route::post('/reorders/{reorder}/update', [ReorderWhatsappController::class, 'sendUpdate'])->name('reorders.updateWhatsapp');
// Route::apiResource('product-received', ProductReceivedController::class);
// Route::patch('/product-received/{productReceived}/complete', [ProductReceivedController::class, 'complete'])->name('product-received.complete');
// Route::apiResource('funds', FundTransactionController::class);

// Route::get('/export-checkout', [CheckoutExcelController::class, 'export']);
// Route::post('/import-checkout', [CheckoutExcelController::class, 'import']);


// Route::post('/import-category', [CategoryExcelController::class, 'import']);
// Route::post('/import-unit', [UnitExcelController::class, 'import']);
// Route::post('/import-user', [UserExcelController::class, 'import']);
// Route::post('/import-product', [ProductExcelController::class, 'import']);

// Route::apiResource('product-received', ProductReceivedController::class);

Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->middleware('role:Kabag,BAAK');
    Route::get('/{category}', [CategoryController::class, 'show'])->middleware('role:Kabag,BAAK');
    Route::post('/', [CategoryController::class, 'store'])->middleware('role:BAAK');
    Route::put('/{category}', [CategoryController::class, 'update'])->middleware('role:BAAK');
    Route::delete('/{category}', [CategoryController::class, 'destroy'])->middleware('role:BAAK');

    Route::post('/import', [CategoryExcelController::class, 'import'])->middleware('role:BAAK');
});

Route::get('/category-template', [CategoryController::class, 'template']);

Route::prefix('units')->group(function () {
    Route::get('/', [UnitController::class, 'index'])->middleware('role:Kabag,BAAK');
    Route::get('/{unit}', [UnitController::class, 'show'])->middleware('role:Kabag,BAAK');
    Route::post('/', [UnitController::class, 'store'])->middleware('role:BAAK');
    Route::put('/{unit}', [UnitController::class, 'update'])->middleware('role:BAAK');
    Route::delete('/{unit}', [UnitController::class, 'destroy'])->middleware('role:BAAK');

    Route::post('/import', [UnitExcelController::class, 'import'])->middleware('role:BAAK');
});

Route::get('/unit-template', [UnitController::class, 'template']);

Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{product}', [ProductController::class, 'show'])->middleware('role:Kabag,BAAK');
    Route::post('/', [ProductController::class, 'store'])->middleware('role:BAAK');
    Route::put('/{product}', [ProductController::class, 'update'])->middleware('role:BAAK');
    Route::patch('/{product}', [ProductController::class, 'update'])->middleware('role:BAAK');
    Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('role:BAAK');

    Route::post('/import', [ProductExcelController::class, 'import'])->middleware('role:BAAK');
});
Route::get('public/products', [ProductController::class, 'publicIndex']);
Route::get('/product-template', [ProductController::class, 'template']);

Route::prefix('study-programs')->group(function () {
    Route::get('/', [StudyProgramController::class, 'index'])->middleware('role:Kabag,BAAK');
    Route::get('/{studyProgram}', [StudyProgramController::class, 'show'])->middleware('role:Kabag,BAAK');
    Route::post('/', [StudyProgramController::class, 'store'])->middleware('role:BAAK');
    Route::put('/{studyProgram}', [StudyProgramController::class, 'update'])->middleware('role:BAAK');
    Route::delete('/{studyProgram}', [StudyProgramController::class, 'destroy'])->middleware('role:BAAK');
});

Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{user}', [UserController::class, 'show'])->middleware('role:Kabag,BAAK');
    Route::post('/', [UserController::class, 'store'])->middleware('role:Kabag,BAAK');
    Route::put('/{user}', [UserController::class, 'update'])->middleware('role:Kabag,BAAK');
    Route::patch('/{user}', [UserController::class, 'update'])->middleware('role:Kabag,BAAK');
    Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('role:Kabag,BAAK');

    Route::post('/import', [UserExcelController::class, 'import'])->middleware('role:BAAK');
});
Route::get('/user-template', [UserController::class, 'template']);
Route::get('public/users', [UserController::class, 'publicIndex']);

Route::prefix('purposes')->group(function () {
    Route::get('/', [PurposeController::class, 'index']);
    Route::get('/{purpose}', [PurposeController::class, 'show'])->middleware('role:Kabag,BAAK');
    Route::post('/', [PurposeController::class, 'store'])->middleware('role:BAAK');
    Route::put('/{purpose}', [PurposeController::class, 'update'])->middleware('role:BAAK');
    Route::delete('/{purpose}', [PurposeController::class, 'destroy'])->middleware('role:BAAK');
});

Route::prefix('checkout-carts')->group(function () {
    Route::get('/', [CheckoutCartController::class, 'index']);
    Route::get('/{checkoutCart}', [CheckoutCartController::class, 'show']);
    Route::post('/', [CheckoutCartController::class, 'store']);
    Route::put('/{checkoutCart}', [CheckoutCartController::class, 'update']);
    Route::patch('/{checkoutCart}', [CheckoutCartController::class, 'update']);
    Route::delete('/{checkoutCart}', [CheckoutCartController::class, 'destroy']);
});

Route::prefix('checkouts')->group(function () {
    // Route::get('/', [CheckoutController::class, 'index'])->middleware('role:Kabag,BAAK');
    Route::get('/', [CheckoutController::class, 'index']);
    Route::get('/{checkout}', [CheckoutController::class, 'show'])->middleware('role:Kabag,BAAK');
    Route::post('/', [CheckoutController::class, 'store']);
    Route::put('/{checkout}', [CheckoutController::class, 'update'])->middleware('role:BAAK');
    // Route::patch('/{checkout}', [CheckoutController::class, 'update'])->middleware('role:BAAK');
    Route::patch('/{checkout}', [CheckoutController::class, 'update']);
    // Route::delete('/{checkout}', [CheckoutController::class, 'destroy'])->middleware('role:BAAK');
    Route::delete('/{checkout}', [CheckoutController::class, 'destroy']);

    Route::post('/import', [CheckoutExcelController::class, 'import'])->middleware('role:BAAK');
    Route::get('/export', [CheckoutExcelController::class, 'export'])->middleware('role:BAAK');
});

Route::prefix('reorder-carts')->group(function () {
    Route::get('/', [ReorderCartController::class, 'index'])->middleware('role:Kabag,BAAK');
    Route::get('/{reorderCart}', [ReorderCartController::class, 'show'])->middleware('role:Kabag,BAAK');
    Route::post('/', [ReorderCartController::class, 'store'])->middleware('role:BAAK');
    Route::put('/{reorderCart}', [ReorderCartController::class, 'update'])->middleware('role:BAAK');
    Route::patch('/{reorderCart}', [ReorderCartController::class, 'update'])->middleware('role:BAAK');
    Route::delete('/{reorderCart}', [ReorderCartController::class, 'destroy'])->middleware('role:BAAK');
});

Route::prefix('reorders')->group(function () {
    Route::get('/', [ReorderController::class, 'index'])->middleware('role:Kabag,BAAK');
    Route::get('/{reorder}', [ReorderController::class, 'show'])->middleware('role:Kabag,BAAK');
    Route::post('/', [ReorderController::class, 'store'])->middleware('role:BAAK');
    Route::put('/{reorder}', [ReorderController::class, 'update'])->middleware('role:BAAK');
    Route::patch('/{reorder}', [ReorderController::class, 'update'])->middleware('role:BAAK');
    Route::delete('/{reorder}', [ReorderController::class, 'destroy'])->middleware('role:BAAK');
});

Route::prefix('reorders')->group(function () {
    Route::post('/{reorder}/send', [ReorderWhatsappController::class, 'send'])->middleware('role:BAAK');
    Route::post('/{reorder}/cancel', [ReorderWhatsappController::class, 'cancel'])->middleware('role:BAAK');
    Route::post('/{reorder}/update', [ReorderWhatsappController::class, 'sendUpdate'])->middleware('role:BAAK');
});

Route::prefix('product-received')->group(function () {
    Route::get('/', [ProductReceivedController::class, 'index'])->middleware('role:Kabag,BAAK');
    Route::get('/{productReceived}', [ProductReceivedController::class, 'show'])->middleware('role:Kabag,BAAK');
    Route::post('/', [ProductReceivedController::class, 'store'])->middleware('role:BAAK');
    Route::put('/{productReceived}', [ProductReceivedController::class, 'update'])->middleware('role:BAAK');
    Route::patch('/{productReceived}', [ProductReceivedController::class, 'update'])->middleware('role:BAAK');
    Route::put('/{productReceived}/complete', [ProductReceivedController::class, 'complete'])->middleware('role:BAAK');
    Route::patch('/{productReceived}/complete', [ProductReceivedController::class, 'complete'])->middleware('role:BAAK');
    // Route::delete('/{productReceived}', [ProductReceivedController::class, 'destroy'])->middleware('role:BAAK');
});

Route::prefix('funds')->group(function () {
    Route::get('/', [FundTransactionController::class, 'index'])->middleware('role:Kabag,BAAK');
    Route::get('/{fund}', [FundTransactionController::class, 'show'])->middleware('role:Kabag,BAAK');
    Route::post('/', [FundTransactionController::class, 'store'])->middleware('role:BAAK');
    Route::put('/{fund}', [FundTransactionController::class, 'update'])->middleware('role:BAAK');
    Route::patch('/{fund}', [FundTransactionController::class, 'update'])->middleware('role:BAAK');
    Route::delete('/{fund}', [FundTransactionController::class, 'destroy'])->middleware('role:BAAK');
});

Route::get('/export-report', [ReportExcelController::class, 'export']);



//middleware
// Route::middleware('auth:sanctum')->group(function () {
//     Route::apiResource('categories', CategoryController::class)->middleware('role:baak');
//     Route::apiResource('users', UserController::class)->middleware('role:kepala baak,baak');
//     Route::apiResource('units', UnitController::class)->middleware('role:baak');
//     Route::apiResource('products', ProductController::class)->middleware('role:baak');




//     Route::post('/send-email', [EmailController::class, 'sendReorderEmail'])->middleware('role:baak');
//     Route::apiResource('send-email', EmailController::class)->middleware('role:baak');
// });
