<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InputSiswa;
use App\Models\SiswaKelas;
use App\Http\Requests\StoreInputSiswaRequest;
use Illuminate\Support\Facades\Storage;

class InputSiswaController extends Controller
{
    private function publicFileUrl(Request $r, ?string $path): ?string
    {
        if (!$path) return null;
        return $r->getSchemeAndHttpHost() . '/storage/' . ltrim($path, '/');
    }

    private function getActiveRosterId(Request $r): int
    {
        $user = $r->user();
        $roster = SiswaKelas::query()
            ->where('siswa_id', optional($user->siswa)->user_id) // siswas.user_id
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (!$roster) abort(422, 'Data kelas aktif siswa tidak ditemukan.');
        return (int) $roster->id;
    }

    public function index(Request $r)
    {
        $user = $r->user();

        if ($user->role === 'siswa') {
            $rosterId = $this->getActiveRosterId($r);

            $rows = InputSiswa::with(['siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user'])
                ->where('siswa_kelas_id', $rosterId)
                ->orderByDesc('tanggal')
                ->paginate($r->integer('per_page', 10));

            // tambahkan gambar_url pada setiap item
            $rows->getCollection()->transform(function ($m) use ($r) {
                $arr = $m->toArray();
                $arr['gambar_url'] = $this->publicFileUrl($r, $m->gambar);
                return $arr;
            });
            return $rows;
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
        if (!empty($data['siswa_dilapor_kelas_id']) && (int)$data['siswa_dilapor_kelas_id'] === (int)$siswaKelasId) {
            return response()->json(['message' => 'Tidak boleh melaporkan diri sendiri.'], 422);
        }

        // Tentukan jenis input: self vs friend
        $isFriend = !empty($data['siswa_dilapor_kelas_id']);
        $tanggal = $data['tanggal'] ?? now()->toDateString();

        // Batasi: maksimal 1 self per hari dan 1 lapor teman per hari per siswa_kelas
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

    public function show(Request $r, InputSiswa $inputSiswa)
    {
        $inputSiswa->load(['siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user']);
        $arr = $inputSiswa->toArray();
        $arr['gambar_url'] = $this->publicFileUrl($r, $inputSiswa->gambar);
        return $arr;
    }

    public function update(StoreInputSiswaRequest $r, InputSiswa $inputSiswa)
    {
        $user = $r->user();
        $isOwner = false;
        if ($user->role === 'siswa') {
            $isOwner = $inputSiswa->siswa_kelas_id === $this->getActiveRosterId($r);
        }
        if (!($user->role === 'admin' || $isOwner)) abort(403);

        $data = $r->validated();

        // optional update siswa_dilapor_kelas_id (tetap cegah self)
        if (!empty($data['siswa_dilapor_kelas_id']) && (int)$data['siswa_dilapor_kelas_id'] === (int)$inputSiswa->siswa_kelas_id) {
            return response()->json(['message' => 'Tidak boleh melaporkan diri sendiri.'], 422);
        }

        $inputSiswa->update([
            'tanggal' => $data['tanggal'] ?? $inputSiswa->tanggal,
            'teks' => $data['teks'] ?? $inputSiswa->teks,
            'status_upload' => $data['status_upload'] ?? $inputSiswa->status_upload,
            'meta' => $data['meta'] ?? $inputSiswa->meta,
            'siswa_dilapor_kelas_id' => array_key_exists('siswa_dilapor_kelas_id', $data)
                ? $data['siswa_dilapor_kelas_id']
                : $inputSiswa->siswa_dilapor_kelas_id,
            'is_friend' => array_key_exists('siswa_dilapor_kelas_id', $data)
                ? !empty($data['siswa_dilapor_kelas_id'])
                : $inputSiswa->is_friend,
        ]);

        // handle replace gambar jika ada upload baru
        if ($r->hasFile('gambar')) {
            if ($inputSiswa->gambar) {
                Storage::disk('public')->delete($inputSiswa->gambar);
            }
            $newPath = $r->file('gambar')->store('inputs', 'public');
            $inputSiswa->gambar = $newPath;
            $inputSiswa->save();
        }

        // kategori_ids diabaikan: tabel pivot sudah dihapus

        $inputSiswa->load(['siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user']);
        $arr = $inputSiswa->toArray();
        $arr['gambar_url'] = $this->publicFileUrl($r, $inputSiswa->gambar);
        return $arr;
    }

    public function destroy(Request $r, InputSiswa $inputSiswa)
    {
        $user = $r->user();
        $isOwner = false;
        if ($user->role === 'siswa') {
            $isOwner = $inputSiswa->siswa_kelas_id === $this->getActiveRosterId($r);
        }
        if (!($user->role === 'admin' || $isOwner)) abort(403);

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