<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\BitrixSocialiteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\DocumentRelatedFileController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EmployeesController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DocumentTypeController;
use App\Http\Controllers\Admin\WorkflowFolderController;
use App\Http\Controllers\Admin\ApprovalRouteController;
use App\Http\Controllers\Trip\TripRequestController;
use App\Http\Controllers\Trip\TripApprovalController;
use App\Http\Controllers\Trip\TripRegistryController;
use App\Http\Controllers\Vacation\VacationRequestController;
use App\Http\Controllers\Vacation\VacationApprovalController;

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

    // Related Files
    Route::post('documents/{document}/related-files', [DocumentRelatedFileController::class, 'store'])->name('documents.related-files.store');
    Route::get('documents/{document}/related-files/{file}/download', [DocumentRelatedFileController::class, 'download'])->name('documents.related-files.download');
    Route::get('documents/{document}/related-files/{file}/preview', [DocumentRelatedFileController::class, 'preview'])->name('documents.related-files.preview');
    Route::delete('documents/{document}/related-files/{file}', [DocumentRelatedFileController::class, 'destroy'])->name('documents.related-files.destroy');

    // Chat
    Route::get('chats/{chat}/messages', [ChatController::class, 'messages'])->name('chats.messages');
    Route::post('chats/{chat}/messages', [ChatController::class, 'store'])->name('chats.messages.store');

    // Archive
    Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');

    // Workflows
    Route::resource('workflows', WorkflowController::class);
    Route::get('workflows/{workflow}/builder', [WorkflowController::class, 'builder'])->name('workflows.builder');
    Route::get('api/workflows', [WorkflowController::class, 'apiIndex'])->name('api.workflows');

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
        Route::resource('workflow-folders', WorkflowFolderController::class);
        Route::resource('approval-routes', ApprovalRouteController::class);
        Route::patch('approval-routes/{approval_route}/toggle', [ApprovalRouteController::class, 'toggle'])->name('approval-routes.toggle');
    });

    // Trips
    Route::prefix('trips')->name('trips.')->group(function () {
        Route::get('/', [TripRequestController::class, 'index'])->name('index');
        Route::get('/create', [TripRequestController::class, 'create'])->name('create');
        Route::post('/', [TripRequestController::class, 'store'])->name('store');
        Route::get('/approvals', [TripApprovalController::class, 'index'])->name('approvals');
        Route::get('/registries', [TripRegistryController::class, 'index'])->name('registries.index');
        Route::post('/registries', [TripRegistryController::class, 'store'])->name('registries.store');
        Route::get('/registries/{registry}', [TripRegistryController::class, 'show'])->name('registries.show');
        Route::post('/registries/{registry}/send', [TripRegistryController::class, 'send'])->name('registries.send');
        Route::post('/registries/{registry}/approve', [TripRegistryController::class, 'approve'])->name('registries.approve');
        Route::post('/registries/{registry}/reject', [TripRegistryController::class, 'reject'])->name('registries.reject');
        Route::post('/registries/{registry}/accounting', [TripRegistryController::class, 'sendToAccounting'])->name('registries.send-accounting');
        Route::post('/registries/{registry}/accept', [TripRegistryController::class, 'accept'])->name('registries.accept');
        Route::get('/{trip}', [TripRequestController::class, 'show'])->name('show');
        Route::get('/{trip}/edit', [TripRequestController::class, 'edit'])->name('edit');
        Route::put('/{trip}', [TripRequestController::class, 'update'])->name('update');
        Route::post('/{trip}/approve', [TripApprovalController::class, 'approve'])->name('approve');
        Route::post('/{trip}/reject', [TripApprovalController::class, 'reject'])->name('reject');
        Route::post('/{trip}/revision', [TripApprovalController::class, 'revision'])->name('revision');
    });

    // Vacations
    Route::prefix('vacations')->name('vacations.')->group(function () {
        Route::get('/', [VacationRequestController::class, 'index'])->name('index');
        Route::get('/create', [VacationRequestController::class, 'create'])->name('create');
        Route::post('/', [VacationRequestController::class, 'store'])->name('store');
        Route::get('/approvals', [VacationApprovalController::class, 'index'])->name('approvals');
        Route::get('/{vacation}', [VacationRequestController::class, 'show'])->name('show');
        Route::get('/{vacation}/edit', [VacationRequestController::class, 'edit'])->name('edit');
        Route::put('/{vacation}', [VacationRequestController::class, 'update'])->name('update');
        Route::post('/{vacation}/approve', [VacationApprovalController::class, 'approve'])->name('approve');
        Route::post('/{vacation}/reject', [VacationApprovalController::class, 'reject'])->name('reject');
        Route::post('/{vacation}/revision', [VacationApprovalController::class, 'revision'])->name('revision');
    });
});
