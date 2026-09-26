<?php

use App\Http\Controllers\Admin\AccessMatrixController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisposalController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\Master\AssetCategoryController;
use App\Http\Controllers\Master\DepartmentController;
use App\Http\Controllers\Master\EmployeeController;
use App\Http\Controllers\Master\LocationController;
use App\Http\Controllers\Master\VendorController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// ----------------------------------------------------------------- Autentikasi
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/dashboard/widgets/{widget}', [DashboardController::class, 'widget'])->middleware('permission:dashboard.view')->name('dashboard.widget');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');

    // ------------------------------------------------------------- Asset Register
    Route::prefix('assets')->name('assets.')->controller(AssetController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:asset.view')->name('index');
        Route::get('/export', 'export')->middleware('permission:report.view')->name('export');
        Route::get('/search', 'search')->middleware('permission:asset.view|assignment.create|loan.create|disposal.create')->name('search');
        Route::get('/create', 'create')->middleware('permission:asset.create')->name('create');
        Route::post('/', 'store')->middleware('permission:asset.create')->name('store');
        Route::get('/{asset}', 'show')->middleware('permission:asset.view')->name('show');
        Route::get('/{asset}/edit', 'edit')->middleware('permission:asset.update')->name('edit');
        Route::put('/{asset}', 'update')->middleware('permission:asset.update')->name('update');
        Route::post('/{asset}/status', 'changeStatus')->middleware('permission:asset.status')->name('status');
    });

    // ------------------------------------------------------------- Approval Inbox
    Route::middleware('permission:approval.decide')->prefix('approvals')->name('approvals.')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->name('index');
        Route::post('/{step}/approve', [ApprovalController::class, 'approve'])->name('approve');
        Route::post('/{step}/reject', [ApprovalController::class, 'reject'])->name('reject');
    });

    // ------------------------------------------------------------- Procurement
    Route::prefix('procurements')->name('procurements.')->controller(ProcurementController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{procurement}', 'show')->name('show');
        Route::get('/{procurement}/edit', 'edit')->name('edit');
        Route::put('/{procurement}', 'update')->name('update');
        Route::post('/{procurement}/submit', 'submit')->name('submit');
        Route::post('/{procurement}/cancel', 'cancel')->name('cancel');
        Route::post('/{procurement}/order', 'order')->name('order');
        Route::get('/{procurement}/receive', 'receiveForm')->name('receive-form');
        Route::post('/{procurement}/receive', 'receive')->name('receive');
        Route::post('/{procurement}/close', 'close')->name('close');
    });

    // ------------------------------------------------------------- Assignment
    Route::prefix('assignments')->name('assignments.')->controller(AssignmentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/active', 'active')->name('active');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{assignment}', 'show')->name('show');
        Route::post('/{assignment}/submit', 'submit')->name('submit');
        Route::post('/{assignment}/cancel', 'cancel')->name('cancel');
        Route::post('/{assignment}/handover', 'handover')->name('handover');
        Route::post('/return/{assetAssignment}', 'returnAsset')->name('return');
    });

    // ------------------------------------------------------------- Peminjaman
    Route::prefix('loans')->name('loans.')->controller(LoanController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/active', 'active')->name('active');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{loan}', 'show')->name('show');
        Route::post('/{loan}/submit', 'submit')->name('submit');
        Route::post('/{loan}/cancel', 'cancel')->name('cancel');
        Route::post('/{loan}/checkout', 'checkout')->name('checkout');
        Route::post('/items/{assetLoan}/return', 'returnLoan')->name('return');
        Route::post('/items/{assetLoan}/extend', 'extend')->name('extend');
        Route::post('/extensions/{extension}/cancel', 'cancelExtension')->name('extensions.cancel');
    });

    // ------------------------------------------------------------- Disposal
    Route::prefix('disposals')->name('disposals.')->controller(DisposalController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{disposal}', 'show')->name('show');
        Route::post('/{disposal}/submit', 'submit')->name('submit');
        Route::post('/{disposal}/cancel', 'cancel')->name('cancel');
        Route::post('/{disposal}/execute', 'execute')->name('execute');
    });

    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:report.view')->name('reports.index');

    // ------------------------------------------------------------- Master data
    Route::prefix('masters')->name('masters.')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])->middleware('permission:employee.view')->name('employees.index');
        Route::middleware('permission:employee.manage')->group(function () {
            Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
            Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
            Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
            Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        });
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->middleware('permission:employee.view')->name('employees.show');

        foreach ([
            'departments' => DepartmentController::class,
            'locations' => LocationController::class,
            'categories' => AssetCategoryController::class,
            'vendors' => VendorController::class,
        ] as $name => $controller) {
            Route::get($name, [$controller, 'index'])->middleware('permission:master.view|master.manage')->name("{$name}.index");
            Route::resource($name, $controller)->except(['index', 'show'])->middleware('permission:master.manage');
        }
    });

    // ------------------------------------------------------------- Administrasi
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware('permission:user.manage');
        Route::resource('roles', RoleController::class)->except(['show'])->middleware('permission:role.manage');
        Route::get('access-matrix', [AccessMatrixController::class, 'index'])->middleware('permission:role.manage')->name('access.index');
        Route::put('access-matrix', [AccessMatrixController::class, 'update'])->middleware('permission:role.manage')->name('access.update');
        Route::get('settings', [SettingController::class, 'edit'])->middleware('permission:setting.manage')->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->middleware('permission:setting.manage')->name('settings.update');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');
    });
});
