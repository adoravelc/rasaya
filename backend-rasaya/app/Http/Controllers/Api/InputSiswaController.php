<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InputSiswa;
use App\Models\SiswaKelas;
use App\Http\Requests\StoreInputSiswaRequest;
use App\Services\GuestSandboxService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class InputSiswaController extends Controller
{
    private function sandbox(): GuestSandboxService
    {
        return app(GuestSandboxService::class);
    }

    private function guestPaginate(array $items, int $page, int $perPage): array
    {
        $total = count($items);
        $offset = max(0, ($page - 1) * $perPage);
        $slice = array_slice($items, $offset, $perPage);

        return [
            'current_page' => $page,
            'data' => array_values($slice),
            'from' => $total > 0 ? ($offset + 1) : null,
            'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
            'per_page' => $perPage,
            'to' => $total > 0 ? min($offset + $perPage, $total) : null,
            'total' => $total,
        ];
    }

    private function publicFileUrl(Request $r, ?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Samakan dengan InputGuru::getGambarUrlAttribute agar konsisten
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
        $roster = SiswaKelas::query()
            ->where('siswa_id', optional($user->siswa)->user_id) // siswas.user_id
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (!$roster)
            abort(422, 'Data kelas aktif siswa tidak ditemukan.');
        return (int) $roster->id;
    }

    public function index(Request $r)
    {
        try {
            $user = $r->user();

            if ($this->sandbox()->isGuestSiswa($r) && $user->role === 'siswa') {
                $rosterId = $this->getActiveRosterId($r);
                $today = now()->toDateString();
                $rows = array_values(array_filter($this->sandbox()->getRefleksi($r), function (array $item) use ($r, $rosterId, $today) {
                    if ((int) ($item['siswa_kelas_id'] ?? 0) !== $rosterId) {
                        return false;
                    }

                    if ($r->has('status_upload')) {
                        $status = (int) $r->input('status_upload');
                        if ((int) ($item['status_upload'] ?? 1) !== $status) {
                            return false;
                        }
                        if ($status === 0 && ($item['tanggal'] ?? null) !== $today) {
                            return false;
                        }
                    }

                    if ($r->filled('jenis')) {
                        $jenis = (string) $r->input('jenis');
                        if ($jenis === 'laporan' && !($item['is_friend'] ?? false)) {
                            return false;
                        }
                        if ($jenis !== 'laporan' && ($item['is_friend'] ?? false)) {
                            return false;
                        }
                    }

                    return true;
                }));

                usort($rows, fn($a, $b) => ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0)));
                $isDraftRequest = $r->has('status_upload') && (int) $r->input('status_upload') === 0;
                $perPage = $isDraftRequest ? 1 : $r->integer('per_page', 10);
                $page = max(1, $r->integer('page', 1));

                return response()->json($this->guestPaginate($rows, $page, $perPage));
            }

            // === LOGIC UNTUK SISWA ===
            if ($user->role === 'siswa') {
                $rosterId = $this->getActiveRosterId($r);

                $q = InputSiswa::with(['siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user'])
                    ->where('siswa_kelas_id', $rosterId);

                // --- 1. Filter Status Upload (Draft vs Final) ---
                if ($r->has('status_upload')) {
                    $status = (int) $r->input('status_upload');
                    $q->where('status_upload', $status);

                    // --- TAMBAHAN PENTING: Draft Expired Logic ---
                    // Jika mencari DRAFT (0), pastikan tanggalnya HARI INI.
                    // Draft kemarin dianggap basi/expired, jadi jangan dimunculkan.
                    if ($status === 0) {
                        $q->whereDate('tanggal', now()->toDateString());
                    }
                }

                // 2. Filter Jenis (Mapping ke is_friend)
                if ($r->filled('jenis')) {
                    $jenis = $r->input('jenis');
                    if ($jenis === 'laporan') {
                        $q->where('is_friend', true);
                    } else {
                        $q->where('is_friend', false);
                    }
                }

                // 3. Sorting Aman
                $q->orderByDesc('id');

                // 4. Limit Logic
                $isDraftRequest = $r->has('status_upload') && (int) $r->input('status_upload') === 0;
                $perPage = $isDraftRequest ? 1 : $r->integer('per_page', 10);

                $rows = $q->paginate($perPage);

                // Transform output
                $rows->getCollection()->transform(function ($m) use ($r) {
                    $arr = $m->toArray();
                    $arr['gambar_url'] = $this->publicFileUrl($r, $m->gambar);
                    if ($m->siswaDilaporKelas && $m->siswaDilaporKelas->siswa && $m->siswaDilaporKelas->siswa->user) {
                        $arr['siswa_dilapor_nama'] = $m->siswaDilaporKelas->siswa->user->name ?? null;
                    }
                    return $arr;
                });
                return $rows;
            }

            // ... (Kode Admin/Guru di bawah biarkan sama) ...
            $q = InputSiswa::with(['siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user']);
            // ... (lanjutkan kode admin seperti file aslimu) ...
            if ($r->filled('siswa_kelas_id')) {
                $q->where('siswa_kelas_id', (int) $r->input('siswa_kelas_id'));
            }
            if ($r->filled('tanggal')) {
                $q->whereDate('tanggal', $r->date('tanggal'));
            }
            $rows = $q->orderByDesc('id')->paginate($r->integer('per_page', 10));
            $rows->getCollection()->transform(function ($m) use ($r) {
                $arr = $m->toArray();
                $arr['gambar_url'] = $this->publicFileUrl($r, $m->gambar);
                return $arr;
            });
            return $rows;

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'DEBUG ERROR: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }

        // admin/guru
        $q = InputSiswa::with(['siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user']);
        if ($r->filled('siswa_kelas_id')) {
            $q->where('siswa_kelas_id', (int) $r->input('siswa_kelas_id'));
        }
        if ($r->filled('tanggal')) {
            $q->whereDate('tanggal', $r->date('tanggal'));
        }
        $rows = $q->orderByDesc('tanggal')->paginate($r->integer('per_page', 10));
        $rows->getCollection()->transform(function ($m) use ($r) {
            $arr = $m->toArray();
            $arr['gambar_url'] = $this->publicFileUrl($r, $m->gambar);
            return $arr;
        });
        return $rows;
    }

    public function store(StoreInputSiswaRequest $r)
    {
        $user = $r->user();
        $data = $r->validated();

        // Pelapor: admin bisa kirim siswa_kelas_id; siswa pakai roster aktif
        $siswaKelasId = $data['siswa_kelas_id'] ?? ($user->role === 'siswa' ? $this->getActiveRosterId($r) : null);
        abort_if(!$siswaKelasId, 403, 'siswa_kelas_id wajib.');

        // Cegah melaporkan diri sendiri
        if (!empty($data['siswa_dilapor_kelas_id']) && (int) $data['siswa_dilapor_kelas_id'] === (int) $siswaKelasId) {
            return response()->json(['message' => 'Tidak boleh melaporkan diri sendiri.'], 422);
        }

        // Tentukan jenis input: self vs friend
        $isFriend = !empty($data['siswa_dilapor_kelas_id']);
        $tanggal = $data['tanggal'] ?? now()->toDateString();

        // Batasi: maksimal 1 self per hari dan 1 lapor teman per hari per siswa_kelas
        if ($this->sandbox()->isGuestSiswa($r)) {
            $items = $this->sandbox()->getRefleksi($r);
            $exists = collect($items)->contains(function (array $item) use ($siswaKelasId, $tanggal, $isFriend) {
                return (int) ($item['siswa_kelas_id'] ?? 0) === (int) $siswaKelasId
                    && (string) ($item['tanggal'] ?? '') === (string) $tanggal
                    && (bool) ($item['is_friend'] ?? false) === (bool) $isFriend;
            });

            if ($exists) {
                $msg = $isFriend
                    ? 'Kamu sudah melaporkan teman hari ini. Coba lagi besok, ya.'
                    : 'Kamu sudah isi refleksi diri hari ini. Terima kasih! Coba lagi besok.';
                return response()->json(['message' => $msg], 422);
            }

            $row = [
                'id' => $this->sandbox()->nextRefleksiId($r),
                'siswa_kelas_id' => $siswaKelasId,
                'siswa_dilapor_kelas_id' => $data['siswa_dilapor_kelas_id'] ?? null,
                'is_friend' => $isFriend,
                'tanggal' => $tanggal,
                'teks' => $data['teks'],
                'gambar' => null,
                'gambar_url' => null,
                'status_upload' => (int) ($data['status_upload'] ?? 1),
                'meta' => $data['meta'] ?? null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            $items[] = $row;
            $this->sandbox()->putRefleksi($r, $items);

            return response()->json($row, 201);
        }

        $exists = InputSiswa::query()
            ->where('siswa_kelas_id', $siswaKelasId)
            ->whereDate('tanggal', $tanggal)
            ->where('is_friend', $isFriend)
            ->exists();
        if ($exists) {
            $msg = $isFriend
                ? 'Kamu sudah melaporkan teman hari ini. Coba lagi besok, ya.'
                : 'Kamu sudah isi refleksi diri hari ini. Terima kasih! Coba lagi besok.';
            return response()->json(['message' => $msg], 422);
        }

        // handle upload gambar (opsional)
        $path = null;
        if ($r->hasFile('gambar')) {
            $path = $r->file('gambar')->store('inputs', 'public');
        }

        $row = InputSiswa::create([
            'siswa_kelas_id' => $siswaKelasId,
            'siswa_dilapor_kelas_id' => $data['siswa_dilapor_kelas_id'] ?? null,
            'is_friend' => $isFriend,
            'tanggal' => $tanggal,
            'teks' => $data['teks'],
            'gambar' => $path,
            'status_upload' => (int) ($data['status_upload'] ?? 1),
            'meta' => $data['meta'] ?? null,
        ]);


        $row->load(['siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user']);
        $payload = $row->toArray();
        $payload['gambar_url'] = $this->publicFileUrl($r, $row->gambar);
        return response()->json($payload, 201);
    }

    public function show(Request $r, int $inputSiswaId)
    {
        if ($this->sandbox()->isGuestSiswa($r)) {
            $item = collect($this->sandbox()->getRefleksi($r))
                ->first(fn(array $x) => (int) ($x['id'] ?? 0) === $inputSiswaId);

            abort_if(!$item, 404);
            return $item;
        }

        $inputSiswa = InputSiswa::findOrFail($inputSiswaId);
        $inputSiswa->load(['siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user']);
        $arr = $inputSiswa->toArray();
        $arr['gambar_url'] = $this->publicFileUrl($r, $inputSiswa->gambar);
        return $arr;
    }

    public function update(StoreInputSiswaRequest $r, int $inputSiswaId)
    {
        try {
            if ($this->sandbox()->isGuestSiswa($r)) {
                $items = $this->sandbox()->getRefleksi($r);
                $index = collect($items)->search(fn(array $x) => (int) ($x['id'] ?? 0) === $inputSiswaId);
                abort_if($index === false, 404);

                $data = $r->validated();
                $current = $items[$index];

                if (!empty($data['siswa_dilapor_kelas_id']) && (int) $data['siswa_dilapor_kelas_id'] === (int) ($current['siswa_kelas_id'] ?? 0)) {
                    return response()->json(['message' => 'Tidak boleh melaporkan diri sendiri.'], 422);
                }

                $current['tanggal'] = $data['tanggal'] ?? $current['tanggal'];
                $current['teks'] = $data['teks'] ?? $current['teks'];
                $current['status_upload'] = (int) ($data['status_upload'] ?? $current['status_upload']);
                $current['meta'] = $data['meta'] ?? $current['meta'];
                if (array_key_exists('siswa_dilapor_kelas_id', $data)) {
                    $current['siswa_dilapor_kelas_id'] = $data['siswa_dilapor_kelas_id'];
                    $current['is_friend'] = !empty($data['siswa_dilapor_kelas_id']);
                }
                $current['updated_at'] = now()->toISOString();

                $items[$index] = $current;
                $this->sandbox()->putRefleksi($r, $items);

                return response()->json($current, 200);
            }

            $inputSiswa = InputSiswa::findOrFail($inputSiswaId);
            $user = $r->user();
            $isOwner = false;

            // LOGIC CEK OWNER YANG DIPERBAIKI
            if ($user->role === 'siswa') {
                // Ambil roster aktif saat ini
                $activeRosterId = $this->getActiveRosterId($r);

                // Bandingkan sebagai integer agar aman (5 vs "5")
                $isOwner = (int) $inputSiswa->siswa_kelas_id === (int) $activeRosterId;

                // FALLBACK: Cek apakah user_id nya sama (jika roster_id beda tapi orangnya sama)
                if (!$isOwner) {
                    // Load relasi siswaKelas -> siswa -> user
                    $inputSiswa->load('siswaKelas.siswa');
                    if ($inputSiswa->siswaKelas && $inputSiswa->siswaKelas->siswa) {
                        $ownerUserId = $inputSiswa->siswaKelas->siswa->user_id;
                        $isOwner = (int) $ownerUserId === (int) $user->id;
                    }
                }
            }

            // Jika admin, otomatis owner = true
            if ($user->role === 'admin') {
                $isOwner = true;
            }

            if (!$isOwner) {
                // Debugging: Boleh dibuka kalau mau lihat kenapa gagal
                // return response()->json([
                //    'message' => 'Unauthorized Debug',
                //    'data_roster' => $inputSiswa->siswa_kelas_id,
                //    'active_roster' => $this->getActiveRosterId($r)
                // ], 403);
                return response()->json(['message' => 'Unauthorized: Data ini bukan milikmu.'], 403);
            }

            // ... (SISA KODE UPDATE BIARKAN SAMA SEPERTI YANG TADI) ...
            $data = $r->validated();

            // Cegah self-report
            if (!empty($data['siswa_dilapor_kelas_id']) && (int) $data['siswa_dilapor_kelas_id'] === (int) $inputSiswa->siswa_kelas_id) {
                return response()->json(['message' => 'Tidak boleh melaporkan diri sendiri.'], 422);
            }

            // Update data
            $inputSiswa->update([
                'tanggal' => $data['tanggal'] ?? $inputSiswa->tanggal,
                'teks' => $data['teks'] ?? $inputSiswa->teks,
                'status_upload' => (int) ($data['status_upload'] ?? $inputSiswa->status_upload),
                'meta' => $data['meta'] ?? $inputSiswa->meta,
                'siswa_dilapor_kelas_id' => array_key_exists('siswa_dilapor_kelas_id', $data)
                    ? $data['siswa_dilapor_kelas_id']
                    : $inputSiswa->siswa_dilapor_kelas_id,
                'is_friend' => array_key_exists('siswa_dilapor_kelas_id', $data)
                    ? !empty($data['siswa_dilapor_kelas_id'])
                    : $inputSiswa->is_friend,
            ]);

            // Handle gambar baru
            if ($r->hasFile('gambar')) {
                if ($inputSiswa->gambar) {
                    Storage::disk('public')->delete($inputSiswa->gambar);
                }
                $newPath = $r->file('gambar')->store('inputs', 'public');
                $inputSiswa->gambar = $newPath;
                $inputSiswa->save();
            }

            $inputSiswa->refresh();
            $inputSiswa->load(['siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user']);

            $arr = $inputSiswa->toArray();
            $arr['gambar_url'] = $this->publicFileUrl($r, $inputSiswa->gambar);

            if ($inputSiswa->siswaDilaporKelas && $inputSiswa->siswaDilaporKelas->siswa && $inputSiswa->siswaDilaporKelas->siswa->user) {
                $arr['siswa_dilapor_nama'] = $inputSiswa->siswaDilaporKelas->siswa->user->name ?? null;
            }

            return response()->json($arr, 200);

        } catch (\Exception $e) {
            Log::error('Update error: ' . $e->getMessage());
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $r, int $inputSiswaId)
    {
        if ($this->sandbox()->isGuestSiswa($r)) {
            $items = array_values(array_filter(
                $this->sandbox()->getRefleksi($r),
                fn(array $x) => (int) ($x['id'] ?? 0) !== $inputSiswaId
            ));
            $this->sandbox()->putRefleksi($r, $items);
            return response()->noContent();
        }

        $inputSiswa = InputSiswa::findOrFail($inputSiswaId);
        $user = $r->user();
        $isOwner = false;
        if ($user->role === 'siswa') {
            $isOwner = $inputSiswa->siswa_kelas_id === $this->getActiveRosterId($r);
        }
        if (!($user->role === 'admin' || $isOwner))
            abort(403);

        // hapus file saat dihapus (opsional)
        if ($inputSiswa->gambar) {
            Storage::disk('public')->delete($inputSiswa->gambar);
        }
        $inputSiswa->delete();  // soft delete
        return response()->noContent();
    }

    public function todayStatus(Request $r)
    {
        $user = $r->user();
        $siswaKelasId = $user->role === 'siswa' ? $this->getActiveRosterId($r) : $r->integer('siswa_kelas_id');
        abort_if(!$siswaKelasId, 403, 'siswa_kelas_id wajib.');

        if ($this->sandbox()->isGuestSiswa($r)) {
            $today = now()->toDateString();
            $items = $this->sandbox()->getRefleksi($r);
            $hasSelf = collect($items)->contains(fn(array $x) =>
                (int) ($x['siswa_kelas_id'] ?? 0) === (int) $siswaKelasId
                && (string) ($x['tanggal'] ?? '') === $today
                && !($x['is_friend'] ?? false)
            );
            $hasFriend = collect($items)->contains(fn(array $x) =>
                (int) ($x['siswa_kelas_id'] ?? 0) === (int) $siswaKelasId
                && (string) ($x['tanggal'] ?? '') === $today
                && (bool) ($x['is_friend'] ?? false)
            );

            return [
                'date' => $today,
                'has_self_today' => $hasSelf,
                'has_friend_report_today' => $hasFriend,
            ];
        }

        $today = now()->toDateString();
        $hasSelf = InputSiswa::query()
            ->where('siswa_kelas_id', $siswaKelasId)
            ->whereDate('tanggal', $today)
            ->where('is_friend', false)
            ->exists();
        $hasFriend = InputSiswa::query()
            ->where('siswa_kelas_id', $siswaKelasId)
            ->whereDate('tanggal', $today)
            ->where('is_friend', true)
            ->exists();

        return [
            'date' => $today,
            'has_self_today' => $hasSelf,
            'has_friend_report_today' => $hasFriend,
        ];
    }
}