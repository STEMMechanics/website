<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkshopController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::match(['get', 'post'], '/register', [UserController::class, 'register'])->name('register');
Route::get('/login', [UserController::class, 'login'])->name('login');
Route::post('/login', [UserController::class, 'authenticate']);
Route::get('/logout', [UserController::class, 'logout'])->name('logout')->middleware('auth');
Route::get('/verify', [UserController::class, 'verify'])->name('verify');
Route::post('/verify', [UserController::class, 'verify_store'])->name('verify.store');

Route::get('/account', [AccountController::class, 'index'])->name('account.index');
Route::get('/account/users', [AccountController::class, 'users_index'])->name('account.users.index');
Route::get('/account/users/{user}', [AccountController::class, 'users_show'])->name('account.users.show');
Route::put('/account/users/{user}', [AccountController::class, 'users_show'])->name('account.users.show');
Route::get('/workshops', [WorkshopController::class, 'index'])->name('workshop.show');