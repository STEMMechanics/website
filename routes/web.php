<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkshopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('index');

Route::get('posts', [PostController::class, 'index'])->name('post.index');
Route::get('posts/{post}', [PostController::class, 'show'])->name('post.show');
Route::get('workshops', [WorkshopController::class, 'index'])->name('workshop.index');
Route::get('workshops/{workshop}', [WorkshopController::class, 'show'])->name('workshop.show');

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::post('/account', [AccountController::class, 'update'])->name('account.update');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'postLogin'])->name('login.store');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'postRegister'])->name('register.store');
Route::get('/update-email', [AuthController::class, 'updateEmail'])->name('update.email');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

Route::get('/code-of-conduct', function () {
    return view('code-of-conduct');
})->name('code-of-conduct');

Route::get('/media', [MediaController::class, 'index'])->name('media.index');
Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show');
Route::get('/media/download/{media}', [MediaController::class, 'download'])->name('media.download');

Route::middleware('admin')->group(function () {
    Route::get('/admin/media', [MediaController::class, 'admin_index'])->name('admin.media.index');
    Route::get('/admin/media/create', [MediaController::class, 'admin_create'])->name('admin.media.create');
    Route::post('/admin/media', [MediaController::class, 'admin_store'])->name('admin.media.store');
    Route::get('/admin/media/{media}', [MediaController::class, 'admin_edit'])->name('admin.media.edit');
    Route::put('/admin/media/{media}', [MediaController::class, 'admin_update'])->name('admin.media.update');
    Route::delete('/admin/media/{media}', [MediaController::class, 'admin_destroy'])->name('admin.media.destroy');
    Route::get('/admin/locations', [LocationController::class, 'index'])->name('admin.location.index');
    Route::get('/admin/locations/create', [LocationController::class, 'create'])->name('admin.location.create');
    Route::post('/admin/locations', [LocationController::class, 'store'])->name('admin.location.store');
    Route::get('/admin/locations/{location}', [LocationController::class, 'edit'])->name('admin.location.edit');
    Route::put('/admin/locations/{location}', [LocationController::class, 'update'])->name('admin.location.update');
    Route::delete('/admin/locations/{location}', [LocationController::class, 'destroy'])->name('admin.location.destroy');

    Route::get('/admin/posts', [PostController::class, 'admin_index'])->name('admin.post.index');
    Route::get('/admin/posts/create', [PostController::class, 'admin_create'])->name('admin.post.create');
    Route::post('/admin/posts', [PostController::class, 'admin_store'])->name('admin.post.store');
    Route::get('/admin/posts/{post}', [PostController::class, 'admin_edit'])->name('admin.post.edit');
    Route::put('/admin/posts/{post}', [PostController::class, 'admin_update'])->name('admin.post.update');
    Route::delete('/admin/posts/{post}', [PostController::class, 'admin_destroy'])->name('admin.post.destroy');

    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.user.index');
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.user.create');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.user.store');
    Route::get('/admin/users/{user}', [UserController::class, 'edit'])->name('admin.user.edit');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.user.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.user.destroy');

    Route::get('/admin/workshops', [WorkshopController::class, 'admin_index'])->name('admin.workshop.index');
    Route::get('/admin/workshops/create', [WorkshopController::class, 'admin_create'])->name('admin.workshop.create');
    Route::post('/admin/workshops', [WorkshopController::class, 'admin_store'])->name('admin.workshop.store');
    Route::get('/admin/workshops/{workshop}', [WorkshopController::class, 'admin_edit'])->name('admin.workshop.edit');
    Route::put('/admin/workshops/{workshop}', [WorkshopController::class, 'admin_update'])->name('admin.workshop.update');
    Route::delete('/admin/workshops/{workshop}', [WorkshopController::class, 'admin_destroy'])->name('admin.workshop.destroy');

});
