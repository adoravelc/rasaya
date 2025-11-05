<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthWebController;
use Illuminate\Http\Request;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\GuruBkDashboardController;
use App\Http\Controllers\Web\GuruWkDashboardController;
use App\Http\Controllers\Web\KelasWebController;
use App\Http\Controllers\Web\KategoriWebController;
use App\Http\Controllers\Web\InputGuruController;
use App\Http\Controllers\Web\AdminGuruController;
use App\Http\Controllers\Web\AdminSiswaController;
use App\Http\Controllers\Web\AdminSiswaKelasController;
use App\Http\Controllers\Web\SiswaDashboardController;
use App\Http\Controllers\Web\AdminJurusanController;
use App\Http\Controllers\Web\TahunAjaranWebController;
use App\Http\Controllers\Web\RekomendasiWebController;
use App\Http\Controllers\Api\SlotKonselingController as SlotApi;
use App\Http\Controllers\Web\MlBridgeController;
use App\Http\Controllers\Web\AnalisisEntryController;
use App\Http\Controllers\Web\EmosiTrenController;

// Redirect landing page straight to login for a focused UX
Route::redirect('/', '/login');

Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'doLogin']);
Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');

Route::get('/dashboard', function (Request $request) {
    $user = $request->user(); // ✅ clean
    if (!$user)
        return redirect()->route('login');

    if ($user->hasRole('admin'))
        return redirect()->route('admin.dashboard');
    if ($user->hasRole('guru'))
        return redirect()->route('guru.dashboard');

    return redirect('/');
})->name('dashboard')->middleware('auth');

/** ===================== ADMIN ===================== */
Route::prefix('admin')->middleware(['auth', 'role:admin'])->group(function () {
    // Dashboard & Analytics
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard.index');
    Route::get('/dashboard/login-history', [AdminDashboardController::class, 'loginHistory'])->name('admin.dashboard.login-history');
    Route::get('/dashboard/user-activity/{userId}', [AdminDashboardController::class, 'userActivity'])->name('admin.dashboard.user-activity');
    Route::get('/dashboard/audit-logs', [AdminDashboardController::class, 'auditLogs'])->name('admin.dashboard.audit-logs');

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

    // Guru
    Route::get('/guru', [AdminGuruController::class, 'index'])->name('admin.guru.index');
    Route::post('/guru', [AdminGuruController::class, 'store'])->name('admin.guru.store');
    Route::put('/guru/{userId}', [AdminGuruController::class, 'update'])->name('admin.guru.update');
    Route::delete('/guru/{userId}', [AdminGuruController::class, 'destroy'])->name('admin.guru.destroy');

    // Siswa
    Route::get('/siswa', [AdminSiswaController::class, 'index'])->name('admin.siswa.index');
    Route::post('/siswa', [AdminSiswaController::class, 'store'])->name('admin.siswa.store');
    Route::put('/siswa/{userId}', [AdminSiswaController::class, 'update'])->name('admin.siswa.update');
    Route::delete('/siswa/{userId}', [AdminSiswaController::class, 'destroy'])->name('admin.siswa.destroy');

    // Siswa-Kelas
    Route::get('/siswa-kelas', [AdminSiswaKelasController::class, 'index'])->name('admin.siswa_kelas.index');
    Route::get('/siswa-kelas/full', [AdminSiswaKelasController::class, 'full'])->name('admin.siswa_kelas.full');
    Route::post('/siswa-kelas', [AdminSiswaKelasController::class, 'store'])->name('admin.siswa_kelas.store');
    Route::post('/siswa-kelas/remove', [AdminSiswaKelasController::class, 'remove'])->name('admin.siswa_kelas.remove');

    // Jurusan
    Route::get('/jurusan', [AdminJurusanController::class, 'index'])->name('admin.jurusan.index');
    Route::post('/jurusan', [AdminJurusanController::class, 'store'])->name('admin.jurusan.store');
    Route::put('/jurusan/{jurusan}', [AdminJurusanController::class, 'update'])->name('admin.jurusan.update');
    Route::delete('/jurusan/{jurusan}', [AdminJurusanController::class, 'destroy'])->name('admin.jurusan.destroy');

    // Tahun Ajaran toggle active
    Route::get('/tahun-ajaran', [TahunAjaranWebController::class, 'index'])->name('admin.tahun_ajaran.index');
    Route::get('/tahun-ajaran/trashed', [TahunAjaranWebController::class, 'trashed'])->name('admin.tahun_ajaran.trashed');
    Route::post('/tahun-ajaran', [TahunAjaranWebController::class, 'store'])->name('admin.tahun_ajaran.store');
    Route::patch('/tahun-ajaran/{tahunAjaran}/active', [TahunAjaranWebController::class, 'toggleActive'])->name('admin.tahun_ajaran.toggle');
    Route::delete('/tahun-ajaran/{tahunAjaran}', [TahunAjaranWebController::class, 'destroy'])->name('admin.tahun_ajaran.destroy');
    Route::post('/tahun-ajaran/{id}/restore', [TahunAjaranWebController::class, 'restore'])->name('admin.tahun_ajaran.restore');
    Route::delete('/tahun-ajaran/{id}/force', [TahunAjaranWebController::class, 'forceDelete'])->name('admin.tahun_ajaran.force');

    // Rekomendasi Tindakan (Master Rekomendasi)
    Route::get('/rekomendasi', [RekomendasiWebController::class, 'index'])->name('admin.rekomendasi.index');
    Route::post('/rekomendasi', [RekomendasiWebController::class, 'store'])->name('admin.rekomendasi.store');
    Route::put('/rekomendasi/{rekomendasi}', [RekomendasiWebController::class, 'update'])->name('admin.rekomendasi.update');
    Route::delete('/rekomendasi/{rekomendasi}', [RekomendasiWebController::class, 'destroy'])->name('admin.rekomendasi.destroy');
    Route::patch('/rekomendasi/{rekomendasi}/active', [RekomendasiWebController::class, 'toggleActive'])->name('admin.rekomendasi.toggle');
    Route::get('/rekomendasi/suggest-kode', [RekomendasiWebController::class, 'suggestKode'])->name('admin.rekomendasi.suggest_kode');
});

