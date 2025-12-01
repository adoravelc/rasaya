<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KelasController;
use App\Http\Controllers\Api\GuruController;
use App\Http\Controllers\Api\SiswaController;
use App\Http\Controllers\Api\SiswaKelasController;
use App\Http\Controllers\Api\KategoriMasalahController;
use App\Http\Controllers\Api\InputSiswaController;
use App\Http\Controllers\Api\MoodController;
use App\Http\Controllers\Api\SlotKonselingController;
use App\Http\Controllers\Api\BookingKonselingController;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/health', fn() => response()->json(['ok' => true, 'ts' => now()]));
Route::post('/login', [AuthController::class, 'login']);
// Forgot password request (public)
Route::post('/forgot-password', [\App\Http\Controllers\Api\ForgotPasswordController::class, 'requestReset']);

/*
|--------------------------------------------------------------------------
| Protected (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // sesi
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    // ubah password sendiri
    Route::post('/me/password', [AuthController::class, 'changePassword']);
    // ubah email sendiri
    Route::post('/me/email', [AuthController::class, 'changeEmail']);
    // save FCM token for push notifications
    Route::post('/user/fcm-token', [AuthController::class, 'saveFcmToken']);

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);

    // === endpoint untuk Flutter pilih teman (boleh siswa) ===
    Route::get('/siswa-list', [SiswaController::class, 'listSimple']);

    // Input refleksi
    Route::get('input-siswa', [InputSiswaController::class, 'index']);
    Route::get('input-siswa/today-status', [InputSiswaController::class, 'todayStatus']);
    Route::post('input-siswa', [InputSiswaController::class, 'store']);
    Route::get('input-siswa/{inputSiswa}', [InputSiswaController::class, 'show']);
    Route::put('input-siswa/{inputSiswa}', [InputSiswaController::class, 'update']);
    Route::delete('input-siswa/{inputSiswa}', [InputSiswaController::class, 'destroy']);


    // Mood tracker (siswa, guru, admin)
    Route::post('/mood', [MoodController::class, 'store']);   // siswa submit
    Route::get('/mood/today', [MoodController::class, 'today']);   // status hari ini
    Route::get('/mood/history', [MoodController::class, 'history']); // riwayat

    // Booking konseling (siswa)
    Route::get('/slots/available', [BookingKonselingController::class,'available']);
    Route::post('/bookings',                [BookingKonselingController::class,'book']);      // {slot_id}
    Route::get('/bookings/me',              [BookingKonselingController::class,'myBookings']);
    Route::post('/bookings/{id}/cancel',    [BookingKonselingController::class,'cancelMine']);

    // ==== Admin only ====
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('kelass', KelasController::class)
            ->parameters(['kelass' => 'kelas']);

        Route::apiResource('gurus', GuruController::class);
        Route::get('gurus-trash', [GuruController::class, 'trash']);
        Route::post('gurus/{id}/restore', [GuruController::class, 'restore']);
        Route::delete('gurus/{id}/force', [GuruController::class, 'forceDestroy']);

        // Penting: hilangkan index agar tidak menimpa /siswas umum
        Route::apiResource('siswas', SiswaController::class)->except(['index']);
        Route::get('siswas-trash', [SiswaController::class, 'trash']);
        Route::post('siswas/{id}/restore', [SiswaController::class, 'restore']);
        Route::delete('siswas/{id}/force', [SiswaController::class, 'forceDestroy']);
    });

    Route::middleware('role:admin,guru')->group(function () {
        Route::get('siswa-kelas', [SiswaKelasController::class, 'index']);
        // Publish/generate slots
        Route::post('/slots/publish', [SlotKonselingController::class, 'publish']);

        // List & manage own slots
        Route::get('/slots', [SlotKonselingController::class, 'index']);
        Route::patch('/slots/{id}/cancel', [SlotKonselingController::class, 'cancel']);
        Route::patch('/slots/{id}/archive', [SlotKonselingController::class, 'archive']);
    });
});