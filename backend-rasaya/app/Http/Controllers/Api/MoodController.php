<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMoodRequest;
use App\Models\PemantauanEmosiSiswa;
use App\Models\SiswaKelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\CarbonInterface;

class MoodController extends Controller
{
    private function publicFileUrl(Request $r, ?string $path): ?string
    {
        if (!$path)
            return null;
        return $r->getSchemeAndHttpHost() . '/storage/' . ltrim($path, '/');
    }
    private function getActiveRosterId(Request $r): int
    {
        $user = $r->user();
        $roster = SiswaKelas::where('siswa_id', $user->id)->where('is_active', true)->latest('id')->first();
        abort_unless($roster, 422, 'Data kelas aktif siswa tidak ditemukan.');
        return (int) $roster->id;
    }

    public function store(StoreMoodRequest $r)
    {
        $data = $r->validated();
        $tanggal = $data['tanggal'] ?? now()->toDateString();
        $sesi = $this->resolveSesi(now());
        $siswaKelasId = $this->getActiveRosterId($r);

        $existing = PemantauanEmosiSiswa::where([
            'siswa_kelas_id' => $siswaKelasId,
            'tanggal' => $tanggal,
            'sesi' => $sesi,
        ])->first();

        // handle upload
        $path = $existing?->gambar;
        if ($r->hasFile('gambar')) {
            if ($path)
                Storage::disk('public')->delete($path);
            $path = $r->file('gambar')->store('moods', 'public'); // simpan relatif
        }

        $row = PemantauanEmosiSiswa::updateOrCreate(
            ['siswa_kelas_id' => $siswaKelasId, 'tanggal' => $tanggal, 'sesi' => $sesi],
            [
                'skor' => (int) $data['skor'],
                'catatan' => $data['catatan'] ?? null,
                'gambar' => $path, // simpan path relatif, ex: moods/abc.jpg
            ]
        );

        $payload = $row->toArray();
        $payload['gambar_url'] = $this->publicFileUrl($r, $row->gambar); // <- pakai helper
        return response()->json($payload, 201);
    }

    public function today(Request $r)
    {
        $tanggal = $r->date('tanggal')?->toDateString() ?? now()->toDateString();
        $siswaKelasId = $this->getActiveRosterId($r);

        $items = PemantauanEmosiSiswa::where('siswa_kelas_id', $siswaKelasId)
            ->whereDate('tanggal', $tanggal)
            ->orderBy('sesi')
            ->get()
            ->map(fn($m) => array_merge($m->toArray(), [
                'gambar_url' => $this->publicFileUrl($r, $m->gambar), // <- pakai helper
            ]));

        return ['tanggal' => $tanggal, 'sesi_now' => $this->resolveSesi(now()), 'items' => $items];
    }

    public function history(Request $r)
    {
        $q = PemantauanEmosiSiswa::where('siswa_kelas_id', $this->getActiveRosterId($r));
        if ($r->filled('tanggal_from'))
            $q->whereDate('tanggal', '>=', $r->date('tanggal_from'));
        if ($r->filled('tanggal_to'))
            $q->whereDate('tanggal', '<=', $r->date('tanggal_to'));
        $rows = $q->orderByDesc('tanggal')->orderBy('sesi')->paginate((int) $r->input('per_page', 20));
        $rows->getCollection()->transform(fn($m) => array_merge($m->toArray(), [
            'gambar_url' => $this->publicFileUrl($r, $m->gambar), // <- pakai helper
        ]));
        return $rows;
    }

    public function update(StoreMoodRequest $r, int $id)
    {
        $siswaKelasId = $this->getActiveRosterId($r);
        $mood = PemantauanEmosiSiswa::where('id', $id)
            ->where('siswa_kelas_id', $siswaKelasId)
            ->firstOrFail();

        $data = $r->validated();
        $path = $mood->gambar;

        if ($r->hasFile('gambar')) {
            if ($path)
                Storage::disk('public')->delete($path);
            $path = $r->file('gambar')->store('moods', 'public');
        }

        $mood->update([
            'skor' => (int) $data['skor'],
            'catatan' => $data['catatan'] ?? null,
            'gambar' => $path,
        ]);

        $payload = $mood->toArray();
        $payload['gambar_url'] = $this->publicFileUrl($r, $mood->gambar);
        return response()->json($payload);
    }

    private function resolveSesi(CarbonInterface $now): string
    {
        $h = (int) $now->copy()->setTimezone(config('app.timezone'))->format('H');
        return $h < 13 ? 'pagi' : 'sore';
    }
}