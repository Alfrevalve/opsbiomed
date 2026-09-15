<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\CatalogImportController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\CostZeroApprovalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DoctorMasterController;
use App\Http\Controllers\DocumentEvidenceController;
use App\Http\Controllers\FailureController;
use App\Http\Controllers\InstitutionMasterController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryForecastController;
use App\Http\Controllers\ManualAdminController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\OperationalAlertController;
use App\Http\Controllers\ProductPriceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StockCoverageController;
use App\Http\Controllers\SurgeryCaseController;
use App\Http\Controllers\TraceController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/dashboard', fn () => redirect()->route('dashboard.ops'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard/ops', DashboardController::class)->name('dashboard.ops');

    Route::prefix('schedule')->name('schedule.')->middleware('can:schedule.view')->group(function (): void {
        Route::get('/', [ScheduleController::class, 'index'])->name('index');
        Route::get('/day', [ScheduleController::class, 'day'])->name('day');
        Route::get('/week', [ScheduleController::class, 'week'])->name('week');
        Route::get('/conflicts', [ScheduleController::class, 'conflicts'])->name('conflicts');
    });

    Route::get('/compliance/ai-policy', [ComplianceController::class, 'aiPolicy'])
        ->middleware('can:compliance.view')
        ->name('compliance.ai-policy');

    Route::prefix('alerts')->name('alerts.')->middleware('can:alerts.view')->group(function (): void {
        Route::get('/', [OperationalAlertController::class, 'index'])->name('index');
        Route::get('/{alert}', [OperationalAlertController::class, 'show'])->name('show');
        Route::post('/{alert}/acknowledge', [OperationalAlertController::class, 'acknowledge'])
            ->middleware('can:alerts.manage')
            ->name('acknowledge');
        Route::post('/{alert}/resolve', [OperationalAlertController::class, 'resolve'])
            ->middleware('can:alerts.resolve')
            ->name('resolve');
        Route::post('/{alert}/dismiss', [OperationalAlertController::class, 'dismiss'])
            ->middleware('can:alerts.manage')
            ->name('dismiss');
    });

    Route::prefix('manual')->name('manual.')->middleware('can:manual.view')->group(function (): void {
        Route::get('/', [ManualController::class, 'index'])->name('index');
        Route::get('/search', [ManualController::class, 'search'])->name('search');
        Route::get('/quick-start', [ManualController::class, 'quickStart'])->name('quick-start');
        Route::post('/{article}/read', [ManualController::class, 'markAsRead'])->name('read');
        Route::get('/{slug}', [ManualController::class, 'show'])->name('show');
    });

    Route::prefix('admin/manual')->name('admin.manual.')->middleware('can:manual.manage')->group(function (): void {
        Route::get('/', [ManualAdminController::class, 'index'])->name('index');
        Route::get('/create', [ManualAdminController::class, 'create'])->name('create');
        Route::post('/', [ManualAdminController::class, 'store'])->name('store');
        Route::get('/categories/create', [ManualAdminController::class, 'categoryCreate'])->name('categories.create');
        Route::post('/categories', [ManualAdminController::class, 'categoryStore'])->name('categories.store');
        Route::get('/categories/{category}/edit', [ManualAdminController::class, 'categoryEdit'])->name('categories.edit');
        Route::match(['put', 'patch'], '/categories/{category}', [ManualAdminController::class, 'categoryUpdate'])->name('categories.update');
        Route::delete('/categories/{category}', [ManualAdminController::class, 'categoryDestroy'])->name('categories.destroy');
        Route::get('/{article}/preview', [ManualAdminController::class, 'preview'])->name('preview');
        Route::get('/{article}/edit', [ManualAdminController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{article}', [ManualAdminController::class, 'update'])->name('update');
        Route::patch('/{article}/publish', [ManualAdminController::class, 'publish'])->name('publish');
        Route::patch('/{article}/disable', [ManualAdminController::class, 'disable'])->name('disable');
        Route::delete('/{article}', [ManualAdminController::class, 'destroy'])->name('destroy');
    });

    Route::get('/trace', [TraceController::class, 'index'])
        ->middleware('can:trace.view')
        ->name('trace.index');
    Route::post('/trace/scan', [TraceController::class, 'scan'])
        ->middleware('can:trace.scan')
        ->name('trace.scan');
    Route::get('/trace/labels/lots', [TraceController::class, 'lotLabels'])
        ->middleware('can:trace.print')
        ->name('trace.labels.lots');
    Route::get('/trace/labels/cases/{case}', [TraceController::class, 'caseLabels'])
        ->middleware('can:trace.print')
        ->name('trace.labels.cases');

    Route::prefix('masters')->name('masters.')->group(function (): void {
        Route::get('/institutions', [InstitutionMasterController::class, 'index'])
            ->middleware('can:masters.view')
            ->name('institutions.index');
        Route::get('/institutions/create', [InstitutionMasterController::class, 'create'])
            ->middleware('can:masters.manage')
            ->name('institutions.create');
        Route::post('/institutions', [InstitutionMasterController::class, 'store'])
            ->middleware('can:masters.manage')
            ->name('institutions.store');
        Route::get('/institutions/{institution}', [InstitutionMasterController::class, 'show'])
            ->middleware('can:masters.view')
            ->name('institutions.show');
        Route::get('/institutions/{institution}/edit', [InstitutionMasterController::class, 'edit'])
            ->middleware('can:masters.manage')
            ->name('institutions.edit');
        Route::patch('/institutions/{institution}', [InstitutionMasterController::class, 'update'])
            ->middleware('can:masters.manage')
            ->name('institutions.update');

        Route::get('/doctors', [DoctorMasterController::class, 'index'])
            ->middleware('can:masters.view')
            ->name('doctors.index');
        Route::get('/doctors/create', [DoctorMasterController::class, 'create'])
            ->middleware('can:masters.manage')
            ->name('doctors.create');
        Route::post('/doctors', [DoctorMasterController::class, 'store'])
            ->middleware('can:masters.manage')
            ->name('doctors.store');
        Route::get('/doctors/{doctor}', [DoctorMasterController::class, 'show'])
            ->middleware('can:masters.view')
            ->name('doctors.show');
        Route::get('/doctors/{doctor}/edit', [DoctorMasterController::class, 'edit'])
            ->middleware('can:masters.manage')
            ->name('doctors.edit');
        Route::patch('/doctors/{doctor}', [DoctorMasterController::class, 'update'])
            ->middleware('can:masters.manage')
            ->name('doctors.update');

        Route::get('/prices', [ProductPriceController::class, 'index'])
            ->middleware('can:prices.view')
            ->name('prices.index');
        Route::get('/prices/create', [ProductPriceController::class, 'create'])
            ->middleware('can:prices.manage')
            ->name('prices.create');
        Route::post('/prices', [ProductPriceController::class, 'store'])
            ->middleware('can:prices.manage')
            ->name('prices.store');
        Route::get('/prices/{price}/edit', [ProductPriceController::class, 'edit'])
            ->middleware('can:prices.manage')
            ->name('prices.edit');
        Route::patch('/prices/{price}', [ProductPriceController::class, 'update'])
            ->middleware('can:prices.manage')
            ->name('prices.update');
    });

    Route::prefix('admin/users')->name('admin.users.')->middleware('can:users.manage')->group(function (): void {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}', [UserManagementController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
        Route::patch('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::post('/{user}/disable', [UserManagementController::class, 'disable'])->name('disable');
        Route::post('/{user}/enable', [UserManagementController::class, 'enable'])->name('enable');
        Route::post('/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('reset-password');
    });

    Route::get('/documents', [DocumentEvidenceController::class, 'index'])
        ->name('documents.index');
    Route::post('/documents', [DocumentEvidenceController::class, 'store'])
        ->name('documents.store');
    Route::get('/documents/{document}/download', [DocumentEvidenceController::class, 'download'])
        ->name('documents.download');
    Route::get('/documents/{document}', [DocumentEvidenceController::class, 'show'])
        ->name('documents.show');
    Route::post('/documents/{document}/validate', [DocumentEvidenceController::class, 'validateDocument'])
        ->name('documents.validate');
    Route::delete('/documents/{document}', [DocumentEvidenceController::class, 'destroy'])
        ->name('documents.destroy');

    Route::get('/reports', [ReportsController::class, 'index'])
        ->name('reports.index');
    Route::get('/reports/operations', [ReportsController::class, 'operations'])
        ->name('reports.operations');
    Route::get('/reports/commercial', [ReportsController::class, 'commercial'])
        ->name('reports.commercial');
    Route::get('/reports/billing', [ReportsController::class, 'billing'])
        ->name('reports.billing');
    Route::get('/reports/inventory', [ReportsController::class, 'inventory'])
        ->name('reports.inventory');
    Route::get('/reports/export/{type}', [ReportsController::class, 'export'])
        ->whereIn('type', ['operations', 'commercial', 'billing', 'inventory'])
        ->name('reports.export');

    Route::resource('cases', SurgeryCaseController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::get('/cases/{case}/control', [SurgeryCaseController::class, 'control'])
        ->name('cases.control');
    Route::post('/cases/{case}/transition', [SurgeryCaseController::class, 'transition'])
        ->name('cases.transition');
    Route::patch('/cases/{case}/schedule/instrumentist', [SurgeryCaseController::class, 'assignInstrumentist'])
        ->name('cases.schedule.instrumentist.update');
    Route::post('/cases/{case}/resources', [SurgeryCaseController::class, 'assignResource'])
        ->name('cases.resources.store');
    Route::delete('/cases/{case}/resources/{assignment}', [SurgeryCaseController::class, 'unassignResource'])
        ->name('cases.resources.destroy');
    Route::get('/cases/{case}/preparation', [SurgeryCaseController::class, 'preparationForm'])
        ->name('cases.preparation');
    Route::post('/cases/{case}/preparation', [SurgeryCaseController::class, 'prepare'])
        ->name('cases.preparation.store');
    Route::get('/cases/{case}/documents', [DocumentEvidenceController::class, 'caseIndex'])
        ->name('cases.documents.index');
    Route::post('/cases/{case}/documents', [DocumentEvidenceController::class, 'storeForCase'])
        ->name('cases.documents.store');

    Route::get('/failures', [FailureController::class, 'index'])
        ->name('failures.index');
    Route::get('/failures/create', [FailureController::class, 'create'])
        ->name('failures.create');
    Route::post('/failures', [FailureController::class, 'store'])
        ->name('failures.store');
    Route::get('/failures/{failure}', [FailureController::class, 'show'])
        ->name('failures.show');
    Route::get('/failures/{failure}/edit', [FailureController::class, 'edit'])
        ->name('failures.edit');
    Route::patch('/failures/{failure}', [FailureController::class, 'update'])
        ->name('failures.update');
    Route::post('/failures/{failure}/release', [FailureController::class, 'release'])
        ->name('failures.release');
    Route::post('/failures/{failure}/retire', [FailureController::class, 'retire'])
        ->name('failures.retire');
    Route::post('/failures/{failure}/close', [FailureController::class, 'close'])
        ->name('failures.close');

    Route::get('/cases/{case}/reserve', [SurgeryCaseController::class, 'reserveForm'])
        ->name('cases.reserve.create');
    Route::post('/cases/{case}/reserve', [SurgeryCaseController::class, 'reserve'])
        ->name('cases.reserve');
    Route::get('/cases/{case}/close', [SurgeryCaseController::class, 'closeForm'])
        ->name('cases.close.create');
    Route::post('/cases/{case}/close', [SurgeryCaseController::class, 'close'])
        ->name('cases.close');
    Route::get('/cases/{case}/reconciliation', [ReconciliationController::class, 'show'])
        ->name('cases.reconciliation');
    Route::post('/cases/{case}/reconciliation', [ReconciliationController::class, 'store'])
        ->name('cases.reconciliation.store');

    Route::get('/returns', [ReturnController::class, 'index'])
        ->name('returns.index');
    Route::get('/returns/{return}', [ReturnController::class, 'show'])
        ->name('returns.show');
    Route::get('/returns/{return}/inspect', [ReturnController::class, 'inspectForm'])
        ->name('returns.inspect.create');
    Route::post('/returns/{return}/inspect', [ReturnController::class, 'inspect'])
        ->name('returns.inspect');

    Route::get('/approvals/cost-zero', [CostZeroApprovalController::class, 'index'])
        ->name('approvals.cost-zero.index');
    Route::post('/approvals/cost-zero/{valuation}/approve', [CostZeroApprovalController::class, 'approve'])
        ->name('approvals.cost-zero.approve');
    Route::post('/approvals/cost-zero/{valuation}/reject', [CostZeroApprovalController::class, 'reject'])
        ->name('approvals.cost-zero.reject');

    Route::get('/billing', [BillingController::class, 'index'])
        ->name('billing.index');
    Route::get('/billing/{case}', [BillingController::class, 'show'])
        ->name('billing.show');
    Route::patch('/billing/{case}', [BillingController::class, 'update'])
        ->name('billing.update');

    Route::get('/inventory', [InventoryController::class, 'index'])
        ->name('inventory.index');
    Route::get('/inventory/coverage', StockCoverageController::class)
        ->name('inventory.coverage');
    Route::get('/inventory/forecast', [InventoryForecastController::class, 'index'])
        ->name('inventory.forecast');
    Route::get('/inventory/forecast/export', [InventoryForecastController::class, 'export'])
        ->name('inventory.forecast.export');
    Route::get('/inventory/{lot}', [InventoryController::class, 'show'])
        ->name('inventory.show');
    Route::get('/inventory/{lot}/edit', [InventoryController::class, 'edit'])
        ->name('inventory.edit');
    Route::patch('/inventory/{lot}', [InventoryController::class, 'update'])
        ->name('inventory.update');
    Route::get('/inventory/{lot}/adjust', [InventoryController::class, 'adjustForm'])
        ->name('inventory.adjust.create');
    Route::post('/inventory/{lot}/adjust', [InventoryController::class, 'adjust'])
        ->name('inventory.adjust');

    Route::get('/catalog/imports', [CatalogImportController::class, 'index'])
        ->name('catalog.imports.index');
    Route::post('/catalog/imports', [CatalogImportController::class, 'store'])
        ->name('catalog.imports.store');
    Route::get('/catalog/imports/{import}', [CatalogImportController::class, 'show'])
        ->name('catalog.imports.show');
    Route::post('/catalog/imports/{import}/commit', [CatalogImportController::class, 'commit'])
        ->name('catalog.imports.commit');

    if (class_exists(ProfileController::class)) {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    }
});

if (file_exists(__DIR__.'/auth.php')) {
    require __DIR__.'/auth.php';
}
