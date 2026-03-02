<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMoodRequest;
use App\Models\PemantauanEmosiSiswa;
use App\Models\SiswaKelas;
use App\Services\GuestSandboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\CarbonInterface;

class MoodController extends Controller
{
    private function sandbox(): GuestSandboxService
    {
        return app(GuestSandboxService::class);
    }

    private function publicFileUrl(Request $r, ?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Samakan logika dengan InputGuru::getGambarUrlAttribute
        // agar bisa override base URL via PUBLIC_STORAGE_URL
        $base = env('PUBLIC_STORAGE_URL');
        if (!$base) {
            $base = config('filesystems.disks.public.url');
        }
        if (!$base) {
            $appUrl = config('app.url') ?: $r->getSchemeAndHttpHost();
            $base = rtrim($appUrl, '/') . '/storage';
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
    private function getActiveRosterId(Request $r): int
    {
        $user = $r->user();
        $roster = SiswaKelas::where('siswa_id', $user->id)->where('is_active', true)->latest('id')->first();
        abort_unless((bool) $roster, 422, 'Data kelas aktif siswa tidak ditemukan.');
        return (int) $roster->id;
    }

    public function store(StoreMoodRequest $r)
    {
        $data = $r->validated();
        $tanggal = $data['tanggal'] ?? now()->toDateString();
        $sesi = $this->resolveSesi(now());
        $siswaKelasId = $this->getActiveRosterId($r);

        if ($this->sandbox()->isGuestSiswa($r)) {
            $items = $this->sandbox()->getMood($r);
            $index = collect($items)->search(fn(array $x) =>
                (int) ($x['siswa_kelas_id'] ?? 0) === $siswaKelasId
                && (string) ($x['tanggal'] ?? '') === (string) $tanggal
                && (string) ($x['sesi'] ?? '') === (string) $sesi
            );

            $row = [
                'id' => $index !== false ? (int) ($items[$index]['id'] ?? 0) : $this->sandbox()->nextMoodId($r),
                'siswa_kelas_id' => $siswaKelasId,
                'tanggal' => (string) $tanggal,
                'sesi' => (string) $sesi,
                'skor' => (int) $data['skor'],
                'catatan' => $data['catatan'] ?? null,
                'gambar' => null,
                'gambar_url' => null,
                'updated_at' => now()->toISOString(),
                'created_at' => $index !== false
                    ? ($items[$index]['created_at'] ?? now()->toISOString())
                    : now()->toISOString(),
            ];

            if ($index !== false) {
                $items[$index] = $row;
            } else {
                $items[] = $row;
            }
            $this->sandbox()->putMood($r, $items);

            return response()->json($row, 201);
        }

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
        if ($row->tanggal instanceof CarbonInterface) {
            $payload['tanggal'] = $row->tanggal->toDateString();
        }
        $payload['gambar_url'] = $this->publicFileUrl($r, $row->gambar); // <- pakai helper
        return response()->json($payload, 201);
    }

    public function today(Request $r)
    {
        $tanggal = $r->date('tanggal')?->toDateString() ?? now()->toDateString();
        $siswaKelasId = $this->getActiveRosterId($r);

        if ($this->sandbox()->isGuestSiswa($r)) {
            $items = collect($this->sandbox()->getMood($r))
                ->filter(fn(array $x) =>
                    (int) ($x['siswa_kelas_id'] ?? 0) === $siswaKelasId
                    && (string) ($x['tanggal'] ?? '') === (string) $tanggal
                )
                ->sortBy('sesi')
                ->values();

            return [
                'tanggal' => $tanggal,
                'sesi_now' => $this->resolveSesi(now()),
                'items' => $items,
            ];
        }

        $items = PemantauanEmosiSiswa::where('siswa_kelas_id', $siswaKelasId)
            ->whereDate('tanggal', $tanggal)
            ->orderBy('sesi')
            ->get()
            ->map(function ($m) use ($r) {
                $arr = is_array($m)
                    ? $m
                    : (method_exists($m, 'toArray') ? $m->toArray() : (array) $m);
                $tanggal = data_get($m, 'tanggal');
                if ($tanggal instanceof CarbonInterface) {
                    $arr['tanggal'] = $tanggal->toDateString();
                }
                $arr['gambar_url'] = $this->publicFileUrl($r, data_get($m, 'gambar')); // <- pakai helper
                return $arr;
            });

        return ['tanggal' => $tanggal, 'sesi_now' => $this->resolveSesi(now()), 'items' => $items];
    }

    public function history(Request $r)
    {
        if ($this->sandbox()->isGuestSiswa($r)) {
            $siswaKelasId = $this->getActiveRosterId($r);
            $rows = collect($this->sandbox()->getMood($r))
                ->filter(fn(array $x) => (int) ($x['siswa_kelas_id'] ?? 0) === $siswaKelasId)
                ->filter(function (array $x) use ($r) {
                    $tgl = (string) ($x['tanggal'] ?? '');
                    if ($r->filled('tanggal_from') && $tgl < (string) $r->input('tanggal_from')) {
                        return false;
                    }
                    if ($r->filled('tanggal_to') && $tgl > (string) $r->input('tanggal_to')) {
                        return false;
                    }
                    return true;
                })
                ->sortByDesc('tanggal')
                ->values()
                ->all();

            $perPage = (int) $r->input('per_page', 20);
            $page = max(1, (int) $r->input('page', 1));
            $total = count($rows);
            $offset = ($page - 1) * $perPage;
            $slice = array_slice($rows, $offset, $perPage);

            return response()->json([
                'current_page' => $page,
                'data' => array_values($slice),
                'from' => $total > 0 ? ($offset + 1) : null,
                'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
                'per_page' => $perPage,
                'to' => $total > 0 ? min($offset + $perPage, $total) : null,
                'total' => $total,
            ]);
        }

        $q = PemantauanEmosiSiswa::where('siswa_kelas_id', $this->getActiveRosterId($r));
        if ($r->filled('tanggal_from'))
            $q->whereDate('tanggal', '>=', $r->date('tanggal_from'));
        if ($r->filled('tanggal_to'))
            $q->whereDate('tanggal', '<=', $r->date('tanggal_to'));
        $rows = $q->orderByDesc('tanggal')->orderBy('sesi')->paginate((int) $r->input('per_page', 20));
        $rows->getCollection()->transform(function ($m) use ($r) {
            $arr = is_array($m)
                ? $m
                : (method_exists($m, 'toArray') ? $m->toArray() : (array) $m);
            $tanggal = data_get($m, 'tanggal');
            if ($tanggal instanceof CarbonInterface) {
                $arr['tanggal'] = $tanggal->toDateString();
            }
            $arr['gambar_url'] = $this->publicFileUrl($r, data_get($m, 'gambar')); // <- pakai helper
            return $arr;
        });
        return $rows;
    }

    public function update(StoreMoodRequest $r, int $id)
    {
        if ($this->sandbox()->isGuestSiswa($r)) {
            $items = $this->sandbox()->getMood($r);
            $index = collect($items)->search(fn(array $x) => (int) ($x['id'] ?? 0) === $id);
            abort_if($index === false, 404);

            $data = $r->validated();
            $row = $items[$index];
            $row['skor'] = (int) $data['skor'];
            $row['catatan'] = $data['catatan'] ?? null;
            $row['updated_at'] = now()->toISOString();

            $items[$index] = $row;
            $this->sandbox()->putMood($r, $items);

            return response()->json($row);
        }

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
        if ($mood->tanggal instanceof CarbonInterface) {
            $payload['tanggal'] = $mood->tanggal->toDateString();
        }
        $payload['gambar_url'] = $this->publicFileUrl($r, $mood->gambar);
        return response()->json($payload);
    }

    private function resolveSesi(CarbonInterface $now): string
    {
        $h = (int) $now->copy()->setTimezone(config('app.timezone'))->format('H');
        return $h < 13 ? 'pagi' : 'sore';
    }
}