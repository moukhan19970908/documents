<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\BitrixSocialiteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EmployeesController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DocumentTypeController;

// Auth
Route::redirect('/', '/login');
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::get('/auth/bitrix24', [BitrixSocialiteController::class, 'redirect'])->name('auth.bitrix24');
Route::get('/auth/bitrix24/callback', [BitrixSocialiteController::class, 'callback'])->name('auth.bitrix24.callback');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware(['auth', 'audit'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Documents
    Route::resource('documents', DocumentController::class);
    Route::post('documents/{document}/start-approval', [ApprovalController::class, 'start'])->name('documents.start-approval');
    Route::post('documents/{document}/approve', [ApprovalController::class, 'approve'])->name('documents.approve');
    Route::post('documents/{document}/reject', [ApprovalController::class, 'reject'])->name('documents.reject');
    Route::post('documents/{document}/resubmit', [ApprovalController::class, 'resubmit'])->name('documents.resubmit');
    Route::post('documents/{document}/request-changes', [ApprovalController::class, 'requestChanges'])->name('documents.request-changes');
    Route::post('documents/{document}/delegate', [ApprovalController::class, 'delegate'])->name('documents.delegate');
    Route::get('documents/{document}/approval-sheet', [ApprovalController::class, 'approvalSheet'])->name('documents.approval-sheet');

    // Files
    Route::post('documents/{document}/files', [DocumentFileController::class, 'store'])->name('documents.files.store');
    Route::get('documents/{document}/files/{file}/download', [DocumentFileController::class, 'download'])->name('documents.files.download');
    Route::get('documents/{document}/files/{file}/preview', [DocumentFileController::class, 'preview'])->name('documents.files.preview');

    // Archive
    Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');

    // Workflows
    Route::resource('workflows', WorkflowController::class);
    Route::get('workflows/{workflow}/builder', [WorkflowController::class, 'builder'])->name('workflows.builder');

    // Tasks
    Route::get('/tasks', [DocumentController::class, 'tasks'])->name('tasks');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Employees org chart
    Route::get('/employees', [EmployeesController::class, 'index'])->name('employees.index');

    // Admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class);
        Route::resource('departments', DepartmentController::class);
        Route::resource('document-types', DocumentTypeController::class);
    });
});
