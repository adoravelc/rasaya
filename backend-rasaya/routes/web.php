<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\GuruBkDashboardController;
use App\Http\Controllers\Web\GuruWkDashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'doLogin']);
Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])
            ->name('admin.dashboard');
    });

    // Guru BK
    Route::middleware(['role:guru', 'gurujenis:bk'])->group(function () {
        Route::get('/guru/guru_bk/dashboard', [GuruBkDashboardController::class, 'index'])
            ->name('guru.guru_bk.dashboard');
    });

    // Guru Wali Kelas
    Route::middleware(['role:guru', 'gurujenis:wali_kelas'])->group(function () {
        Route::get('/guru/wali_kelas/dashboard', [GuruWkDashboardController::class, 'index'])
            ->name('guru.wali_kelas.dashboard');
    });

});