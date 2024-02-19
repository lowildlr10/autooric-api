<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OfficialReceiptController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ParticularController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\PayorController;
use App\Http\Controllers\PaperSizeController;
use App\Http\Controllers\CategoryController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::group([
    'prefix' => 'v1',
    'middleware' => ['api']
], function() {
    Route::name('user.')
        ->group(function() {
            Route::post('/login', [AuthController::class, 'login'])->name('login');
            Route::get('/unauthenticated', [AuthController::class, 'unauthenticated'])->name('unauthenticated');
        });

    Route::middleware('auth:sanctum')->group(function() {
        Route::name('user.')
            ->group(function() {
                Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
                Route::get('/me', [AuthController::class, 'me'])->name('me');
            });

        // Route api for official receipt
        Route::apiResource('/official-receipts', 'App\Http\Controllers\OfficialReceiptController')
            ->except(['create', 'edit', 'update', 'destroy']);
        Route::patch('/official-receipts/{official_receipt}/deposit', [OfficialReceiptController::class, 'deposit'])
            ->name('official-receipts.deposit');
        Route::patch('/official-receipts/{official_receipt}/cancel', [OfficialReceiptController::class, 'cancel'])
            ->name('official-receipts.cancel');

        // Route api for categories
        Route::apiResource('/categories', 'App\Http\Controllers\CategoryController');
        Route::get('/categories-paginated', [CategoryController::class, 'indexPaginated'])
            ->name('categories.paginated');

        // Route api for particular
        Route::apiResource('/particulars', 'App\Http\Controllers\ParticularController');
        Route::get('/particulars-paginated', [ParticularController::class, 'indexPaginated'])
            ->name('particulars.paginated');

        // Route api for payor
        Route::apiResource('/payors', 'App\Http\Controllers\PayorController');
        Route::get('/payors-paginated', [PayorController::class, 'indexPaginated'])
            ->name('payors.paginated');

        // Route api for discount
        Route::apiResource('/discounts', 'App\Http\Controllers\DiscountController');
        Route::get('/discounts-paginated', [DiscountController::class, 'indexPaginated'])
            ->name('discounts.paginated');

        // Route api for user management
        Route::apiResource('/user-management/users', 'App\Http\Controllers\UserManagementController');

        // Route api for paper size
        Route::apiResource('/paper-sizes', 'App\Http\Controllers\PaperSizeController');
        Route::get('/paper-sizes-paginated', [PaperSizeController::class, 'indexPaginated'])
            ->name('paper-sizes.paginated');

        // Print api routes
        Route::get('/print/{printType}', [PrintController::class, 'index'])->name('print.index');
    });
});
