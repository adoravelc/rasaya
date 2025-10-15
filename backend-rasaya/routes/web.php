<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\GuruBkDashboardController;
use App\Http\Controllers\Web\GuruWkDashboardController;
use App\Http\Controllers\Web\KelasWebController;
use App\Http\Controllers\Web\KategoriWebController;
use App\Http\Controllers\Web\InputGuruController;

Route::view('/', 'welcome');

Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'doLogin']);
Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');

/** ===================== ADMIN ===================== */
Route::prefix('admin')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    // Kelas
    Route::get('/kelas', [KelasWebController::class, 'index'])->name('admin.kelas.index');
    Route::post('/kelas', [KelasWebController::class, 'storeAjax'])->name('admin.kelas.store');
    Route::put('/kelas/{kelas}', [KelasWebController::class, 'updateAjax'])->name('admin.kelas.update');
    Route::delete('/kelas/{kelas}', [KelasWebController::class, 'destroyAjax'])->name('admin.kelas.destroy');
    Route::post('/kelas/{id}/restore', [KelasWebController::class, 'restore'])->name('admin.kelas.restore');
    Route::delete('/kelas/{id}/force', [KelasWebController::class, 'forceDelete'])->name('admin.kelas.force');

    // Kategori
    Route::get('/kategori', [KategoriWebController::class, 'index'])->name('admin.kategori.index');
    Route::post('/kategori', [KategoriWebController::class, 'store'])->name('admin.kategori.store');
    Route::put('/kategori/{kategori}', [KategoriWebController::class, 'update'])->name('admin.kategori.update');
    Route::delete('/kategori/{kategori}', [KategoriWebController::class, 'destroy'])->name('admin.kategori.destroy');
    Route::patch('/kategori/{kategori}/active', [KategoriWebController::class, 'toggleActive'])->name('admin.kategori.toggle');
    Route::get('/kategori/trashed', [KategoriWebController::class, 'trashed'])->name('admin.kategori.trashed');
    Route::post('/kategori/{id}/restore', [KategoriWebController::class, 'restore'])->name('admin.kategori.restore');
    Route::delete('/kategori/{id}/force', [KategoriWebController::class, 'force'])->name('admin.kategori.force');
});

/** ===================== GURU (BK & WALI KELAS) ===================== */
Route::prefix('guru')->middleware(['auth', 'role:guru'])->group(function () {
    // Dashboard generik (boleh redirect ke BK/WK sesuai jenis)
    Route::get('/', fn() => view('roles.guru.dashboard'))->name('guru.dashboard'); // opsional
    // Observasi (Input Guru) — kedua jenis guru boleh akses
    Route::get('/observasi', [InputGuruController::class, 'index'])->name('guru.observasi.index');
    Route::post('/observasi', [InputGuruController::class, 'store'])->name('guru.observasi.store');
    Route::get('/observasi/{observasi}', [InputGuruController::class, 'show'])->name('guru.observasi.show');
    Route::put('/observasi/{observasi}', [InputGuruController::class, 'update'])->name('guru.observasi.update');
    Route::delete('/observasi/{observasi}', [InputGuruController::class, 'destroy'])->name('guru.observasi.destroy');
    Route::get('/observasi-trashed', [InputGuruController::class, 'trashed'])->name('guru.observasi.trashed');
    Route::post('/observasi/{id}/restore', [InputGuruController::class, 'restore'])->name('guru.observasi.restore');
    Route::delete('/observasi/{id}/force', [InputGuruController::class, 'forceDelete'])->name('guru.observasi.force');

    // BK
    Route::prefix('bk')->middleware('gurujenis:bk')->group(function () {
        Route::get('/', [GuruBkDashboardController::class, 'index'])->name('guru.bk.dashboard');
    });
    // Wali Kelas
    Route::prefix('wk')->middleware('gurujenis:wali_kelas')->group(function () {
        Route::get('/', [GuruWkDashboardController::class, 'index'])->name('guru.wk.dashboard');
    });
});

Route::redirect('/admin/dashboard', '/admin');
Route::redirect('/guru/guru_bk/dashboard', '/guru/bk');
Route::redirect('/guru/wali_kelas/dashboard', '/guru/wk');
