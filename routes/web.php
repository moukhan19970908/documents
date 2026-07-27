<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\BitrixSocialiteController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\DocumentRelatedFileController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EmployeesController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ProcedureController;
use App\Http\Controllers\Procedure\ProcedureTaskController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\Admin\ProcedureTemplateController;
use App\Http\Controllers\RequestsController;
use App\Http\Controllers\Admin\KnowledgeController as AdminKnowledgeController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\AccessControlController;
use App\Http\Controllers\Admin\BlankTemplateController;
use App\Http\Controllers\Admin\NumberingController;
use App\Http\Controllers\Admin\DocumentTypeController;
use App\Http\Controllers\Admin\ScenarioController;
use App\Http\Controllers\Admin\WorkflowFolderController;
use App\Http\Controllers\Admin\ApprovalRouteController;
use App\Http\Controllers\Admin\ExternalParticipantController;
use App\Http\Controllers\Trip\TripRequestController;
use App\Http\Controllers\Trip\TripApprovalController;
use App\Http\Controllers\Trip\TripRegistryController;
use App\Http\Controllers\Trip\TripTaskController;
use App\Http\Controllers\Vacation\VacationRequestController;
use App\Http\Controllers\Vacation\VacationApprovalController;
use App\Http\Controllers\Vacation\VacationRegistryController;

use App\Http\Controllers\AgreementController;

// Auth
Route::redirect('/', '/login');
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::get('/auth/bitrix24', [BitrixSocialiteController::class, 'redirect'])->name('auth.bitrix24');
Route::get('/auth/bitrix24/callback', [BitrixSocialiteController::class, 'callback'])->name('auth.bitrix24.callback');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Agreement (auth required, but no agreement check yet)
Route::middleware('auth')->group(function () {
    Route::get('/agreement', [AgreementController::class, 'show'])->name('agreement.show');
    Route::post('/agreement/accept', [AgreementController::class, 'accept'])->name('agreement.accept');
    Route::get('/agreement/decline', [AgreementController::class, 'decline'])->name('agreement.decline');
});

