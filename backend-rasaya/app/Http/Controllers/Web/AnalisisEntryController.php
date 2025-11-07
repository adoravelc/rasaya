<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AnalisisEntry;
use App\Models\AnalisisRekomendasi;
use App\Models\KategoriMasalah;
use App\Models\MasterRekomendasi;
use App\Models\InputGuru;
use App\Models\InputSiswa;
use App\Models\PemantauanEmosiSiswa;
use App\Models\SiswaKelas;
use App\Services\AnalisisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AnalisisEntryController extends Controller
{
    public function __construct(private AnalisisService $svc)
    {
    }

    public function index(Request $r)
    {
        // daftar hasil analisis; wali kelas hanya melihat siswanya sendiri
        $q = AnalisisEntry::query()->with([
                'rekomendasis.master',
                'siswaKelas.kelas',
                'siswaKelas.tahunAjaran',
                'siswaKelas.siswa.user'
            ])->latest()
            ->where('created_by', $r->user()->id);

        $guru = optional($r->user())->guru;
        if ($guru && $guru->jenis === 'wali_kelas') {
            $userId = $r->user()->id;
            $q->whereHas('siswaKelas.kelas', function ($qq) use ($userId) {
                $qq->where('wali_guru_id', $userId);
            });
        }

        return view('roles.guru.analisis.index', [
            'rows' => $q->paginate(12)
        ]);
    }

    public function create(Request $r)
    {
        // daftar siswa untuk dropdown
        $guru = optional($r->user())->guru;
        $q = SiswaKelas::with(['siswa.user', 'kelas'])->orderBy('id', 'desc');
        if ($guru && $guru->jenis === 'wali_kelas') {
            $q->whereHas('kelas', function ($qq) use ($r) {
                $qq->where('wali_guru_id', $r->user()->id);
            });
        }
        $siswas = $q->limit(200)->get();
        return view('roles.guru.analisis.create', compact('siswas'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'siswa_kelas_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        // Termasuk semua catatan guru (BK dan WK) dalam analisis untuk siswa tsb
        $includeAllGuruNotes = true;

        $entry = $this->svc->analisisRentang(
            (int) $data['siswa_kelas_id'],
            $data['from'],
            $data['to'],
            (int) $r->user()->id,
            $includeAllGuruNotes,
        );

        return redirect()
            ->route('guru.analisis.show', $entry->id)
            ->with('ok', 'Analisis selesai.');
    }

    public function show(AnalisisEntry $analisis)
    {
        $analisis->load(['rekomendasis', 'siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan', 'createdBy']);

    $isWali = optional(Auth::user()->guru)->jenis === 'wali_kelas';

        // Kumpulkan semua input yang termasuk dalam rentang analisis ini
        $from = optional($analisis->tanggal_awal_proses)?->toDateString();
        $to = optional($analisis->tanggal_akhir_proses)?->toDateString();

        $refleksisSelf = collect();
        $friendReports = collect();
        $guruNotes = collect();

        $used = collect($analisis->used_items ?? []);
        $topEmojis = collect();
        $avgMood = $analisis->avg_mood; // already stored at analysis time

        if ($used->isNotEmpty()) {
            // Ambil data tepat yang dipakai saat analisis (snapshot IDs)
            $selfIds = $used->where('type', 'ref_self')->pluck('id')->all();
            $friendIds = $used->where('type', 'ref_friend')->pluck('id')->all();
            $guruIds = $used->where('type', 'guru')->pluck('id')->all();

            if (!empty($selfIds)) {
                $refleksisSelf = InputSiswa::with(['kategoris', 'siswaKelas.siswa.user'])
                    ->whereIn('id', $selfIds)
                    ->where('is_friend', false) // jaga-jaga: filter hanya refleksi diri
                    ->orderBy('tanggal', 'desc')
                    ->get();
            }
            if (!empty($friendIds)) {
                $friendReports = InputSiswa::with(['kategoris', 'siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user'])
                    ->whereIn('id', $friendIds)
                    ->where('is_friend', true) // jaga-jaga: pastikan ini laporan teman
                    ->orderBy('tanggal', 'desc')
                    ->get();
            }
            if (!empty($guruIds)) {
                $guruNotes = InputGuru::with(['kategoris', 'siswaKelas.siswa.user'])
                    ->whereIn('id', $guruIds)
                    ->orderBy('tanggal', 'desc')
                    ->get();
            }
        } elseif ($from && $to) {
            // Fallback untuk analisis lama (sebelum ada snapshot): gunakan rentang tanggal
            $refleksisSelf = InputSiswa::with(['kategoris', 'siswaKelas.siswa.user'])
                ->where('siswa_kelas_id', $analisis->siswa_kelas_id)
                ->whereBetween('tanggal', [$from, $to])
                ->where('is_friend', false)
                ->orderBy('tanggal', 'desc')
                ->get();

            $friendReports = InputSiswa::with(['kategoris', 'siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user'])
                ->where('siswa_dilapor_kelas_id', $analisis->siswa_kelas_id)
                ->whereBetween('tanggal', [$from, $to])
                ->where('is_friend', true)
                ->orderBy('tanggal', 'desc')
                ->get();

            $guruNotes = InputGuru::with(['kategoris', 'siswaKelas.siswa.user'])
                ->where('siswa_kelas_id', $analisis->siswa_kelas_id)
                ->whereBetween('tanggal', [$from, $to])
                ->orderBy('tanggal', 'desc')
                ->get();
        }

        // Ambil data mood untuk ringkasan (gunakan rentang yang tercatat di entry)
        if ($from && $to) {
            $moods = PemantauanEmosiSiswa::query()
                ->where('siswa_kelas_id', $analisis->siswa_kelas_id)
                ->whereBetween('tanggal', [$from, $to])
                ->get(['skor']);
            if ($moods->isNotEmpty()) {
                $freq = $moods->groupBy('skor')->map(function($g){ return $g->count(); })->sortDesc();
                // Mapping skor->emoji: 1=😓 Awful, 2=😭 Overwhelmed, 3=😔 Bad, 4=😟 Stressed, 5=😐 Meh, 6=😴 Tired, 7=😊 Good, 8=😎 Chill, 9=😍 In Love, 10=🤩 Rad
                $map = [
                    1 => '😓',
                    2 => '😭',
                    3 => '😔',
                    4 => '😟',
                    5 => '😐',
                    6 => '😴',
                    7 => '😊',
                    8 => '😎',
                    9 => '😍',
                    10 => '🤩'
                ];
                $topEmojis = $freq->take(5)->map(function ($cnt, $skor) use ($map) {
                    $s = (int) $skor;
                    $emoji = $map[$s] ?? '�';
                    return ['skor' => $s, 'emoji' => $emoji, 'count' => (int) $cnt];
                })->values();
                // If not stored previously, compute avg mood now for display fallback
                $avgMood = $avgMood ?? round((float) $moods->avg('skor'), 2);
            }
        }

        // ===================== Interpretasi skor untuk tampilan guru (awam) =====================
        $sentimenScore = (float) ($analisis->skor_sentimen ?? 0.0); // asumsi sudah di [-1,1]
        $sentimenDesc = match (true) {
            $sentimenScore <= -0.80 => 'Sangat negatif: indikasi tekanan emosional berat atau keluhan serius; perlu perhatian segera.',
            $sentimenScore <= -0.60 => 'Negatif berat: banyak ekspresi stres/keluhan; monitor intensif disarankan.',
            $sentimenScore <= -0.35 => 'Negatif cukup kuat: muncul beberapa keluhan atau penurunan motivasi.',
            $sentimenScore <= -0.15 => 'Agak negatif: ada tanda masalah ringan atau kejadian tidak menyenangkan.',
            $sentimenScore < 0.15 => 'Netral: ekspresi campuran atau minim emosi kuat.',
            $sentimenScore < 0.35 => 'Agak positif: ada nuansa semangat atau sikap cukup baik.',
            $sentimenScore < 0.60 => 'Positif cukup kuat: menunjukkan motivasi dan emosi relatif sehat.',
            $sentimenScore < 0.80 => 'Sangat positif: konsisten menampilkan sikap optimis dan stabil.',
            default => 'Positif tinggi sekali: antusias / sangat konstruktif (cek konsistensi agar bukan sekadar euforia sementara).'
        };

        // Mood range diasumsikan 1–10 (1 sangat buruk, 10 sangat baik)
        $avgMoodVal = (float) ($avgMood ?? $analisis->avg_mood ?? 0.0);
        $moodDesc = match (true) {
            $avgMoodVal <= 0 => 'Tidak ada data mood pada rentang ini.',
            $avgMoodVal <= 2 => 'Sangat rendah / tertekan: perasaan negatif dominan.',
            $avgMoodVal <= 4 => 'Rendah: sering muncul rasa tidak nyaman / beban emosional.',
            $avgMoodVal <= 6 => 'Sedang: kondisi wajar atau sedikit lelah, masih dalam batas normal.',
            $avgMoodVal <= 8 => 'Baik: kestabilan emosi cukup terjaga.',
            $avgMoodVal <= 9 => 'Sangat baik: menunjukkan kesejahteraan emosional tinggi.',
            default => 'Sangat tinggi / euforia: perasaan sangat positif; tetap pantau agar stabil.'
        };

        // Penjelasan skala singkat
        $sentimenScaleInfo = 'Skor Sentimen: -1 (sangat negatif) sampai +1 (sangat positif). Nilai mendekati 0 berarti netral.';
        $moodScaleInfo = 'Skor Mood: 1 (sangat buruk) → 10 (sangat baik). Semakin tinggi biasanya semakin stabil dan positif.';

        $kategoris = \App\Models\KategoriMasalah::aktif()->orderBy('nama')->get(['id','nama','kode']);

        return view('roles.guru.analisis.show', [
            'analisis' => $analisis,
            'refleksisSelf' => $refleksisSelf,
            'friendReports' => $friendReports,
            'guruNotes' => $guruNotes,
            'isWali' => $isWali,
            'topEmojis' => $topEmojis,
            'avgMood' => $avgMood,
            'kategoris' => $kategoris,
            'sentimenDesc' => $sentimenDesc,
            'moodDesc' => $moodDesc,
            'sentimenScaleInfo' => $sentimenScaleInfo,
            'moodScaleInfo' => $moodScaleInfo,
        ]);
    }

    public function decide(Request $r, AnalisisEntry $analisis, int $rekomId)
    {
        $validated = $r->validate([
            'action' => ['required', Rule::in(['accept', 'reject'])],
            'kategori_id' => ['nullable', 'integer'],
            'selected_master_rekomendasi_id' => ['nullable', 'integer'],
        ]);

        /** @var AnalisisRekomendasi $rec */
        $rec = $analisis->rekomendasis()->findOrFail($rekomId);

        if ($validated['action'] === 'accept') {
            $rec->update([
                'status' => 'accepted',
                'decided_by' => Auth::id(),
                'decided_at' => now(),
                'rejected_kategori_id' => null,
                'selected_master_rekomendasi_id' => null,
            ]);
            // flag needs attention if severity high
            if (($rec->severity ?? 'low') === 'high') {
                $analisis->needs_attention = true;
                $analisis->save();
            }
            return back()->with('ok', 'Rekomendasi diterima.');
        }

        // action = reject → require kategori + selected alternative
        $kategoriId = (int) ($validated['kategori_id'] ?? 0);
        $altId = (int) ($validated['selected_master_rekomendasi_id'] ?? 0);
    $kategori = $kategoriId ? KategoriMasalah::aktif()->find($kategoriId) : null;
    $alt = $altId ? MasterRekomendasi::with('kategoris')->find($altId) : null;

        if (!$kategori || !$alt) {
            return back()->withErrors(['reject' => 'Kategori dan rekomendasi alternatif wajib dipilih.']);
        }

        // Validate selected alternative is linked to kategori via pivot
        if (!$alt->kategoris->pluck('id')->contains($kategori->id)) {
            return back()->withErrors(['reject' => 'Rekomendasi yang dipilih tidak sesuai dengan kategori yang dipilih.']);
        }

        $rec->update([
            'status' => 'rejected',
            'decided_by' => Auth::id(),
            'decided_at' => now(),
            'rejected_kategori_id' => $kategori->id,
            'selected_master_rekomendasi_id' => $alt->id,
        ]);
        // Send feedback to ML using top keywords
        try {
            $keywords = collect($analisis->kata_kunci ?? [])->pluck('term')->take(8)->values()->all();
            app(\App\Services\MlClient::class)->feedback($keywords, from: $rec->master?->kategoris?->first()?->nama, to: $kategori->nama);
        } catch (\Throwable $e) {
            // ignore ML feedback failure
        }
        return back()->with('ok', 'Penolakan disimpan beserta rekomendasi alternatif.');
    }

    // Return up to 5 alternative master recommendations for a given kategori
    public function alternatives(Request $r, AnalisisEntry $analisis, int $rekomId)
    {
        $r->validate(['kategori_id' => ['required', 'integer']]);
        $kategori = KategoriMasalah::aktif()->findOrFail((int) $r->kategori_id);
        $q = MasterRekomendasi::query()->where('is_active', true)
            ->whereHas('kategoris', function($qq) use ($kategori){ $qq->where('kategori_masalahs.id', $kategori->id); })
            ->limit(5)
            ->get(['id', 'kode', 'judul', 'deskripsi', 'severity']);
        return response()->json(['items' => $q]);
    }

    // Finalize an analysis: auto-reject any remaining suggested recommendations
    public function finalize(Request $r, AnalisisEntry $analisis)
    {
        $remaining = $analisis->rekomendasis()->where('status', 'suggested')->get();
        if ($remaining->isEmpty()) {
            return response()->json(['ok' => true]);
        }
        foreach ($remaining as $rec) {
            $rec->update([
                'status' => 'rejected',
                'decided_by' => Auth::id(),
                'decided_at' => now(),
            ]);
            // ML feedback: penalize original category slightly (no target)
            try {
                $fromCat = optional($rec->master?->kategoris?->first())->nama;
                if ($fromCat) {
                    $keywords = collect($analisis->kata_kunci ?? [])->pluck('term')->take(6)->values()->all();
                    app(\App\Services\MlClient::class)->feedback($keywords, from: $fromCat, to: null, delta: 0.15);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        // ensure needs_attention stays true if any accepted high exists
        $hasHigh = $analisis->rekomendasis()->where('status','accepted')->where('severity','high')->exists();
        if ($hasHigh && !$analisis->needs_attention) {
            $analisis->needs_attention = true;
            $analisis->save();
        }
        return response()->json(['ok' => true]);
    }

    // Toggle needs_attention flag manually by guru (BK or WK)
    public function attention(Request $r, AnalisisEntry $analisis)
    {
        $r->validate(['needs_attention' => ['required', 'boolean']]);

        // Authorization: BK can toggle for all; WK only for their class
        $guru = optional($r->user())->guru;
        if (!$guru) {
            abort(403);
        }
        if ($guru->jenis === 'wali_kelas') {
            $allow = optional($analisis->siswaKelas?->kelas)->wali_guru_id === $r->user()->id;
            abort_if(!$allow, 403);
        }

        $analisis->needs_attention = (bool) $r->boolean('needs_attention');
        $analisis->save();

        if ($r->wantsJson()) {
            return response()->json(['ok' => true, 'needs_attention' => $analisis->needs_attention]);
        }
        return back()->with('ok', 'Status perhatian diperbarui.');
    }

    // Detail rekomendasi untuk modal (judul tampil saja di list; isi diambil via AJAX)
    public function detail(Request $r, AnalisisEntry $analisis, int $rekomId)
    {
        // Authorization: ensure rekom belongs to this analisis
        /** @var AnalisisRekomendasi $rec */
        $rec = $analisis->rekomendasis()->with('master')->findOrFail($rekomId);

        $minScore = null;
        $rules = $rec->master?->rules;
        if (is_string($rules)) {
            $decoded = json_decode($rules, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $rules = $decoded;
            }
        }
        if (is_array($rules) && array_key_exists('min_neg_score', $rules)) {
            $minScore = (float) $rules['min_neg_score'];
        }

        return response()->json([
            'id' => $rec->id,
            'judul' => $rec->judul,
            'deskripsi' => $rec->deskripsi,
            'severity' => $rec->severity,
            'min_neg_score' => $minScore,
        ]);
    }
}
