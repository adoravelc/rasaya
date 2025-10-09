<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KelasController;
use App\Http\Controllers\Api\GuruController;
use App\Http\Controllers\Api\SiswaController;
use App\Http\Controllers\Api\SiswaKelasController;

Route::get('/health', fn() => response()->json(['ok' => true, 'ts' => now()]));

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('kelass', KelasController::class)
        ->parameters(['kelass' => 'kelas']);

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('gurus', GuruController::class);
        Route::get('gurus-trash', [GuruController::class, 'trash']);
        Route::post('gurus/{id}/restore', [GuruController::class, 'restore']);
        Route::delete('gurus/{id}/force', [GuruController::class, 'forceDestroy']);

        Route::apiResource('siswas', SiswaController::class);
        Route::get('siswas-trash', [SiswaController::class, 'trash']);
        Route::post('siswas/{id}/restore', [SiswaController::class, 'restore']);
        Route::delete('siswas/{id}/force', [SiswaController::class, 'forceDestroy']);

        // siswa-kelas assign/drop tetap sama
        Route::post('siswa-kelas', [SiswaKelasController::class, 'store']);
        Route::delete('siswa-kelas', [SiswaKelasController::class, 'destroy']);
    });

    // index (lihat roster) boleh admin & guru
    Route::middleware('role:admin,guru')->group(function () {
        Route::get('siswa-kelas', [SiswaKelasController::class, 'index']);
    });
});