/** ===================== GURU (BK & WALI KELAS) ===================== */
Route::prefix('guru')->middleware(['auth', 'role:guru'])->group(function () {
    // Alihkan root dashboard guru ke dashboard sesuai jenis (BK / Wali Kelas)
    Route::get('/', function (Request $request) {
        $user = $request->user();
        $guru = \App\Models\Guru::where('user_id', $user->id)->first();
        if ($guru && $guru->jenis === 'bk') {
            return redirect()->route('guru.bk.dashboard');
        }
        if ($guru && $guru->jenis === 'wali_kelas') {
            return redirect()->route('guru.wk.dashboard');
        }
        // Jika tidak terdeteksi jenisnya, kembalikan ke /dashboard umum
        return redirect()->route('dashboard');
    })->name('guru.dashboard');

    // Observasi (Input Guru)
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

        // Halaman Blade + JSON untuk Slot Konseling (reuse controller API)
        Route::view('/slot-konseling', 'roles.guru.guru_bk.slot_konseling')->name('guru.guru_bk.slots.view');
        Route::get('/slots', [SlotApi::class, 'index'])->name('guru.guru_bk.slots.index');
        Route::get('/slots/{id}', [SlotApi::class, 'show'])->name('guru.guru_bk.slots.show');
        Route::post('/slots/publish', [SlotApi::class, 'publish'])->name('guru.guru_bk.slots.publish');
        Route::delete('/slots/{id}', [SlotApi::class, 'destroy'])->name('guru.guru_bk.slots.destroy');

        // Tetap tersedia untuk BK (legacy route names)
        Route::get('/analisis', [AnalisisEntryController::class, 'index'])->name('guru.bk.analisis.index');
        Route::get('/analisis/create', [AnalisisEntryController::class, 'create'])->name('guru.bk.analisis.create');
        Route::post('/analisis', [AnalisisEntryController::class, 'store'])->name('guru.bk.analisis.store');
        Route::get('/analisis/{analisis}', [AnalisisEntryController::class, 'show'])->name('guru.bk.analisis.show');
        Route::post('/analisis/{analisis}/rekomendasi/{rid}', [AnalisisEntryController::class, 'decide'])->name('guru.bk.analisis.decide');
    Route::get('/analisis/{analisis}/rekomendasi/{rid}/alternatives', [AnalisisEntryController::class, 'alternatives'])->name('guru.bk.analisis.alternatives');
    });

    // Wali Kelas
    Route::prefix('wk')->middleware('gurujenis:wali_kelas')->group(function () {
        Route::get('/', [GuruWkDashboardController::class, 'index'])->name('guru.wk.dashboard');
    });

    // Umum: Analisis (tersedia untuk semua Guru, BK & Wali Kelas)
    Route::prefix('analisis')->group(function () {
        Route::get('/', [AnalisisEntryController::class, 'index'])->name('guru.analisis.index');
        Route::get('/create', [AnalisisEntryController::class, 'create'])->name('guru.analisis.create');
        Route::post('/', [AnalisisEntryController::class, 'store'])->name('guru.analisis.store');
        Route::get('/{analisis}', [AnalisisEntryController::class, 'show'])->name('guru.analisis.show');
            Route::post('/{analisis}/rekomendasi/{rid}', [AnalisisEntryController::class, 'decide'])->name('guru.analisis.decide');
        // Detail rekomendasi (JSON) untuk modal
        Route::get('/{analisis}/rekomendasi/{rid}', [AnalisisEntryController::class, 'detail'])->name('guru.analisis.rekomendasi.detail');
            Route::post('/{analisis}/finalize', [AnalisisEntryController::class, 'finalize'])->name('guru.analisis.finalize');
        Route::post('/{analisis}/attention', [AnalisisEntryController::class, 'attention'])->name('guru.analisis.attention');
        Route::get('/{analisis}/rekomendasi/{rid}/alternatives', [AnalisisEntryController::class, 'alternatives'])->name('guru.analisis.alternatives');
    });

    // Tren Emosi (Dashboard chart + halaman khusus)
    Route::get('/tren-emosi', [EmosiTrenController::class, 'index'])->name('guru.tren_emosi.index');
    Route::get('/tren-emosi/data', [EmosiTrenController::class, 'data'])->name('guru.tren_emosi.data');
    Route::get('/tren-emosi/siswa', [EmosiTrenController::class, 'siswaByKelas'])->name('guru.tren_emosi.siswa');
});

/** ===================== SISWA ===================== */
Route::prefix('siswa')->middleware(['auth', 'role:siswa'])->group(function () {
    Route::get('/', [SiswaDashboardController::class, 'index'])->name('siswa.dashboard');
});

Route::redirect('/admin/dashboard', '/admin');
Route::redirect('/guru/guru_bk/dashboard', '/guru/bk');
Route::redirect('/guru/wali_kelas/dashboard', '/guru/wk');

//nyoba dummy ML API
Route::get('/ml/health', [MlBridgeController::class, 'health']);
Route::post('/ml/try', [MlBridgeController::class, 'tryAnalyze']);
