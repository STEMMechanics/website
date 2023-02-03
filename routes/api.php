<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SubscriptionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [UserController::class, 'register']);

Route::apiResource('users', UserController::class);
Route::post('/users/forgotUsername', [UserController::class, 'forgotUsername']);
Route::post('/users/forgotPassword', [UserController::class, 'forgotPassword']);
Route::post('/users/resetPassword', [UserController::class, 'resetPassword']);
Route::post('/users/resendVerifyEmailCode', [UserController::class, 'resendVerifyEmailCode']);
Route::post('/users/verifyEmail', [UserController::class, 'verifyEmail']);

Route::apiResource('media', MediaController::class);
Route::get('media/{medium}/download', [MediaController::class, 'download']);

Route::apiResource('posts', PostController::class);

Route::apiResource('events', EventController::class);

Route::apiResource('subscriptions', SubscriptionController::class);
Route::delete('subscriptions', [SubscriptionController::class, 'destroyByEmail']);

Route::post('/contact', [ContactController::class, 'send']);

Route::get('/logs/{name}', [LogController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::any('{any}', function () {
    return response()->json(['message' => 'Resource not found'], 404);
})->where('any', '.*');
