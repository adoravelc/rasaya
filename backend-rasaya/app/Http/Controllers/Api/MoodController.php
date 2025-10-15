<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMoodRequest;
use App\Models\PemantauanEmosiSiswa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MoodController extends Controller
{
    /**
     * Simpan / update mood sesi (pagi/sore) hari ini.
     * - Unik per (siswa_id, tanggal, sesi) -> updateOrCreate
     * - sesi ditentukan otomatis oleh server
     */
    public function store(StoreMoodRequest $r)
    {
        $user = $r->user();
        $siswaUserId = optional($user->siswa)->user_id;   // relasi: siswas.user_id
        abort_if(!$siswaUserId, 403);

        $data = $r->validated();
        $tanggal = ($data['tanggal'] ?? now()->toDateString());
        $sesi = $this->resolveSesi(now());

        $row = PemantauanEmosiSiswa::updateOrCreate(
            ['siswa_id' => $siswaUserId, 'tanggal' => $tanggal, 'sesi' => $sesi],
            ['skor' => $data['skor'], 'gambar' => $data['gambar'] ?? null]
        );

        return response()->json($row, 201);
    }

    /**
     * Cek status hari ini (pagi/sore) milik siswa login.
     * Query: ?tanggal=YYYY-MM-DD (opsional, default=hari ini)
     */
    public function today(Request $r)
    {
        $user = $r->user();
        $siswaUserId = optional($user->siswa)->user_id;
        abort_if(!$siswaUserId, 403);

        $tanggal = $r->date('tanggal')?->toDateString() ?? now()->toDateString();

        $rows = PemantauanEmosiSiswa::where('siswa_id', $siswaUserId)
            ->whereDate('tanggal', $tanggal)
            ->orderBy('sesi')
            ->get();

        // bantu front-end: sesi yang sedang aktif
        $sesiNow = $this->resolveSesi(now());

        return [
            'tanggal' => $tanggal,
            'sesi_now' => $sesiNow,
            'items' => $rows,
        ];
    }

    /**
     * Riwayat:
     * - siswa: riwayat dirinya sendiri (paginate)
     * - admin/guru: bisa filter siswa_id & range tanggal
     * Query: ?page=1&per_page=20&tanggal_from=YYYY-MM-DD&tanggal_to=YYYY-MM-DD&siswa_id=123
     */
    public function history(Request $r)
    {
        $q = PemantauanEmosiSiswa::query();

        if ($r->user()->role === 'siswa') {
            $siswaUserId = optional($r->user()->siswa)->user_id;
            abort_if(!$siswaUserId, 403);
            $q->where('siswa_id', $siswaUserId);
        } else {
            if ($r->filled('siswa_id')) {
                $q->where('siswa_id', (int) $r->input('siswa_id'));
            }
        }

        if ($r->filled('tanggal_from')) {
            $q->whereDate('tanggal', '>=', $r->date('tanggal_from'));
        }
        if ($r->filled('tanggal_to')) {
            $q->whereDate('tanggal', '<=', $r->date('tanggal_to'));
        }

        $per = (int) ($r->input('per_page', 20));
        return $q->orderByDesc('tanggal')->orderBy('sesi')->paginate($per);
    }

    /** Tentukan sesi otomatis (silakan atur batas waktunya) */
    private function resolveSesi(\Carbon\CarbonInterface $now): string
    {
        $local = $now->copy()->setTimezone(config('app.timezone')); // Asia/Makassar
        $hour = (int) $local->format('H'); // 0..23
        // cutoff 13:00 → <=12 pagi, >=13 sore
        return $hour < 13 ? 'pagi' : 'sore';
    }
}
