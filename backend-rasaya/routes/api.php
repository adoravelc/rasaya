<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KelasController;
use App\Http\Controllers\Api\GuruController;
use App\Http\Controllers\Api\SiswaController;
use App\Http\Controllers\Api\SiswaKelasController;
use App\Http\Controllers\Api\KategoriMasalahController;
use App\Http\Controllers\Api\InputSiswaController;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/health', fn() => response()->json(['ok' => true, 'ts' => now()]));
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Session
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |----------------------------------------------------------------------
    | Input Refleksi Siswa
    |   - siswa: index() hanya data milik sendiri
    |   - admin/guru: bisa filter & kelola
    |----------------------------------------------------------------------
    */
    Route::get('/siswas', [SiswaController::class, 'index']); // <-- Endpoint ini yang diakses Flutter
    Route::get('input-siswa', [InputSiswaController::class, 'index']);
    Route::post('input-siswa', [InputSiswaController::class, 'store']);     // <— ini yang kamu butuhkan
    Route::get('input-siswa/{inputSiswa}', [InputSiswaController::class, 'show']);
    Route::put('input-siswa/{inputSiswa}', [InputSiswaController::class, 'update']);
    Route::delete('input-siswa/{inputSiswa}', [InputSiswaController::class, 'destroy']);

    /*
    |----------------------------------------------------------------------
    | Admin only
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {
        // Kelas
        Route::apiResource('kelass', KelasController::class)
            ->parameters(['kelass' => 'kelas']);

        // Guru
        Route::apiResource('gurus', GuruController::class);
        Route::get('gurus-trash', [GuruController::class, 'trash']);
        Route::post('gurus/{id}/restore', [GuruController::class, 'restore']);
        Route::delete('gurus/{id}/force', [GuruController::class, 'forceDestroy']);

        // Siswa
        Route::apiResource('siswas', SiswaController::class);
        Route::get('siswas-trash', [SiswaController::class, 'trash']);
        Route::post('siswas/{id}/restore', [SiswaController::class, 'restore']);
        Route::delete('siswas/{id}/force', [SiswaController::class, 'forceDestroy']);

        // Assign / drop siswa-kelas
        Route::post('siswa-kelas', [SiswaKelasController::class, 'store']);
        Route::delete('siswa-kelas', [SiswaKelasController::class, 'destroy']);

        // Kategori Masalah
        Route::post('kategori-masalah', [KategoriMasalahController::class, 'store']);
        Route::get('kategori-masalah/{kategoriMasalah}', [KategoriMasalahController::class, 'show']);
        Route::put('kategori-masalah/{kategoriMasalah}', [KategoriMasalahController::class, 'update']);
        Route::delete('kategori-masalah/{kategoriMasalah}', [KategoriMasalahController::class, 'destroy']);
        Route::get('kategori-masalah-trashed', [KategoriMasalahController::class, 'trashed']);
        Route::post('kategori-masalah/{id}/restore', [KategoriMasalahController::class, 'restore']);
        Route::delete('kategori-masalah/{id}/force', [KategoriMasalahController::class, 'forceDelete']);
    });

    /*
    |----------------------------------------------------------------------
    | Admin & Guru
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin,guru')->group(function () {
        Route::get('siswa-kelas', [SiswaKelasController::class, 'index']); // lihat roster
    });
});