Route::middleware(['auth', 'agreement', 'audit', \App\Http\Middleware\ExternalRestriction::class])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Обратная связь (ТЗ 29)
    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::get('/feedback/create', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
    Route::get('/feedback/{feedback}', [FeedbackController::class, 'show'])->name('feedback.show');
    Route::post('/feedback/{feedback}/messages', [FeedbackController::class, 'reply'])->name('feedback.reply');
    Route::patch('/feedback/{feedback}/status', [FeedbackController::class, 'updateStatus'])->name('feedback.status');

    // База знаний (чтение — всем авторизованным)
    Route::get('knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
    Route::get('knowledge/{material}', [KnowledgeController::class, 'show'])->name('knowledge.show');

    // Заявки — единый хаб (ТЗ 18): Отпуска / Командировки / Иное
    Route::get('requests', [RequestsController::class, 'index'])->name('requests.index');

    // Поручения (свободное дерево, ТЗ 17)
    Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
    Route::post('assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    Route::get('assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
    Route::post('assignments/{assignment}/sub', [AssignmentController::class, 'storeSub'])->name('assignments.sub');
    Route::post('assignments/{assignment}/start', [AssignmentController::class, 'start'])->name('assignments.start');
    Route::post('assignments/{assignment}/contribute', [AssignmentController::class, 'contribute'])->name('assignments.contribute');
    Route::post('assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->name('assignments.submit');
    Route::post('assignments/{assignment}/accept', [AssignmentController::class, 'accept'])->name('assignments.accept');
    Route::post('assignments/{assignment}/return', [AssignmentController::class, 'returnToRework'])->name('assignments.return');
    Route::post('assignments/{assignment}/extend', [AssignmentController::class, 'extendDeadline'])->name('assignments.extend');
    Route::post('assignments/{assignment}/extend/approve', [AssignmentController::class, 'approveExtension'])->name('assignments.extend.approve');
    Route::post('assignments/{assignment}/extend/reject', [AssignmentController::class, 'rejectExtension'])->name('assignments.extend.reject');
    Route::delete('assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
    Route::get('assignment-files/{file}', [AssignmentController::class, 'file'])->name('assignments.file');

    // Процедуры (сценарное дерево с чек-листом и веером задач, ТЗ 19)
    Route::prefix('procedures')->name('procedures.')->group(function () {
        Route::get('/', [ProcedureController::class, 'index'])->name('index');
        Route::get('/create', [ProcedureController::class, 'create'])->name('create');
        Route::post('/', [ProcedureController::class, 'store'])->name('store');
        // Порождённые задачи (литеральные пути — до wildcard /{procedure})
        Route::get('/tasks', [ProcedureTaskController::class, 'index'])->name('tasks.index');
        Route::post('/tasks/{task}/take', [ProcedureTaskController::class, 'take'])->name('tasks.take');
        Route::post('/tasks/{task}/submit', [ProcedureTaskController::class, 'submit'])->name('tasks.submit');
        Route::post('/tasks/{task}/accept', [ProcedureTaskController::class, 'accept'])->name('tasks.accept');
        Route::post('/tasks/{task}/return', [ProcedureTaskController::class, 'returnTask'])->name('tasks.return');
        Route::post('/tasks/{task}/deadline', [ProcedureTaskController::class, 'changeDeadline'])->name('tasks.deadline');
        Route::get('/task-files/{file}', [ProcedureTaskController::class, 'file'])->name('tasks.file');
        Route::get('/files/{file}', [ProcedureController::class, 'file'])->name('file');
        // Карточка процедуры и действия по этапам
        Route::get('/{procedure}', [ProcedureController::class, 'show'])->name('show');
        Route::post('/{procedure}/stage', [ProcedureController::class, 'submitStage'])->name('stage');
        Route::post('/{procedure}/branch', [ProcedureController::class, 'branch'])->name('branch');
        Route::post('/{procedure}/checklist', [ProcedureController::class, 'submitChecklist'])->name('checklist');
        Route::delete('/{procedure}', [ProcedureController::class, 'destroy'])->name('destroy');
    });

    // Проверки (свободное дерево, отдельный раздел [ПРВ], ТЗ 20)
    Route::prefix('inspections')->name('inspections.')->group(function () {
        Route::get('/', [InspectionController::class, 'index'])->name('index');
        Route::get('/create', [InspectionController::class, 'create'])->name('create');
        Route::post('/', [InspectionController::class, 'store'])->name('store');
        Route::get('/files/{file}', [InspectionController::class, 'file'])->name('file');
        Route::get('/{inspection}', [InspectionController::class, 'show'])->name('show');
        Route::post('/{inspection}/sub', [InspectionController::class, 'storeSub'])->name('sub');
        Route::post('/{inspection}/start', [InspectionController::class, 'start'])->name('start');
        Route::post('/{inspection}/contribute', [InspectionController::class, 'contribute'])->name('contribute');
        Route::post('/{inspection}/submit', [InspectionController::class, 'submit'])->name('submit');
        Route::post('/{inspection}/accept', [InspectionController::class, 'accept'])->name('accept');
        Route::post('/{inspection}/return', [InspectionController::class, 'returnToRework'])->name('return');
        Route::post('/{inspection}/spawn-assignment', [InspectionController::class, 'spawnAssignment'])->name('spawn-assignment');
        Route::delete('/{inspection}', [InspectionController::class, 'destroy'])->name('destroy');
    });

    // Documents
    Route::resource('documents', DocumentController::class);
    Route::put('documents/{document}/blank', [DocumentController::class, 'updateBlank'])->name('documents.blank.update');
    Route::post('documents/{document}/start-approval', [ApprovalController::class, 'start'])->name('documents.start-approval');
    Route::post('documents/{document}/approve', [ApprovalController::class, 'approve'])->name('documents.approve');
    Route::post('documents/{document}/reject', [ApprovalController::class, 'reject'])->name('documents.reject');
    Route::post('documents/{document}/resubmit', [ApprovalController::class, 'resubmit'])->name('documents.resubmit');
    Route::post('documents/{document}/request-changes', [ApprovalController::class, 'requestChanges'])->name('documents.request-changes');
    Route::post('documents/{document}/delegate', [ApprovalController::class, 'delegate'])->name('documents.delegate');
    Route::post('documents/{document}/process-approve', [ApprovalController::class, 'processApprove'])->name('documents.process-approve');
    Route::post('documents/{document}/process-reject', [ApprovalController::class, 'processReject'])->name('documents.process-reject');
    Route::post('documents/{document}/cancel-approval', [ApprovalController::class, 'cancelApproval'])->name('documents.cancel-approval');
    Route::post('documents/{document}/decide/{action}', [ApprovalController::class, 'decide'])
        ->whereIn('action', ['opinion_yes', 'opinion_no', 'acknowledge', 'accept', 'execute'])
        ->name('documents.decide');
    Route::get('documents/{document}/approval-sheet', [ApprovalController::class, 'approvalSheet'])->name('documents.approval-sheet');
    Route::post('documents/{document}/notes', [DocumentController::class, 'storeNote'])->name('documents.notes.store');

    // Files
    Route::post('documents/{document}/files', [DocumentFileController::class, 'store'])->name('documents.files.store');
    Route::get('documents/{document}/files/{file}/download', [DocumentFileController::class, 'download'])->name('documents.files.download');
    Route::get('documents/{document}/files/{file}/preview', [DocumentFileController::class, 'preview'])->name('documents.files.preview');

    // Related Files
    Route::post('documents/{document}/related-files', [DocumentRelatedFileController::class, 'store'])->name('documents.related-files.store');
    Route::get('documents/{document}/related-files/{file}/download', [DocumentRelatedFileController::class, 'download'])->name('documents.related-files.download');
    Route::get('documents/{document}/related-files/{file}/preview', [DocumentRelatedFileController::class, 'preview'])->name('documents.related-files.preview');
    Route::delete('documents/{document}/related-files/{file}', [DocumentRelatedFileController::class, 'destroy'])->name('documents.related-files.destroy');

    // Приказы
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
    Route::put('orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::post('orders/{order}/publish', [OrderController::class, 'publish'])->name('orders.publish');
    Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    Route::post('orders/{order}/acknowledge', [OrderController::class, 'acknowledge'])->name('orders.acknowledge');
    Route::post('orders/{order}/remind', [OrderController::class, 'remind'])->name('orders.remind');
    Route::get('orders/{order}/file', [OrderController::class, 'file'])->name('orders.file');
    Route::get('orders/{order}/pdf', [OrderController::class, 'pdf'])->name('orders.pdf');
    Route::post('orders/{order}/approve', [OrderController::class, 'approve'])->name('orders.approve');
    Route::post('orders/{order}/reject', [OrderController::class, 'reject'])->name('orders.reject');

    // Кредитный комитет — документы процесса credit_committee (тот же движок, что и документооборот)
    Route::get('credit-committee', [DocumentController::class, 'index'])->defaults('process', 'credit_committee')->name('credit-committee.index');
    Route::get('credit-committee/create', [DocumentController::class, 'create'])->defaults('process', 'credit_committee')->name('credit-committee.create');

    // Chat
    Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
    Route::get('chats/{chat}', [ChatController::class, 'show'])->name('chats.show');
    Route::get('chats/{chat}/messages', [ChatController::class, 'messages'])->name('chats.messages');
    Route::post('chats/{chat}/messages', [ChatController::class, 'store'])->name('chats.messages.store');
    Route::post('chats/{chat}/read', [ChatController::class, 'markRead'])->name('chats.read');
    Route::post('chats/{chat}/favorite', [ChatController::class, 'toggleFavorite'])->name('chats.favorite');
    Route::get('chats/{chat}/attachments/{message}/download', [ChatController::class, 'downloadAttachment'])->name('chats.attachment.download');
    Route::get('chats/{chat}/attachments/{message}/preview', [ChatController::class, 'previewAttachment'])->name('chats.attachment.preview');

    // Archive
    Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
    Route::get('/archive/{archived}/file', [ArchiveController::class, 'file'])->name('archive.file');
    Route::get('/archive/{archived}/sheet', [ArchiveController::class, 'sheet'])->name('archive.sheet');
    Route::get('/archive/{archived}', [ArchiveController::class, 'show'])->name('archive.show');

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

    // Web Push subscriptions
    Route::post('/push/subscribe', [\App\Http\Controllers\PushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [\App\Http\Controllers\PushController::class, 'unsubscribe'])->name('push.unsubscribe');

    // Employees org chart
    Route::get('/employees', [EmployeesController::class, 'index'])->name('employees.index');

    // Admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('access-control', AccessControlController::class)->name('access-control.index');
        Route::post('access-control/users/{user}/workflow-access', [AccessControlController::class, 'updateUserWorkflowAccess'])->name('access-control.users.workflow-access');
        Route::post('access-control/departments/{department}/workflow-access', [AccessControlController::class, 'updateDeptWorkflowAccess'])->name('access-control.depts.workflow-access');
        Route::post('access-control/users/{user}/tasks-access', [AccessControlController::class, 'updateUserTasksAccess'])->name('access-control.users.tasks-access');
        Route::post('access-control/departments/{department}/tasks-access', [AccessControlController::class, 'updateDeptTasksAccess'])->name('access-control.depts.tasks-access');
        Route::post('access-control/users/{user}/archive-access', [AccessControlController::class, 'updateUserArchiveAccess'])->name('access-control.users.archive-access');
        Route::post('access-control/departments/{department}/archive-access', [AccessControlController::class, 'updateDeptArchiveAccess'])->name('access-control.depts.archive-access');
        Route::get('roles/matrix', [RoleController::class, 'matrix'])->name('roles.matrix');
        Route::put('roles/matrix', [RoleController::class, 'updateMatrix'])->name('roles.matrix.update');
        Route::get('roles/watchers', [RoleController::class, 'watchers'])->name('roles.watchers');
        Route::post('roles/watchers', [RoleController::class, 'storeWatcher'])->name('roles.watchers.store');
        Route::put('roles/watchers/{watcher}', [RoleController::class, 'updateWatcher'])->name('roles.watchers.update');
        Route::delete('roles/watchers/{watcher}', [RoleController::class, 'destroyWatcher'])->name('roles.watchers.destroy');
        Route::get('roles/personal', [RoleController::class, 'personal'])->name('roles.personal');
        Route::get('roles/directions', [RoleController::class, 'directions'])->name('roles.directions');
        Route::post('roles/directions', [RoleController::class, 'storeDirection'])->name('roles.directions.store');
        Route::delete('roles/directions/{department}', [RoleController::class, 'destroyDirection'])->name('roles.directions.destroy');
        Route::post('roles/directions/{department}/departments', [RoleController::class, 'addDirectionDepartment'])->name('roles.directions.departments.add');
        Route::delete('roles/directions/{department}/departments/{child}', [RoleController::class, 'removeDirectionDepartment'])->name('roles.directions.departments.remove');
        Route::patch('roles/directions/{department}/cross-visibility', [RoleController::class, 'updateDirectionCrossVisibility'])->name('roles.directions.cross-visibility');
        Route::resource('roles', RoleController::class)->except(['show']);
        Route::post('roles/{role}/duplicate', [RoleController::class, 'duplicate'])->name('roles.duplicate');
        Route::resource('users', UserController::class);
        Route::resource('external-participants', ExternalParticipantController::class)
            ->only(['index', 'create', 'store', 'destroy']);
        Route::resource('departments', DepartmentController::class);
        Route::resource('scenarios', ScenarioController::class)->except(['show']);
        Route::put('scenarios/{scenario}/route', [ScenarioController::class, 'updateRoute'])->name('scenarios.route');
        Route::post('scenarios/{scenario}/publish', [ScenarioController::class, 'publish'])->name('scenarios.publish');
        Route::resource('document-types', DocumentTypeController::class)->except(['show']);
        Route::post('document-types/{document_type}/duplicate', [DocumentTypeController::class, 'duplicate'])->name('document-types.duplicate');
        Route::patch('document-types/{document_type}/toggle', [DocumentTypeController::class, 'toggle'])->name('document-types.toggle');
        Route::patch('document-types/{document_type}/counters/{counter}', [DocumentTypeController::class, 'updateCounter'])->name('document-types.counters.update');
        Route::get('assignments/settings', [\App\Http\Controllers\Admin\AssignmentSettingController::class, 'edit'])->name('assignments.settings');
        Route::put('assignments/settings', [\App\Http\Controllers\Admin\AssignmentSettingController::class, 'update'])->name('assignments.settings.update');
        Route::get('trip-tasks/settings', [\App\Http\Controllers\Admin\TripTaskSettingController::class, 'edit'])->name('trip-tasks.settings');
        Route::put('trip-tasks/settings', [\App\Http\Controllers\Admin\TripTaskSettingController::class, 'update'])->name('trip-tasks.settings.update');
        // Шаблоны процедур (сценарии, ТЗ 19)
        Route::get('procedures', [ProcedureTemplateController::class, 'index'])->name('procedures.index');
        Route::post('procedures', [ProcedureTemplateController::class, 'store'])->name('procedures.store');
        Route::get('procedures/{template}/edit', [ProcedureTemplateController::class, 'edit'])->name('procedures.edit');
        Route::put('procedures/{template}', [ProcedureTemplateController::class, 'update'])->name('procedures.update');
        Route::delete('procedures/{template}', [ProcedureTemplateController::class, 'destroy'])->name('procedures.destroy');
        Route::post('procedures/{template}/stages', [ProcedureTemplateController::class, 'storeStage'])->name('procedures.stages.store');
        Route::put('procedures/{template}/stages/{stage}', [ProcedureTemplateController::class, 'updateStage'])->name('procedures.stages.update');
        Route::delete('procedures/{template}/stages/{stage}', [ProcedureTemplateController::class, 'destroyStage'])->name('procedures.stages.destroy');
        Route::post('procedures/{template}/items', [ProcedureTemplateController::class, 'storeItem'])->name('procedures.items.store');
        Route::put('procedures/{template}/items/{item}', [ProcedureTemplateController::class, 'updateItem'])->name('procedures.items.update');
        Route::delete('procedures/{template}/items/{item}', [ProcedureTemplateController::class, 'destroyItem'])->name('procedures.items.destroy');
        Route::get('numbering', [NumberingController::class, 'index'])->name('numbering.index');
        Route::post('numbering/custom', [NumberingController::class, 'storeCustom'])->name('numbering.custom.store');
        Route::put('numbering/custom/{numerator}', [NumberingController::class, 'updateCustom'])->name('numbering.custom.update');
        Route::delete('numbering/custom/{numerator}', [NumberingController::class, 'destroyCustom'])->name('numbering.custom.destroy');
        Route::put('numbering/{numerator}', [NumberingController::class, 'update'])->name('numbering.update');
        Route::resource('blank-templates', BlankTemplateController::class)->except(['show']);
        Route::patch('blank-templates/{blank_template}/toggle', [BlankTemplateController::class, 'toggle'])->name('blank-templates.toggle');

        // База знаний — управление материалами и доступом
        Route::get('knowledge/create', [AdminKnowledgeController::class, 'create'])->name('knowledge.create');
        Route::post('knowledge', [AdminKnowledgeController::class, 'store'])->name('knowledge.store');
        Route::get('knowledge/{material}/edit', [AdminKnowledgeController::class, 'edit'])->name('knowledge.edit');
        Route::put('knowledge/{material}', [AdminKnowledgeController::class, 'update'])->name('knowledge.update');
        Route::delete('knowledge/{material}', [AdminKnowledgeController::class, 'destroy'])->name('knowledge.destroy');
        Route::get('knowledge/{material}/access', [AdminKnowledgeController::class, 'access'])->name('knowledge.access');
        Route::put('knowledge/{material}/access', [AdminKnowledgeController::class, 'updateAccess'])->name('knowledge.access.update');
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
        Route::post('/registries/{registry}/items/{item}/return', [TripRegistryController::class, 'returnItem'])->name('registries.items.return');
        // Порождаемые задания командировок (ТЗ 18.3)
        Route::get('/tasks', [TripTaskController::class, 'index'])->name('tasks.index');
        Route::post('/tasks/{task}/take', [TripTaskController::class, 'take'])->name('tasks.take');
        Route::post('/tasks/{task}/complete', [TripTaskController::class, 'complete'])->name('tasks.complete');
        Route::get('/task-files/{file}', [TripTaskController::class, 'file'])->name('tasks.file');
        Route::get('/{trip}', [TripRequestController::class, 'show'])->name('show');
        Route::get('/{trip}/edit', [TripRequestController::class, 'edit'])->name('edit');
        Route::put('/{trip}', [TripRequestController::class, 'update'])->name('update');
        Route::delete('/{trip}', [TripRequestController::class, 'destroy'])->name('destroy');
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
        Route::get('/registries', [VacationRegistryController::class, 'index'])->name('registries.index');
        Route::post('/registries', [VacationRegistryController::class, 'store'])->name('registries.store');
        Route::get('/registries/{registry}', [VacationRegistryController::class, 'show'])->name('registries.show');
        Route::post('/registries/{registry}/send', [VacationRegistryController::class, 'send'])->name('registries.send');
        Route::post('/registries/{registry}/approve', [VacationRegistryController::class, 'approve'])->name('registries.approve');
        Route::post('/registries/{registry}/reject', [VacationRegistryController::class, 'reject'])->name('registries.reject');
        Route::post('/registries/{registry}/accounting', [VacationRegistryController::class, 'sendToAccounting'])->name('registries.send-accounting');
        Route::post('/registries/{registry}/accept', [VacationRegistryController::class, 'accept'])->name('registries.accept');
        Route::post('/registries/{registry}/items/{item}/return', [VacationRegistryController::class, 'returnItem'])->name('registries.items.return');
        Route::get('/{vacation}', [VacationRequestController::class, 'show'])->name('show');
        Route::get('/{vacation}/edit', [VacationRequestController::class, 'edit'])->name('edit');
        Route::put('/{vacation}', [VacationRequestController::class, 'update'])->name('update');
        Route::delete('/{vacation}', [VacationRequestController::class, 'destroy'])->name('destroy');
        Route::post('/{vacation}/approve', [VacationApprovalController::class, 'approve'])->name('approve');
        Route::post('/{vacation}/reject', [VacationApprovalController::class, 'reject'])->name('reject');
        Route::post('/{vacation}/revision', [VacationApprovalController::class, 'revision'])->name('revision');
    });
});
