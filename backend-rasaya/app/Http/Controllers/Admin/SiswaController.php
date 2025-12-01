<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Siswa; // Model sudah benar, model Siswa = tabel siswas
use App\Models\SiswaKelas;
use App\Models\TahunAjaran;
use Illuminate\Support\Facades\Log;

class SiswaController extends Controller
{
    // ...existing code...

    public function deactivate(Request $r, Siswa $siswa)
    {
        $r->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);
        $siswa->update([
            'is_active' => false,
            'inactive_reason' => $r->reason,
            'inactive_at' => now(),
        ]);
        // Nonaktifkan semua kelas di tahun ajaran aktif
        $tahunAktif = TahunAjaran::where('is_active', true)->first();
        if ($tahunAktif) {
            SiswaKelas::where('siswa_id', $siswa->id)
                ->whereHas('kelas', fn($q) => $q->where('tahun_ajaran_id', $tahunAktif->id))
                ->update(['is_active' => false]);
        }
        Log::info('Siswa dinonaktifkan', [
            'siswa_id' => $siswa->id,
            'reason' => $r->reason,
            'by' => $r->user()->id,
        ]);
        return back()->with('ok', 'Siswa dinonaktifkan.');
    }

    public function activate(Request $r, Siswa $siswa)
    {
        $siswa->update([
            'is_active' => true,
            'inactive_reason' => null,
            'inactive_at' => null,
        ]);
        // (Opsional) Aktifkan kembali status kelas jika kelas masih aktif
        $tahunAktif = TahunAjaran::where('is_active', true)->first();
        if ($tahunAktif) {
            SiswaKelas::where('siswa_id', $siswa->id)
                ->whereHas('kelas', fn($q) => $q->where('tahun_ajaran_id', $tahunAktif->id))
                ->update(['is_active' => true]);
        }
        Log::info('Siswa diaktifkan kembali', [
            'siswa_id' => $siswa->id,
            'by' => $r->user()->id,
        ]);
        return back()->with('ok', 'Siswa diaktifkan kembali.');
    }
}
