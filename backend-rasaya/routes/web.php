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
use App\Http\Controllers\Web\AdminUserManagementController;
use App\Http\Controllers\Api\SlotKonselingController as SlotApi;
use App\Http\Controllers\Web\MlBridgeController;
use App\Http\Controllers\Web\AnalisisEntryController;
use App\Http\Controllers\Web\EmosiTrenController;
use App\Http\Controllers\Web\GuruRefleksiHistoryController;
use App\Http\Controllers\Web\GuruRefleksiController;
use App\Http\Controllers\Web\AdminBackupController;
use App\Http\Controllers\Web\YearRolloverController;
use App\Http\Controllers\Web\RosterImportController;
use App\Http\Controllers\Web\NotificationController;

// Redirect landing page straight to login for a focused UX
Route::redirect('/', '/login');

Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'doLogin']);
Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');

// Forgot password (request flow)
Route::get('/forgot-password', [\App\Http\Controllers\Web\ForgotPasswordController::class, 'showForm'])->name('password.forgot');
Route::post('/forgot-password', [\App\Http\Controllers\Web\ForgotPasswordController::class, 'requestReset'])->name('password.forgot.request');
Route::get('/forgot-password/done', [\App\Http\Controllers\Web\ForgotPasswordController::class, 'done'])->name('password.forgot.done');

// Email reset link flow (public)
Route::get('/reset-password/{token}', [\App\Http\Controllers\Web\PasswordResetController::class, 'show'])->name('password.reset.show');
Route::post('/reset-password', [\App\Http\Controllers\Web\PasswordResetController::class, 'submit'])->name('password.reset.submit');

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

/** ===================== NOTIFICATIONS ===================== */
Route::middleware('auth')->group(function () {
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Web\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Web\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
});

