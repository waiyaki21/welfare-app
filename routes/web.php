<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenditureController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\FinancialYearController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NativeWindowController;

use Illuminate\Support\Facades\Route;

// ── Native routes: Window controls ──────────────────────────────────────────────
Route::controller(NativeWindowController::class)->group(function () {
    Route::post('/window/control/{action}', 'control')->name('window.control');
});

// ── Auth routes (guest only) ──────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('auth.login');
    Route::post('/login',   [AuthController::class, 'login'])->name('auth.login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('auth.register');
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register.post');

    Route::get('/auth/google',          [AuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

    Route::get('/forgot-password',  [AuthController::class, 'showForgotPassword'])->name('auth.password.reset');
    Route::post('/forgot-password', [AuthController::class, 'resetPassword'])->name('auth.password.reset.post');
});

// Logout (auth required)
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth');

// ── Protected routes (auth required) ─────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Profile & settings
    Route::get('/profile',                   [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile/update',           [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/password',         [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/app-settings',     [ProfileController::class, 'updateAppSettings'])->name('profile.app-settings');
    Route::post('/profile/sidebar-color',    [ProfileController::class, 'updateSidebarColor'])->name('profile.sidebar-color');
    Route::post('/profile/import-settings',  [ProfileController::class, 'updateImportSettings'])->name('profile.import-settings');
    Route::post('/profile/theme',            [ProfileController::class, 'updateTheme'])->name('profile.theme');

    // Members
    Route::get('/members',               [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/create',        [MemberController::class, 'create'])->name('members.create');
    Route::post('/members',              [MemberController::class, 'store'])->name('members.store');
    Route::get('/members/{member}',      [MemberController::class, 'show'])->name('members.show');
    Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
    Route::put('/members/{member}',      [MemberController::class, 'update'])->name('members.update');
    Route::delete('/members/{member}',   [MemberController::class, 'destroy'])->name('members.destroy');

    // Payments
    Route::get('/payments',                [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments',               [PaymentController::class, 'store'])->name('payments.store');
    Route::post('/payments/quick',         [PaymentController::class, 'quickStore'])->name('payments.quick');
    Route::get('/payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
    Route::put('/payments/{payment}',      [PaymentController::class, 'update'])->name('payments.update');
    Route::delete('/payments/{payment}',   [PaymentController::class, 'destroy'])->name('payments.destroy');

    // Expenses
    Route::get('/expenses',                [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses',               [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('/expenses/{expense}',      [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/expenses/{expense}',   [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // Expenditures module
    Route::get('/expenditures', [ExpenditureController::class, 'index'])->name('expenditures.index');
    Route::get('/expenditures/create', [ExpenditureController::class, 'create'])->name('expenditures.create');
    Route::post('/expenditures', [ExpenditureController::class, 'store'])->name('expenditures.store');
    Route::get('/expenditures/{expenditure}', [ExpenditureController::class, 'show'])->name('expenditures.show');
    Route::get('/expenditures/{expenditure}/edit', [ExpenditureController::class, 'edit'])->name('expenditures.edit');
    Route::put('/expenditures/{expenditure}', [ExpenditureController::class, 'update'])->name('expenditures.update');
    Route::delete('/expenditures/{expenditure}', [ExpenditureController::class, 'destroy'])->name('expenditures.destroy');

    // Expense Categories
    Route::get('/expense-categories',                    [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
    Route::post('/expense-categories',                   [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
    Route::put('/expense-categories/{expenseCategory}',  [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
    Route::delete('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('expense-categories.destroy');

    // Financial Years
    Route::get('/financial-years',                          [FinancialYearController::class, 'index'])->name('financial-years.index');
    Route::get('/financial-years/{financialYear}',          [FinancialYearController::class, 'show'])->name('financial-years.show');
    Route::get('/financial-years/{financialYear}/edit',     [FinancialYearController::class, 'edit'])->name('financial-years.edit');
    Route::put('/financial-years/{financialYear}',          [FinancialYearController::class, 'update'])->name('financial-years.update');
    Route::delete('/financial-years/{financialYear}',       [FinancialYearController::class, 'destroy'])->name('financial-years.destroy');
    Route::get('/financial-years/{financialYear}/export',   [FinancialYearController::class, 'export'])->name('financial-years.export');

    // Reset Database
    Route::get('/reset-database',  [FinancialYearController::class, 'resetConfirm'])->name('db.reset.confirm');
    Route::post('/reset-database', [FinancialYearController::class, 'resetExecute'])->name('db.reset.execute');

    // Import — full year
    Route::get('/import',  [ImportController::class, 'show'])->name('import.show');
    Route::post('/import', [ImportController::class, 'store'])->name('import.store');
    Route::post('/imports/preview/year', [ImportController::class, 'previewYearImport'])->name('imports.preview.year');
    Route::post('/imports/final/year',   [ImportController::class, 'finalYearImport'])->name('imports.final.year');
    Route::post('/imports/year/preview', [ImportController::class, 'previewYearImport'])->name('imports.year.preview');
    Route::post('/imports/year/final',   [ImportController::class, 'finalYearImport'])->name('imports.year.final');
    Route::post('/imports/expenditures/preview', [ImportController::class, 'previewExpenditureImport'])->name('imports.expenditures.preview');
    Route::post('/imports/expenditures/final',   [ImportController::class, 'finalExpenditureImport'])->name('imports.expenditures.final');
    Route::get('/expenditures/template/{year}',  [ImportController::class, 'expenditureTemplate'])->name('expenditures.template');

    // Import — monthly payments/welfare
    Route::get('/import/monthly/template', [ImportController::class, 'monthlyTemplate'])->name('import.monthly.template');
    Route::post('/import/monthly',         [ImportController::class, 'storeMonthly'])->name('import.monthly.store');
    Route::post('/imports/preview/month',  [ImportController::class, 'previewMonthImport'])->name('imports.preview.month');
    Route::post('/imports/final/month',    [ImportController::class, 'finalMonthImport'])->name('imports.final.month');

    // Import Last upload
    Route::post('/import/preview-last', [ImportController::class, 'previewLastUpload'])->name('import.preview.last');

    // Import pre-flight checks (month payments / year expenditures)
    Route::post('/imports/check/month-payments',     [ImportController::class, 'checkMonthPayments'])->name('imports.check.month-payments');
    Route::post('/imports/check/year-expenditures',  [ImportController::class, 'checkYearExpenditures'])->name('imports.check.year-expenditures');

    // Updates
    Route::post('/updates/check', [\App\Http\Controllers\UpdateController::class, 'check'])->name('updates.check');
});
