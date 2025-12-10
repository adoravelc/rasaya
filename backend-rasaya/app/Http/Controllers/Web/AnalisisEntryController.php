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
use Illuminate\Support\Facades\Log;

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
        $q = SiswaKelas::with(['siswa.user', 'kelas'])
            ->where('is_active', true)
            ->whereNull('left_at')
            ->orderBy('id', 'desc');
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

        try {
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
        } catch (\RuntimeException $e) {
            // User-friendly error dari MlClient
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            // Unexpected error
            Log::error('Analisis error: ' . $e->getMessage(), [
                'siswa_kelas_id' => $data['siswa_kelas_id'],
                'from' => $data['from'],
                'to' => $data['to'],
            ]);
            
            return back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat memproses analisis. Silakan coba lagi atau hubungi Admin jika masalah berlanjut.');
        }
    }

    public function show(AnalisisEntry $analisis)
    {
        $analisis->load(['rekomendasis.master.kategoris', 'siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan', 'createdBy', 'reviewedBy']);

        // Auto-populate severity-bucket recommendations if none exist yet
        if ($analisis->rekomendasis()->count() === 0) {
            try {
                $actualScore = (float) ($analisis->skor_sentimen ?? 0.0);
                $abs = abs($actualScore);
                $derivedSeverity = $abs <= 0.20 ? 'low' : ($abs <= 0.50 ? 'medium' : 'high');
                $maxFallback = (int) config('rekomendasi.max_fallback', 5);

                    $kategoriIds = [];
                    $overview = $analisis->categories_overview ?? [];
                    if (is_array($overview) && count($overview) > 0) {
                        foreach ($overview as $item) {
                            $kid = $item['id'] ?? $item['kategori_masalah_id'] ?? null;
                            if (!$kid) {
                                // Try resolve by category name/kode from overview
                                $cname = $item['category'] ?? $item['name'] ?? null;
                                if ($cname) {
                                    $resolved = KategoriMasalah::aktif()
                                        ->where(function($q) use ($cname) {
                                            $q->where('nama', $cname)->orWhere('kode', $cname);
                                        })
                                        ->first(['id']);
                                    $kid = $resolved?->id;
                                }
                            }
                            if ($kid) $kategoriIds[] = (int) $kid;
                        }
                    }
                    // ONLY use categories analyzed by the system; if none, do not auto-populate
                    if (empty($kategoriIds)) {
                        throw new \RuntimeException('No analyzed categories available to suggest recommendations.');
                    }

                foreach ($kategoriIds as $kid) {
                    $masters = MasterRekomendasi::whereHas('kategoris', function($q) use ($kid) {
                            $q->where('kategori_masalah_id', $kid);
                        })
                        ->where('is_active', true)
                        ->where('severity', $derivedSeverity)
                        ->get();

                    foreach ($masters as $m) {
                        // Avoid duplicates if already exists
                        $exists = $analisis->rekomendasis()
                            ->where('master_rekomendasi_id', $m->id)
                            ->where('kategori_masalah_id', $kid)
                            ->exists();
                        if ($exists) { continue; }
                        AnalisisRekomendasi::create([
                            'analisis_entry_id' => $analisis->id,
                            'master_rekomendasi_id' => $m->id,
                            'kategori_masalah_id' => $kid,
                            'judul' => $m->judul,
                            'deskripsi' => $m->deskripsi,
                            'severity' => $m->severity ?? $derivedSeverity,
                            'match_score' => 0.75,
                            'status' => 'suggested',
                            'rules' => [
                                'mode' => 'severity-bucket:auto',
                                'derived_severity' => $derivedSeverity,
                                'actual_score' => $actualScore,
                                    'kategori_source' => 'overview',
                            ],
                        ]);
                    }
                }
                $analisis->refresh()->load('rekomendasis.master.kategoris');
            } catch (\Throwable $e) {
                // Non-blocking: continue rendering page even if auto-populate fails
                Log::warning('Auto-populate rekomendasi failed: ' . $e->getMessage());
            }
        }

        // 1. Ambil Overview & Sentimen
        $categoriesOverview = collect($analisis->categories_overview ?? []);
        $studentSentiment = abs((float) ($analisis->skor_sentimen ?? 0));

        // --- TAMBAHAN BARU: HITUNG POPULARITAS ---
        // Kita ambil ID semua master rekomendasi yang sedang ditampilkan saat ini
        $masterIds = $analisis->rekomendasis->pluck('master_rekomendasi_id')->unique();

        // Query ke database: "Coba hitung, ID ini sudah berapa kali di-ACCEPT di seluruh sistem?"
        // Hasilnya array: [ID_REKOMENDASI => JUMLAH_DIPILIH]
        $popularityScores = \Illuminate\Support\Facades\DB::table('analisis_rekomendasis')
            ->whereIn('master_rekomendasi_id', $masterIds)
            ->where('status', 'accepted') // Hanya hitung yang diterima
            ->select('master_rekomendasi_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('master_rekomendasi_id')
            ->pluck('total', 'master_rekomendasi_id');

        // 2. Sorting sesuai permintaan: kategori (ranking overview) lalu skor terbesar
        $overviewOrderedKategoriIds = [];
        if ($categoriesOverview->isNotEmpty()) {
            // categories_overview diurutkan secara natural dari atas ke bawah (sudah ranking)
            foreach ($categoriesOverview as $item) {
                $kid = $item['id'] ?? $item['kategori_masalah_id'] ?? null;
                if (!$kid) {
                    $cname = $item['category'] ?? $item['name'] ?? null;
                    if ($cname) {
                        $resolved = KategoriMasalah::aktif()
                            ->where(function($q) use ($cname) {
                                $q->where('nama', $cname)->orWhere('kode', $cname);
                            })
                            ->first(['id']);
                        $kid = $resolved?->id;
                    }
                }
                if ($kid) $overviewOrderedKategoriIds[] = (int) $kid;
            }
        }

        // Fallback jika tidak ada mapping: pertahankan urutan asli
        $sortedRekomendasis = $analisis->rekomendasis->sort(function ($a, $b) use ($overviewOrderedKategoriIds) {
            // Tentukan index kategori berdasarkan urutan overview
            $aIdx = array_search((int)($a->kategori_masalah_id ?? 0), $overviewOrderedKategoriIds, true);
            $bIdx = array_search((int)($b->kategori_masalah_id ?? 0), $overviewOrderedKategoriIds, true);
            // Jika kategori tidak ditemukan dalam overview, taruh di belakang
            $aIdx = ($aIdx === false) ? PHP_INT_MAX : $aIdx;
            $bIdx = ($bIdx === false) ? PHP_INT_MAX : $bIdx;

            if ($aIdx !== $bIdx) return $aIdx <=> $bIdx; // kategori lebih atas dulu

            // Dalam kategori yang sama: urutkan skor terbesar ke kecil
            $aScore = (float) ($a->match_score ?? 0.0);
            $bScore = (float) ($b->match_score ?? 0.0);
            return $bScore <=> $aScore; // desc
        })->values();

        $analisis->setRelation('rekomendasis', $sortedRekomendasis);

        // ... (SISA KODE KE BAWAH TETAP SAMA SEPERTI ASLINYA) ...

        $isWali = optional(Auth::user()->guru)->jenis === 'wali_kelas';

        // Kumpulkan semua input yang termasuk dalam rentang analisis ini
        $from = optional($analisis->tanggal_awal_proses)?->toDateString();
        $to = optional($analisis->tanggal_akhir_proses)?->toDateString();

        $refleksisSelf = collect();
        $friendReports = collect();
        $guruNotes = collect();

        $used = collect($analisis->used_items ?? []);
        $topEmojis = collect();
        $avgMood = $analisis->avg_mood;

        if ($used->isNotEmpty()) {
            $selfIds = $used->where('type', 'ref_self')->pluck('id')->all();
            $friendIds = $used->where('type', 'ref_friend')->pluck('id')->all();
            $guruIds = $used->where('type', 'guru')->pluck('id')->all();

            if (!empty($selfIds)) {
                $refleksisSelf = InputSiswa::with(['siswaKelas.siswa.user'])
                    ->whereIn('id', $selfIds)
                    ->where('is_friend', false)
                    ->orderBy('tanggal', 'desc')->get();
            }
            if (!empty($friendIds)) {
                $friendReports = InputSiswa::with(['siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user'])
                    ->whereIn('id', $friendIds)
                    ->where('is_friend', true)
                    ->orderBy('tanggal', 'desc')->get();
            }
            if (!empty($guruIds)) {
                $guruNotes = InputGuru::with(['siswaKelas.siswa.user'])
                    ->whereIn('id', $guruIds)
                    ->orderBy('tanggal', 'desc')->get();
            }
        } elseif ($from && $to) {
            $refleksisSelf = InputSiswa::with(['siswaKelas.siswa.user'])
                ->where('siswa_kelas_id', $analisis->siswa_kelas_id)
                ->whereBetween('tanggal', [$from, $to])
                ->where('is_friend', false)->orderBy('tanggal', 'desc')->get();

            $friendReports = InputSiswa::with(['siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user'])
                ->where('siswa_dilapor_kelas_id', $analisis->siswa_kelas_id)
                ->whereBetween('tanggal', [$from, $to])
                ->where('is_friend', true)->orderBy('tanggal', 'desc')->get();

            $guruNotes = InputGuru::with(['siswaKelas.siswa.user'])
                ->where('siswa_kelas_id', $analisis->siswa_kelas_id)
                ->whereBetween('tanggal', [$from, $to])->orderBy('tanggal', 'desc')->get();
        }

        if ($from && $to) {
            $moods = PemantauanEmosiSiswa::query()
                ->where('siswa_kelas_id', $analisis->siswa_kelas_id)
                ->whereBetween('tanggal', [$from, $to])
                ->get(['skor']);
            if ($moods->isNotEmpty()) {
                $freq = $moods->groupBy('skor')->map(function ($g) {
                    return $g->count();
                })->sortDesc();
                $map = [1 => '😓', 2 => '😭', 3 => '😔', 4 => '😟', 5 => '😐', 6 => '😴', 7 => '😊', 8 => '😎', 9 => '😍', 10 => '🤩'];
                $topEmojis = $freq->take(5)->map(function ($cnt, $skor) use ($map) {
                    $s = (int) $skor;
                    return ['skor' => $s, 'emoji' => $map[$s] ?? '', 'count' => (int) $cnt];
                })->values();
                $avgMood = $avgMood ?? round((float) $moods->avg('skor'), 2);
            }
        }

        $sentimenScore = (float) ($analisis->skor_sentimen ?? 0.0);
        $sentimenDesc = match (true) {
            $sentimenScore <= -0.80 => 'Sangat negatif: indikasi tekanan emosional berat atau keluhan serius.',
            $sentimenScore <= -0.60 => 'Negatif berat: banyak ekspresi stres/keluhan.',
            $sentimenScore <= -0.35 => 'Negatif cukup kuat: muncul beberapa keluhan.',
            $sentimenScore <= -0.15 => 'Agak negatif: ada tanda masalah ringan.',
            $sentimenScore < 0.15 => 'Netral: ekspresi campuran.',
            $sentimenScore < 0.35 => 'Agak positif.',
            $sentimenScore < 0.60 => 'Positif cukup kuat.',
            $sentimenScore < 0.80 => 'Sangat positif.',
            default => 'Positif tinggi sekali.'
        };

        $avgMoodVal = (float) ($avgMood ?? $analisis->avg_mood ?? 0.0);
        $moodDesc = match (true) {
            $avgMoodVal <= 0 => 'Tidak ada data mood.',
            $avgMoodVal <= 2 => 'Sangat rendah / tertekan.',
            $avgMoodVal <= 4 => 'Rendah: tidak nyaman.',
            $avgMoodVal <= 6 => 'Sedang: wajar.',
            $avgMoodVal <= 8 => 'Baik.',
            $avgMoodVal <= 9 => 'Sangat baik.',
            default => 'Sangat tinggi.'
        };

        $sentimenScaleInfo = 'Skor Sentimen: -1 (negatif) ... +1 (positif).';
        $moodScaleInfo = 'Skor Mood: 1 (buruk) ... 10 (baik).';
        $kategoris = \App\Models\KategoriMasalah::aktif()->orderBy('nama')->get(['id', 'nama', 'kode']);

        $mlWarnings = [];
        $co = collect($analisis->categories_overview ?? []);
        if ($co->isEmpty())
            $mlWarnings[] = 'Kategori otomatis belum tersedia.';

        // Kategori options untuk form revisi
        $kategoriOptions = \App\Models\KategoriMasalah::where('is_active', true)
            ->orderBy('nama')
            ->get();

        return view('roles.guru.analisis.show', [
            'analisis' => $analisis,
            'refleksisSelf' => $refleksisSelf,
            'friendReports' => $friendReports,
            'guruNotes' => $guruNotes,
            'isWali' => $isWali,
            'topEmojis' => $topEmojis,
            'avgMood' => $avgMood,
            'kategoris' => $kategoris,
            'kategoriOptions' => $kategoriOptions,
            'sentimenDesc' => $sentimenDesc,
            'moodDesc' => $moodDesc,
            'sentimenScaleInfo' => $sentimenScaleInfo,
            'moodScaleInfo' => $moodScaleInfo,
            'mlWarnings' => $mlWarnings,
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
            
            // POSITIVE FEEDBACK: Reinforce correct categorization
            try {
                // Extract CLEAN keywords - split phrases into individual words
                $rawKeywords = collect($analisis->kata_kunci ?? [])->pluck('term')->take(10);
                $keywords = collect([]);
                foreach ($rawKeywords as $phrase) {
                    $words = collect(explode(' ', strtolower($phrase)))
                        ->filter(fn($w) => strlen($w) >= 3)
                        ->values();
                    $keywords = $keywords->merge($words);
                }
                $keywords = $keywords->unique()->take(15)->values()->all();
                
                $kategori = $rec->master?->kategoris?->first()?->nama ?? null;
                
                if (!empty($keywords) && $kategori) {
                    // Accept = feedback positif (reinforce)
                    app(\App\Services\MlClient::class)->feedback(
                        keywords: $keywords,
                        from: null,  // No correction needed
                        to: $kategori,  // Reinforce this category
                        delta: 0.15  // Smaller boost untuk acceptance
                    );
                    
                    Log::info('ML Feedback Sent', [
                        'analisis_id' => $analisis->id,
                        'rekomendasi_id' => $rec->id,
                        'kategori' => $kategori,
                        'keywords' => $keywords,
                        'action' => 'accept'
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('ML Feedback Failed', ['error' => $e->getMessage()]);
            }
            
            // flag needs attention if severity high
            if (($rec->severity ?? 'low') === 'high') {
                $analisis->needs_attention = true;
                $analisis->save();
            }
            
            return back()->with('ok', 'Rekomendasi diterima. Sistem ML terus belajar dari keputusan Anda.');
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
        
        // IMPROVED: Send feedback to ML dengan tracking lebih detail
        try {
            // Extract CLEAN keywords - split phrases into individual words
            $rawKeywords = collect($analisis->kata_kunci ?? [])->pluck('term')->take(10);
            $keywords = collect([]);
            foreach ($rawKeywords as $phrase) {
                $words = collect(explode(' ', strtolower($phrase)))
                    ->filter(fn($w) => strlen($w) >= 3)
                    ->values();
                $keywords = $keywords->merge($words);
            }
            $keywords = $keywords->unique()->take(15)->values()->all();
            
            // Get kategori from & to
            $fromKategori = $rec->master?->kategoris?->first()?->nama ?? null;
            $toKategori = $kategori->nama;
            
            // Kirim feedback ke ML dengan delta lebih besar untuk rejection (lebih kuat impact)
            if (!empty($keywords) && $fromKategori && $toKategori) {
                app(\App\Services\MlClient::class)->feedback(
                    keywords: $keywords,
                    from: $fromKategori,
                    to: $toKategori,
                    delta: 0.3  // Rejection = feedback kuat
                );
                
                // Log feedback untuk tracking
                Log::info('ML Feedback Sent', [
                    'analisis_id' => $analisis->id,
                    'rekomendasi_id' => $rec->id,
                    'from' => $fromKategori,
                    'to' => $toKategori,
                    'keywords' => $keywords,
                    'action' => 'reject'
                ]);
            }
        } catch (\Throwable $e) {
            // ignore ML feedback failure but log it
            Log::warning('ML Feedback Failed', ['error' => $e->getMessage()]);
        }
        
        return back()->with('ok', 'Penolakan disimpan beserta rekomendasi alternatif. Sistem ML telah mempelajari koreksi Anda.');
    }

    // Return up to 5 alternative master recommendations for a given kategori
    public function alternatives(Request $r, AnalisisEntry $analisis, int $rekomId)
    {
        $r->validate(['kategori_id' => ['required', 'integer']]);
        $kategori = KategoriMasalah::aktif()->findOrFail((int) $r->kategori_id);
        $q = MasterRekomendasi::query()->where('is_active', true)
            ->whereHas('kategoris', function ($qq) use ($kategori) {
                $qq->where('kategori_masalahs.id', $kategori->id);
            })
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
                    // Extract CLEAN keywords - split phrases into individual words
                    $rawKeywords = collect($analisis->kata_kunci ?? [])->pluck('term')->take(6);
                    $keywords = collect([]);
                    foreach ($rawKeywords as $phrase) {
                        $words = collect(explode(' ', strtolower($phrase)))
                            ->filter(fn($w) => strlen($w) >= 3)
                            ->values();
                        $keywords = $keywords->merge($words);
                    }
                    $keywords = $keywords->unique()->take(10)->values()->all();
                    app(\App\Services\MlClient::class)->feedback($keywords, from: $fromCat, to: null, delta: 0.15);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        // ensure needs_attention stays true if any accepted high exists
        $hasHigh = $analisis->rekomendasis()->where('status', 'accepted')->where('severity', 'high')->exists();
        if ($hasHigh && !$analisis->needs_attention) {
            $analisis->needs_attention = true;
            $analisis->save();
        }
        return response()->json(['ok' => true]);
    }

    // Accept analysis review (set review_status → accepted)
    public function acceptReview(Request $r, AnalisisEntry $analisis)
    {
        // Idempotent: jika sudah accepted, kembalikan status sekarang
        if ($analisis->review_status === 'accepted') {
            return response()->json([
                'review_status' => $analisis->review_status,
                'reviewed_by' => $analisis->reviewed_by,
                'reviewed_at' => $analisis->reviewed_at,
            ]);
        }

        $analisis->update([
            'review_status' => 'accepted',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Analisis ditandai sebagai accepted.',
            'review_status' => $analisis->review_status,
            'reviewed_by' => $analisis->reviewed_by,
            'reviewed_at' => $analisis->reviewed_at,
        ]);
    }

    // Mark analysis as under revision (review_status → revised)
    public function markRevised(Request $r, AnalisisEntry $analisis)
    {
        if ($analisis->review_status === 'revised') {
            return response()->json([
                'review_status' => $analisis->review_status,
                'reviewed_by' => $analisis->reviewed_by,
                'reviewed_at' => $analisis->reviewed_at,
            ]);
        }

        $analisis->update([
            'review_status' => 'revised',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Analisis ditandai untuk revisi.',
            'review_status' => $analisis->review_status,
            'reviewed_by' => $analisis->reviewed_by,
            'reviewed_at' => $analisis->reviewed_at,
        ]);
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

    public function handlingStatus(Request $r, AnalisisEntry $analisis)
    {
        $r->validate(['handling_status' => ['required', 'in:handled,resolved']]);

        // Authorization: BK can toggle for all; WK only for their class
        $guru = optional($r->user())->guru;
        if (!$guru) {
            abort(403);
        }
        if ($guru->jenis === 'wali_kelas') {
            $allow = optional($analisis->siswaKelas?->kelas)->wali_guru_id === $r->user()->id;
            abort_if(!$allow, 403);
        }

        $status = $r->input('handling_status');
        $analisis->handling_status = $status;

        // Jika status 'resolved', otomatis set needs_attention jadi false
        if ($status === 'resolved') {
            $analisis->needs_attention = false;
        }

        $analisis->save();

        if ($r->wantsJson()) {
            return response()->json(['ok' => true, 'handling_status' => $analisis->handling_status]);
        }
        return back()->with('ok', 'Status penanganan diperbarui.');
    }

    /**
     * Guru dapat merevisi kategori keseluruhan analisis jika sistem ML salah mengklasifikasikan
     * Ini akan memberikan feedback yang lebih kuat ke ML untuk kasus serupa di masa depan
     */
    public function reviseCategory(Request $r, AnalisisEntry $analisis)
    {
        $validated = $r->validate([
            'new_kategori_id' => ['required', 'integer', 'exists:kategori_masalahs,id'],
            'revision_reason' => ['required', 'string', 'max:1000'],
        ]);

        // Authorization
        $guru = optional($r->user())->guru;
        if (!$guru) {
            abort(403);
        }
        if ($guru->jenis === 'wali_kelas') {
            $allow = optional($analisis->siswaKelas?->kelas)->wali_guru_id === $r->user()->id;
            abort_if(!$allow, 403);
        }

        $newKategori = KategoriMasalah::aktif()->findOrFail($validated['new_kategori_id']);
        
        // Track old categories untuk feedback
        $oldCategories = collect($analisis->categories_overview ?? [])
            ->pluck('category')
            ->toArray();

        // Update analisis metadata
        $analisis->update([
            'revised_kategori_id' => $newKategori->id,
            'revision_reason' => $validated['revision_reason'] ?? null,
            'revised_by' => $r->user()->id,
            'revised_at' => now(),
        ]);

        // STRONG FEEDBACK to ML: Category revision = sistem salah total
        try {
            // Extract keywords from ML analysis (keep as-is, don't split)
            $rawKeywords = collect($analisis->kata_kunci ?? [])->pluck('term')->take(15);
            $keywords = $rawKeywords->map(fn($k) => strtolower(trim($k)))->filter();
            
            // ADD extra keywords dari form - JANGAN PISAH PER KATA, simpan utuh sebagai phrase
            $extraWords = collect();
            if (!empty($validated['revision_reason'])) {
                // Split by semicolon, comma, or newline (these are keyword separators)
                // But DO NOT split by space - allow multi-word keywords like "tidak tertarik", "sering bolos"
                $extraWords = collect(preg_split('/[;\n,]+/', $validated['revision_reason']))
                    ->map(fn($w) => strtolower(trim($w)))
                    ->filter(fn($w) => strlen($w) >= 3) // Min 3 chars for entire phrase
                    ->values();
                $keywords = $keywords->merge($extraWords);
            }
            
            $keywords = $keywords->unique()->take(25)->values()->all();
            
            if (!empty($keywords)) {
                // Penalize semua kategori lama (jika ada)
                foreach ($oldCategories as $oldCat) {
                    if ($oldCat && $oldCat !== $newKategori->nama) {
                        app(\App\Services\MlClient::class)->feedback(
                            keywords: $keywords,
                            from: $oldCat,
                            to: null,
                            delta: 0.4  // Strong penalty
                        );
                    }
                }
                
                // Pisahkan kata kunci yang sudah ada vs baru
                $existingKeywords = collect($newKategori->kata_kunci ?? [])
                    ->map(fn($w) => strtolower(trim((string)$w)))
                    ->filter();
                
                $existingWords = collect();
                $newWords = collect();
                
                foreach ($extraWords as $word) {
                    if ($existingKeywords->contains($word)) {
                        $existingWords->push($word);
                    } else {
                        $newWords->push($word);
                    }
                }
                
                // Reward kata kunci yang SUDAH ADA dengan boost lebih besar (reinforcement)
                if ($existingWords->isNotEmpty()) {
                    app(\App\Services\MlClient::class)->feedback(
                        keywords: $existingWords->all(),
                        from: null,
                        to: $newKategori->nama,
                        delta: 0.7  // Stronger boost for existing keywords (reinforcement)
                    );
                }
                
                // Reward kata kunci BARU dengan boost normal
                if ($newWords->isNotEmpty()) {
                    app(\App\Services\MlClient::class)->feedback(
                        keywords: $newWords->all(),
                        from: null,
                        to: $newKategori->nama,
                        delta: 0.5  // Normal boost for new keywords
                    );
                }
                
                // Reward kata kunci ML-generated (bukan dari form) dengan boost normal
                $mlOnlyKeywords = collect($keywords)->diff($extraWords)->values();
                if ($mlOnlyKeywords->isNotEmpty()) {
                    app(\App\Services\MlClient::class)->feedback(
                        keywords: $mlOnlyKeywords->all(),
                        from: null,
                        to: $newKategori->nama,
                        delta: 0.5  // Normal boost
                    );
                }
                
                // Persist HANYA kata kunci BARU ke kategori_masalahs (hindari duplikat)
                try {
                    if ($newWords->isNotEmpty()) {
                        $merged = $existingKeywords->merge($newWords)
                            ->filter(fn($w) => strlen($w) >= 3)
                            ->unique()
                            ->take(400)
                            ->values();
                        $newKategori->kata_kunci = $merged->all();
                        $newKategori->save();
                    }
                } catch (\Throwable $ex) {
                    // Ignore DB update error for keywords persistence
                }
                
                Log::info('ML Category Revision Feedback', [
                    'analisis_id' => $analisis->id,
                    'from_categories' => $oldCategories,
                    'to_category' => $newKategori->nama,
                    'keywords' => $keywords,
                    'reason' => $validated['revision_reason'] ?? null
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('ML Revision Feedback Failed', ['error' => $e->getMessage()]);
        }

        return back()->with('ok', 'Revisi kategori berhasil disimpan. ML akan belajar dari koreksi ini untuk meningkatkan akurasi di masa depan.');
    }

    // Detail rekomendasi untuk modal (judul tampil saja di list; isi diambil via AJAX)
    public function detail(Request $r, AnalisisEntry $analisis, int $rekomId)
    {
        // Authorization: ensure rekom belongs to this analisis
        /** @var AnalisisRekomendasi $rec */
        $rec = $analisis->rekomendasis()->with('master.kategoris')->findOrFail($rekomId);

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

        // Ambil nama kategori dari master rekomendasi
        $kategoris = $rec->master ? $rec->master->kategoris->pluck('nama')->toArray() : [];
        $kategoriText = !empty($kategoris) ? implode(', ', $kategoris) : 'Umum';

        return response()->json([
            'id' => $rec->id,
            'judul' => $rec->judul,
            'deskripsi' => $rec->deskripsi,
            'severity' => $rec->severity,
            'min_neg_score' => $minScore,
            'kategori' => $kategoriText,
            'kategori_list' => $kategoris,
        ]);
    }
}