/** ===================== ADMIN ===================== */
Route::prefix('admin')->middleware(['auth', 'role:admin'])->group(function () {
    // Dashboard & Analytics
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard.index');
    Route::get('/dashboard/login-history', [AdminDashboardController::class, 'loginHistory'])->name('admin.dashboard.login-history');
    Route::get('/dashboard/refleksi-history', [AdminDashboardController::class, 'refleksiHistory'])->name('admin.dashboard.refleksi-history');
    Route::get('/dashboard/mood-history', [AdminDashboardController::class, 'moodHistory'])->name('admin.dashboard.mood-history');
    Route::get('/dashboard/user-activity/{userId}', [AdminDashboardController::class, 'userActivity'])->name('admin.dashboard.user-activity');
    Route::get('/dashboard/audit-logs', [AdminDashboardController::class, 'auditLogs'])->name('admin.dashboard.audit-logs');

    // Backup & Recovery
    Route::get('/backup', [AdminBackupController::class, 'index'])->name('admin.backup.index');
    Route::get('/backup/export/{dataset}.{format}', [AdminBackupController::class, 'export'])->name('admin.backup.export');

    // Rollover Tahun Ajaran
    Route::get('/rollover', [YearRolloverController::class, 'create'])->name('admin.rollover.create');
    Route::post('/rollover/dry-run', [YearRolloverController::class, 'dryRun'])->name('admin.rollover.dryrun');
    Route::post('/rollover/run', [YearRolloverController::class, 'run'])->name('admin.rollover.run');
    Route::get('/rollover/{run}', [YearRolloverController::class, 'show'])->name('admin.rollover.show');
    Route::get('/rollover/{run}/json', [YearRolloverController::class, 'json'])->name('admin.rollover.json');

    // Roster Import (Admin)
    Route::get('/roster-import', [RosterImportController::class, 'index'])->name('admin.roster.index');
    Route::get('/roster-import/template', [RosterImportController::class, 'template'])->name('admin.roster.template');
    Route::post('/roster-import/preview', [RosterImportController::class, 'preview'])->name('admin.roster.preview');
    Route::post('/roster-import/commit', [RosterImportController::class, 'commit'])->name('admin.roster.commit');

    // Kelas
    Route::get('/kelas', [KelasWebController::class, 'index'])->name('admin.kelas.index');
    Route::post('/kelas', [KelasWebController::class, 'storeAjax'])->name('admin.kelas.store');
    Route::put('/kelas/{kelas}', [KelasWebController::class, 'updateAjax'])->name('admin.kelas.update');
    Route::delete('/kelas/{kelas}', [KelasWebController::class, 'destroyAjax'])->name('admin.kelas.destroy');
    Route::post('/kelas/{id}/restore', [KelasWebController::class, 'restore'])->name('admin.kelas.restore');
    Route::delete('/kelas/{id}/force', [KelasWebController::class, 'forceDelete'])->name('admin.kelas.force');

    // Kategori
    Route::get('/kategori', [KategoriWebController::class, 'index'])->name('admin.kategori.index');
    Route::post('/kategori/master', [KategoriWebController::class, 'storeMaster'])->name('admin.kategori.master.store');
    Route::get('/kategori/{kategori}/detail', [KategoriWebController::class, 'detail'])->name('admin.kategori.detail');
    Route::post('/kategori', [KategoriWebController::class, 'store'])->name('admin.kategori.store');
    Route::put('/kategori/{kategori}', [KategoriWebController::class, 'update'])->name('admin.kategori.update');
    Route::delete('/kategori/{kategori}', [KategoriWebController::class, 'destroy'])->name('admin.kategori.destroy');
    Route::patch('/kategori/{kategori}/active', [KategoriWebController::class, 'toggleActive'])->name('admin.kategori.toggle');
    Route::get('/kategori/trashed', [KategoriWebController::class, 'trashed'])->name('admin.kategori.trashed');
    Route::post('/kategori/{id}/restore', [KategoriWebController::class, 'restore'])->name('admin.kategori.restore');
    Route::delete('/kategori/{id}/force', [KategoriWebController::class, 'forceDelete'])->name('admin.kategori.force');
    Route::put('/kategori/master/{id}', [KategoriWebController::class, 'updateMaster'])->name('admin.kategori.master.update');
    Route::delete('/kategori/master/{id}', [KategoriWebController::class, 'destroyMaster'])->name('admin.kategori.master.destroy');
    Route::patch('/kategori/master/{id}/active', [KategoriWebController::class, 'toggleActiveMaster'])->name('admin.kategori.master.toggle');

    // Guru
    Route::get('/guru', [AdminGuruController::class, 'index'])->name('admin.guru.index');
    Route::post('/guru', [AdminGuruController::class, 'store'])->name('admin.guru.store');
    Route::put('/guru/{userId}', [AdminGuruController::class, 'update'])->name('admin.guru.update');
    Route::delete('/guru/{userId}', [AdminGuruController::class, 'destroy'])->name('admin.guru.destroy');
    Route::get('/guru/trashed', [AdminGuruController::class, 'trashed'])->name('admin.guru.trashed');
    Route::post('/guru/{userId}/restore', [AdminGuruController::class, 'restore'])->name('admin.guru.restore');
    Route::delete('/guru/{userId}/force', [AdminGuruController::class, 'forceDelete'])->name('admin.guru.force');

    // Siswa
    Route::get('/siswa', [AdminSiswaController::class, 'index'])->name('admin.siswa.index');
    Route::post('/siswa', [AdminSiswaController::class, 'store'])->name('admin.siswa.store');
    Route::put('/siswa/{userId}', [AdminSiswaController::class, 'update'])->name('admin.siswa.update');
    Route::delete('/siswa/{userId}', [AdminSiswaController::class, 'destroy'])->name('admin.siswa.destroy');
    Route::get('/siswa/trashed', [AdminSiswaController::class, 'trashed'])->name('admin.siswa.trashed');
    Route::post('/siswa/{userId}/restore', [AdminSiswaController::class, 'restore'])->name('admin.siswa.restore');
    Route::delete('/siswa/{userId}/force', [AdminSiswaController::class, 'forceDelete'])->name('admin.siswa.force');

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
    Route::get('/rekomendasi/suggest-kode', [RekomendasiWebController::class, 'suggestKode'])->name('admin.rekomendasi.suggest_kode');
    Route::get('/rekomendasi/{rekomendasi}/detail', [RekomendasiWebController::class, 'detail'])->name('admin.rekomendasi.detail');
    Route::post('/rekomendasi', [RekomendasiWebController::class, 'store'])->name('admin.rekomendasi.store');
    Route::put('/rekomendasi/{rekomendasi}', [RekomendasiWebController::class, 'update'])->name('admin.rekomendasi.update');
    Route::delete('/rekomendasi/{rekomendasi}', [RekomendasiWebController::class, 'destroy'])->name('admin.rekomendasi.destroy');
    Route::patch('/rekomendasi/{rekomendasi}/active', [RekomendasiWebController::class, 'toggleActive'])->name('admin.rekomendasi.toggle');

    // Manajemen User (gabungan Guru & Siswa)
    Route::get('/users', [AdminUserManagementController::class, 'index'])->name('admin.users.index');
    Route::get('/users/trashed', [AdminUserManagementController::class, 'trashed'])->name('admin.users.trashed');
    Route::post('/users/{userId}/restore', [AdminUserManagementController::class, 'restore'])->name('admin.users.restore');
    Route::delete('/users/{userId}/force', [AdminUserManagementController::class, 'forceDelete'])->name('admin.users.force');
    Route::post('/users/{userId}/reset-password', [AdminUserManagementController::class, 'resetPassword'])->name('admin.users.reset-password');
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
    });

    // Wali Kelas
    Route::prefix('wk')->middleware('gurujenis:wali_kelas')->group(function () {
        Route::get('/', [GuruWkDashboardController::class, 'index'])->name('guru.wk.dashboard');
    });

    // Guru BK: Refleksi history lintas tahun
    Route::get('/bk/refleksi-history', [GuruRefleksiHistoryController::class, 'index'])->name('guru.bk.refleksi-history');

    // Profile (for both BK & Wali Kelas)
    Route::get('/profile', [\App\Http\Controllers\Web\GuruProfileController::class, 'index'])->name('guru.profile.index');
    Route::post('/profile/password', [\App\Http\Controllers\Web\GuruProfileController::class, 'updatePassword'])->name('guru.profile.password');
    Route::post('/profile', [\App\Http\Controllers\Web\GuruProfileController::class, 'updateProfile'])->name('guru.profile.update');

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
        Route::post('/{analisis}/handling-status', [AnalisisEntryController::class, 'handlingStatus'])->name('guru.analisis.handling_status');
        Route::get('/{analisis}/rekomendasi/{rid}/alternatives', [AnalisisEntryController::class, 'alternatives'])->name('guru.analisis.alternatives');
    });

    /** ===================== REFERRAL & PRIVATE KONSELING ===================== */
    Route::post('/referrals', [\App\Http\Controllers\Web\CounselingReferralController::class, 'store'])->name('guru.referrals.store');
    Route::post('/referrals/{id}/accept', [\App\Http\Controllers\Web\CounselingReferralController::class, 'accept'])->middleware('gurujenis:bk')->name('guru.referrals.accept');
    Route::get('/referrals/{id}/private/create', [\App\Http\Controllers\Web\CounselingReferralController::class, 'createPrivateSlot'])->middleware('gurujenis:bk')->name('guru.guru_bk.private_slots.create');
    Route::post('/referrals/{id}/private/schedule', [\App\Http\Controllers\Web\CounselingReferralController::class, 'schedule'])->middleware('gurujenis:bk')->name('guru.guru_bk.private_slots.schedule');
    Route::post('/referrals/analysis/{analisis}/direct', [\App\Http\Controllers\Web\CounselingReferralController::class, 'createFromAnalysis'])->middleware('gurujenis:bk')->name('guru.referrals.analysis.direct');

    // Notifikasi - tandai semua dibaca
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read_all');

    // Tren Emosi (Dashboard chart + halaman khusus)
    Route::get('/tren-emosi', [EmosiTrenController::class, 'index'])->name('guru.tren_emosi.index');
    Route::get('/tren-emosi/data', [EmosiTrenController::class, 'data'])->name('guru.tren_emosi.data');
    Route::get('/tren-emosi/siswa', [EmosiTrenController::class, 'siswaByKelas'])->name('guru.tren_emosi.siswa');

    // Refleksi Siswa (baru) - lintas semua guru
    Route::get('/refleksi', [GuruRefleksiController::class, 'index'])->name('guru.refleksi.index');
});

/** ===================== SISWA ===================== */
Route::prefix('siswa')->middleware(['auth', 'role:siswa'])->group(function () {
    Route::get('/', [SiswaDashboardController::class, 'index'])->name('siswa.dashboard');
    Route::get('/private-session', [\App\Http\Controllers\Web\SiswaPrivateSessionController::class, 'show'])->name('siswa.private_session.show');
});

Route::redirect('/admin/dashboard', '/admin');
Route::redirect('/guru/guru_bk/dashboard', '/guru/bk');
Route::redirect('/guru/wali_kelas/dashboard', '/guru/wk');

//nyoba dummy ML API
Route::get('/ml/health', [MlBridgeController::class, 'health']);
Route::post('/ml/try', [MlBridgeController::class, 'tryAnalyze']);
