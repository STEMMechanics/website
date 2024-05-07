<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('index');

Route::redirect('/events', '/workshops', 301);
Route::redirect('/events/{event}', '/workshops/{event}', 301);

Route::get('/posts', [PostController::class, 'index'])->name('post.index');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('post.show');
Route::get('/workshops', [EventController::class, 'index'])->name('event.index');
Route::get('/workshops/{event}', [EventController::class, 'show'])->name('event.show');

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

Route::get('/terms-conditions', function () {
    return view('terms-conditions');
})->name('terms-conditions');

Route::get('/privacy', function () {
    return view('privacy');
})->name('privacy');

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

    Route::get('/admin/events', [EventController::class, 'admin_index'])->name('admin.event.index');
    Route::get('/admin/events/create', [EventController::class, 'admin_create'])->name('admin.event.create');
    Route::get('/admin/events/{event}/duplicate', [EventController::class, 'admin_duplicate'])->name('admin.event.duplicate');
    Route::post('/admin/events', [EventController::class, 'admin_store'])->name('admin.event.store');
    Route::get('/admin/events/{event}', [EventController::class, 'admin_edit'])->name('admin.event.edit');
    Route::put('/admin/events/{event}', [EventController::class, 'admin_update'])->name('admin.event.update');
    Route::delete('/admin/events/{event}', [EventController::class, 'admin_destroy'])->name('admin.event.destroy');
});
