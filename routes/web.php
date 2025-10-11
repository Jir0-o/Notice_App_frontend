<?php

use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/print/notice/{id}', function () {
    // Returns a static view; JS will fetch by window.location
    return view('pdf.notice_template');
})->name('notice.print');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::get('/settings',[SettingsController::class, 'index'])->name('settings');

    Route::resource('users', UserController::class);
});