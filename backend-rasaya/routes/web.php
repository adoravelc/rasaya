<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\GuruBkDashboardController;
use App\Http\Controllers\Web\GuruWkDashboardController;
use App\Http\Controllers\Web\KelasWebController;
use App\Http\Controllers\Web\KategoriWebController;

Route::view('/', 'welcome');

Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'doLogin']);
Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');

/**
 * ADMIN
 */
Route::prefix('admin')->middleware(['auth', 'role:admin'])->group(function () {
    // Dashboard di /admin
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    // CRUD Kelas
    Route::get('/kelas', [KelasWebController::class, 'index'])->name('admin.kelas.index');
    Route::post('/kelas', [KelasWebController::class, 'storeAjax'])->name('admin.kelas.store');
    Route::put('/kelas/{kelas}', [KelasWebController::class, 'updateAjax'])->name('admin.kelas.update');
    Route::delete('/kelas/{kelas}', [KelasWebController::class, 'destroyAjax'])->name('admin.kelas.destroy');
    // Soft deletes
    Route::post('/kelas/{id}/restore', [KelasWebController::class, 'restore'])->name('admin.kelas.restore');
    Route::delete('/kelas/{id}/force', [KelasWebController::class, 'forceDelete'])->name('admin.kelas.force');

    //CRUD Kategori Masalah
    Route::get('/kategori', [KategoriWebController::class, 'index'])->name('admin.kategori.index');
    Route::post('/kategori', [KategoriWebController::class, 'store'])->name('admin.kategori.store');
    Route::put('/kategori/{kategori}', [KategoriWebController::class, 'update'])->name('admin.kategori.update');
    Route::delete('/kategori/{kategori}', [KategoriWebController::class, 'destroy'])->name('admin.kategori.destroy');
    Route::patch('/kategori/{kategori}/active', [KategoriWebController::class, 'toggleActive'])
        ->name('admin.kategori.toggle');
    // Soft deletes
    Route::get('/kategori/trashed', [KategoriWebController::class, 'trashed'])->name('admin.kategori.trashed');
    Route::post('/kategori/{id}/restore', [KategoriWebController::class, 'restore'])->name('admin.kategori.restore');
    Route::delete('/kategori/{id}/force', [KategoriWebController::class, 'force'])->name('admin.kategori.force');
});

/**
 * GURU
 */
Route::prefix('guru')->middleware(['auth', 'role:guru'])->group(function () {

    // Guru BK => /guru/bk
    Route::prefix('bk')->middleware('gurujenis:bk')->group(function () {
        Route::get('/', [GuruBkDashboardController::class, 'index'])->name('guru.bk.dashboard');
    });

    // Guru Wali Kelas => /guru/wk
    Route::prefix('wk')->middleware('gurujenis:wali_kelas')->group(function () {
        Route::get('/', [GuruWkDashboardController::class, 'index'])->name('guru.wk.dashboard');
    });
});

/* (opsional) redirect legacy url ke path baru */
Route::redirect('/admin/dashboard', '/admin');
Route::redirect('/guru/guru_bk/dashboard', '/guru/bk');
Route::redirect('/guru/wali_kelas/dashboard', '/guru/wk');
