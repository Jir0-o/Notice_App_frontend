<?php

use App\Http\Controllers\NoticeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Carbon\Carbon;
use App\Models\MeetingDetail;


// Home Route
Route::get('/', function () {
    return view('welcome');
});

// External Views
Route::view('/ext/login', 'frontend.ext.login')->name('ext.login');

Route::middleware('web', 'front.token')->group(function () {

    // Meetings
    Route::view('/meetings', 'frontend.meetings.index')->name('meetings.index');
    Route::view('/view-notices', 'frontend.notices.index')->name('notices.index');
    Route::view('/generate/notice', 'frontend.notices.notice_generate')->name('notices.generate');
    // Rooms
    Route::view('/rooms', 'frontend.rooms.index')->name('rooms.index');


Route::view('/ext/panel', 'frontend.ext.panel')->name('ext.panel');

Route::get('/notices/my', function () {
    return view('notices.my');
})->name('notices.my');

// Super Admin Routes
Route::view('/departments', 'frontend.departments.index')->name('departments.index');
Route::view('/designations', 'frontend.designations.index')->name('designations.index');
Route::view('/users-panel', 'frontend.users.index')->name('panel.users.index');

// Archive Views (Frontend)
Route::view('/archive/departments', 'frontend.archive.departments')->name('archive.departments');
Route::view('/archive/designations', 'frontend.archive.designations')->name('archive.designations');
Route::view('/archive/users', 'frontend.archive.users')->name('archive.users');

// Settings Route
Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

// User Routes
Route::view('/user/meetings', 'frontend.meetings.user_meetings')->name('user.meetings.index');
Route::view('/user-notices', 'frontend.user_notices.index')->name('user-notices.index');
Route::view('/user-notices/{id}', 'frontend.user_notices.show')->name('user-notices.show');


Route::view('/generate/view/notice/{id}', 'frontend.notices.noticePdf')->name('notices.pdf');

// Notices
Route::view('/notices/create', 'frontend.notices.create')->name('notices.create');
Route::view('/notices/{id}', 'frontend.notices.show')->name('notices.show');
Route::view('/notices/{id}/edit', 'frontend.notices.edit')->name('notices.edit');


// Profile Route for All Roles
Route::view('/user-profile', 'frontend.profile.profile')->name('user.profile.index');

// Dashboard
Route::get('/dashboard', function () {
    return view('frontend.dashboard');
})->name('dashboard');

// User Resource Controller
Route::resource('users', UserController::class);

});

Route::get('time', function() {
    $now = Carbon::now('Asia/Dhaka');
    $from = $now->copy()->addMinutes(29);
    $to   = $now->copy()->addMinutes(31);
   
    $due = MeetingDetail::query()
        ->whereDate('date', $now->toDateString())
        ->whereBetween('start_time', [
            $from->format('H:i:s'),
            $to->format('H:i:s'),
        ])
        ->withCount('propagations')
        ->get();

    dd($due);
});