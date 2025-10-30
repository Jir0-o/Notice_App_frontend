<?php

use App\Http\Controllers\Api\SanctumAuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\PasswordResetApiController;
use App\Http\Controllers\NoticePropagationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\MeetingDetailController;
use App\Http\Controllers\Api\MeetingAvailabilityController;
use App\Http\Controllers\Api\NoticeTemplateController;
use App\Http\Controllers\Api\MeetingAttachmentController;
use App\Http\Controllers\Api\MeetingReminderCronController;

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
Route::group(['prefix'=>'auth'], function(){
    Route::post('/register', [SanctumAuthController::class, 'store']);
    Route::post('/login', [SanctumAuthController::class, 'login'])->name('login');
    Route::post('/logout', [SanctumAuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
});

//notice
Route::middleware('auth:sanctum')->group(function () {
    Route::middleware(['role:User'])->group(function () {
        // for notice base on user
        Route::get('my-notices', [NoticePropagationController::class, 'myNotices'])->name('my-notices');
        Route::get('my-notices/{id}', [NoticePropagationController::class, 'showMyNotice'])->name('my-notices.show');
        // for downloading notice attachments
        Route::get('notices/{id}/attachments', [NoticeController::class, 'downloadAttachments'])->name('notices.attachments.download');

        // single attachment download
        Route::get('/notices/{noticeId}/attachments/{attachmentId}', [NoticeController::class, 'downloadSingleAttachment'])
        ->name('notices.attachments.download');

        // mark notice as read
        Route::post('notices/{id}/read', [NoticeController::class, 'noticeMarkAsRead'])->name('notices.read');

        // meeting details for user
        Route::get('meeting-details/by-user', [MeetingDetailController::class, 'byUser'])->name('meeting-details.by-user');

        // mark meeting detail propagation as read
        Route::post('meeting-details/{id}/read', [MeetingDetailController::class, 'markAsRead'])->name('meeting-details.read');

        Route::get('/meeting-details/{meetingDetail}/attachments', [MeetingAttachmentController::class, 'index'])->name('meeting-details.attachments.index');
        Route::get('/meeting-attachments/{attachment}/download', [MeetingAttachmentController::class, 'downloadOne'])->name('meeting-attachments.download');
        Route::get('/meeting-details/{meetingDetail}/attachments/download-all', [MeetingAttachmentController::class, 'downloadAll'])->name('meeting-attachments.download-all');

        Route::get('/cron/meeting-reminders', MeetingReminderCronController::class)->name('cron.meeting-reminders');
    });

    // for profile update
    Route::get('profile', [UserController::class, 'profileShow'])->name('profile.show');
    Route::post('profile', [UserController::class, 'profileUpdate'])->name('profile.update');
    Route::post('profile/password', [UserController::class, 'changePassword'])->name('profile.password.change');

    // for notice
    Route::middleware(['role:Admin'])->group(function () {
        Route::apiResource('notices', NoticeController::class);
        Route::post('notices-update/{id}', [NoticeController::class, 'updateNotice'])->name('notices.update');
        Route::post('notices/draft', [NoticeController::class, 'draftNotices'])->name('notices.draft');
        Route::get('/drafts-list', [NoticeController::class, 'draftNoticesList'])->name('notices.drafts.list');
        Route::post('/notices/{id}/publish', [NoticeController::class, 'publish'])->name('notices.publish');

        // for department based user
        Route::get('notices/department-users/{id}', [DepartmentController::class, 'departmentBasedUsers'])->name('notices.department.users');

        // notice search
        Route::get('notices-search', [NoticeController::class, 'noticeSearch'])->name('notices.search');

        // for meeting
        Route::apiResource('meetings', MeetingController::class);
        // Extra endpoints if you want quick toggles:
        Route::patch('meetings/{meeting}/toggle', [MeetingController::class, 'toggle'])->name('meetings.toggle');

        // for meeting details propagations
        Route::apiResource('meetings-details', MeetingDetailController::class);

        Route::get('v1/meetings/free', [MeetingAvailabilityController::class, 'free'])->name('meetings.free');

        // for notice templates
        Route::get   ('/notice-templates',        [NoticeTemplateController::class, 'index']);
        Route::post  ('/notice-templates',        [NoticeTemplateController::class, 'store']);
        Route::get   ('/notice-templates/{id}',   [NoticeTemplateController::class, 'show']);
        Route::match(['put','patch'], '/notice-templates/{id}', [NoticeTemplateController::class, 'update']);
        Route::delete('/notice-templates/{id}',   [NoticeTemplateController::class, 'destroy']);

        // JSON ONLY — no PDF here
        // Route::get('/notice-templates/{id}', [NoticeTemplateController::class, 'showApi'])
        //     ->name('api.notice.show');

        // routes/api.php
        Route::get('/notice-templates/{id}/pdf', [NoticeTemplateController::class, 'download'])
            ->name('api.notice.pdf');
    });
});

// dashboard routes
Route::middleware(['auth:sanctum', 'role:Super Admin'])->get('dashboard/superadmin', [DashboardController::class, 'superadmin'])->name('dashboard.superadmin');
Route::middleware(['auth:sanctum', 'role:Admin'])->get('dashboard/admin', [DashboardController::class, 'admin'])->name('dashboard.admin');
Route::middleware(['auth:sanctum', 'role:User'])->get('dashboard/user', [DashboardController::class, 'user'])->name('dashboard.user');

Route::middleware(['auth:sanctum', 'role:Super Admin'])->group(function () {
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('permissions', PermissionController::class);
    Route::apiResource('departments', DepartmentController::class)->except(['index']);
    Route::apiResource('designations', DesignationController::class)->except(['index']);
    Route::apiResource('users', UserController::class)->except(['index']);
    //active user
    Route::post('/active-users/{id}', [UserController::class, 'activeUsers'])->name('active-users');

    //department active
    Route::post('/active-departments/{id}', [DepartmentController::class, 'restore'])->name('active-departments');

    //designation active
    Route::post('/active-designations/{id}', [DesignationController::class, 'restore'])->name('active-designations');

    // deactivate user
    Route::get('/deactivate-users', [UserController::class, 'deActiveUsers'])->name('deactivate-users');

    // deactivate department list
    Route::get('/deactivate-departments', [DepartmentController::class, 'deActiveDepartments'])->name('deactivate-departments');

    // deactivate designation list
    Route::get('/deactivate-designations', [DesignationController::class, 'deActiveDesignations'])->name('deactivate-designations');

});

// Allow all authenticated users to view list of departments and designations
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::get('designations', [DesignationController::class, 'index']);
    Route::get('users', [UserController::class, 'index']);

    // department list
    Route::get('/departments-data', [DepartmentController::class, 'departmentList'])->name('departments.list');

    // designation list
    Route::get('/designations-data', [DesignationController::class, 'designationList'])->name('designations.list');
});

Route::prefix('auth/password')->group(function () {
    Route::post('forgot', [PasswordResetApiController::class, 'requestOtp']);     // Step 1
    Route::post('verify-otp', [PasswordResetApiController::class, 'verifyOtp']);   // Step 2
    Route::post('reset', [PasswordResetApiController::class, 'resetPassword']);    // Step 3
});

// Notification routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get   ('/notifications',                [NotificationController::class, 'index']);
    Route::get   ('/notifications/unread-count',   [NotificationController::class, 'unreadCount']);
    Route::post  ('/notifications/mark-read',      [NotificationController::class, 'markManyRead']);   // bulk
    Route::post  ('/notifications/{id}/read',      [NotificationController::class, 'markRead']);       // single
    Route::post  ('/notifications/read-all',       [NotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{id}',           [NotificationController::class, 'destroy']);
    Route::delete('/notifications',                [NotificationController::class, 'clear']);          // delete all
});


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
