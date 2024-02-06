<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OfficialReceiptController;
use App\Http\Controllers\PrintController;

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
                Route::post('/register', [AuthController::class, 'register'])->name('register');
                Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
                Route::get('/me', [AuthController::class, 'me'])->name('me');
                Route::patch('/users/{id}', [AuthController::class, 'update'])->name('update');
            });

        // Route api for official receipt
        Route::apiResource('/official-receipts', 'App\Http\Controllers\OfficialReceiptController')
            ->except(['create', 'edit', 'update', 'destroy']);
        Route::patch('/official-receipts/{official_receipt}/deposit', [OfficialReceiptController::class, 'deposit'])
            ->name('official-receipts.deposit');
        Route::patch('/official-receipts/{official_receipt}/cancel', [OfficialReceiptController::class, 'cancel'])
            ->name('official-receipts.cancel');

        // Route api for particular
        Route::apiResource('/particulars', 'App\Http\Controllers\ParticularController');

        // Route api for discount
        Route::apiResource('/discounts', 'App\Http\Controllers\DiscountController');

        // Print api routes
        Route::get('/print/{printType}', [PrintController::class, 'index'])->name('print.index');
    });
});
