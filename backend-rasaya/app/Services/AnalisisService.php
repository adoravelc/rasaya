<?php
namespace App\Services;

use App\Models\AnalisisEntry;
use App\Models\AnalisisRekomendasi;
use App\Models\InputSiswa;
use App\Models\InputGuru;
use App\Models\MasterRekomendasi;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AnalisisService
{
    public function __construct(private MlClient $ml)
    {
    }

    /**
     * Jalankan analisis untuk satu siswa_kelas pada rentang tanggal (Y-m-d).
     * Kembalikan AnalisisEntry yang tersimpan, beserta rekomendasi suggested.
     */
    public function analisisRentang(int $siswaKelasId, string $from, string $to, int $createdByUserId, bool $includeAllGuruNotes = false): AnalisisEntry
    {
        // Prevent early timeout during first ML warmup (e.g., IndoBERT download)
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
        // 1) ambil teks dari refleksi & input guru (sesuaikan field model-mu)
        // Refleksi diri siswa tsb
        $refleksisSelf = InputSiswa::query()
            ->where('siswa_kelas_id', $siswaKelasId)
            ->where('is_friend', false)
            ->whereBetween('tanggal', [$from, $to])
            ->get(['id', 'teks', 'tanggal']);
        // Laporan teman tentang siswa tsb
        $refleksisFriend = InputSiswa::query()
            ->where('siswa_dilapor_kelas_id', $siswaKelasId)
            ->where('is_friend', true)
            ->whereBetween('tanggal', [$from, $to])
            ->get(['id', 'teks', 'tanggal']);

        // Observasi guru untuk siswa tsb
        $guruQ = InputGuru::query()
            ->where('siswa_kelas_id', $siswaKelasId)
            ->whereBetween('tanggal', [$from, $to]);
        if (!$includeAllGuruNotes) {
            $guruQ->where('guru_id', $createdByUserId);
        }
        $gurus = $guruQ->get(['id', 'teks', 'tanggal']);

        $payload = [];
        $usedItems = [];
        foreach ($refleksisSelf as $r) {
            $payload[] = ['id' => 'ref-self-' . $r->id, 'text' => $r->teks];
            $usedItems[] = ['type' => 'ref_self', 'id' => (int) $r->id];
        }
        foreach ($refleksisFriend as $r) {
            $payload[] = ['id' => 'ref-friend-' . $r->id, 'text' => $r->teks];
            $usedItems[] = ['type' => 'ref_friend', 'id' => (int) $r->id];
        }
        foreach ($gurus as $g) {
            $payload[] = ['id' => 'guru-' . $g->id, 'text' => $g->teks];
            $usedItems[] = ['type' => 'guru', 'id' => (int) $g->id];
        }

        // handle kosong
        if (empty($payload)) {
            // buat entry kosong supaya ada jejak permintaan
            // hitung avg mood (emoji) dalam rentang
            $avgMood = \App\Models\PemantauanEmosiSiswa::query()
                ->where('siswa_kelas_id', $siswaKelasId)
                ->whereBetween('tanggal', [$from, $to])
                ->avg('skor');

            return AnalisisEntry::create([
                'siswa_kelas_id' => $siswaKelasId,
                'created_by' => $createdByUserId,
                'skor_sentimen' => 0,
                'avg_mood' => $avgMood ? round((float) $avgMood, 2) : null,
                'kata_kunci' => [],
                'used_items' => [],
                'source' => 'gabungan',
                'source_id' => null,
                'tanggal_awal_proses' => now(),
                'tanggal_akhir_proses' => now(),
            ]);
        }

        // 2) panggil ML API
        $res = $this->ml->analyze($payload);
        $mlResponse = $res; // Store for later use in rekomendasi generation

        // Ambil daftar item untuk perhitungan rata-rata sentimen.
        // Versi ML terbaru mengembalikan "items" dengan struktur
        //   [ 'sentiment' => ['barasa'=>..,'english'=>..,'aggregate'=>..,'label'=>..], ... ]
        // Sedangkan versi lama mengembalikan "per_entry" dengan field numerik langsung.
        $per = collect($res['items'] ?? $res['per_entry'] ?? []);
        $avg = $per->avg(function ($x) {
            $s = $x['sentiment'] ?? 0.0;
            if (is_array($s)) {
                return (float) ($s['aggregate'] ?? 0.0);
            }
            return (float) $s;
        });

        // gunakan keyphrases dari ML (konversi ke format {term,count}) + normalisasi & dedup
        $dialectMap = [
            'pung' => 'punya',
            'puny' => 'punya',
            'beta' => 'saya',
            'sy' => 'saya',
            'b' => 'saya',
            'deng' => 'dengan',
            'dng' => 'dengan',
            'sm' => 'sama',
            'ko' => 'kamu',
            'kau' => 'kamu',
        ];
        $stopSingles = ['dan','atau','yang','di','ke','dengan','untuk','pada','saya','aku'];
        $kps = collect($res['keyphrases'] ?? []);
        $tmp = [];
        foreach ($kps as $it) {
            $term = strtolower(trim((string)($it['term'] ?? '')));
            if ($term === '') continue;
            // normalisasi kata per token
            $tokens = collect(preg_split('/\s+/u', $term))->map(function($tk) use ($dialectMap){
                $base = strtolower($tk);
                return $dialectMap[$base] ?? $base;
            });
            if ($tokens->count() === 1 && in_array($tokens[0], $stopSingles, true)) {
                continue; // skip stop kata tunggal umum
            }
            $norm = trim($tokens->join(' '));
            $w = (float)($it['weight'] ?? 0);
            $tmp[$norm] = ($tmp[$norm] ?? 0) + $w;
        }
        // bangun array final
        $keywords = collect($tmp)->sortDesc()->map(function($w, $term){
            $count = max(1, (int) round($w * 10));
            return ['term' => (string)$term, 'count' => $count];
        })->values()->take(30)->all();

        // gabungan teks untuk rule master rekomendasi
        $joinedText = mb_strtolower(collect($payload)->pluck('text')->join(' '));

        // simpan ke DB + generate rekomendasi
        $summary = $res['summary'] ?? null;
        $clusters = $res['clusters'] ?? null;
        $categoriesOverview = $res['categories_overview'] ?? null; // legacy (AKADEMIK/EMOSI/...)

        // ===================== Rebuild category overview using latest taxonomy-style clusters =====================
        // ML items may contain enriched cluster metadata: topic_name, label (subtopic), bucket, subtopic_code
        $itemsRaw = $res['items'] ?? [];
        $topicWeights = [];
        $subtopicWeights = [];
        foreach ($itemsRaw as $it) {
            if (!is_array($it)) continue;
            $cluster = $it['cluster'] ?? null;
            if (!is_array($cluster)) continue;
            $topic = trim((string)($cluster['topic_name'] ?? $cluster['bucket'] ?? ''));
            $sub = trim((string)($cluster['label'] ?? ''));
            if ($topic === '' && $sub === '') continue;
            $neg = (bool)($it['negative_flag'] ?? false);
            $sev = (float)($it['severity'] ?? 0.0);
            // weight: base 1 + severity bonus for negative items
            $w = 1.0 + ($neg ? (0.5 * $sev) : 0.0);
            if ($topic !== '') {
                $topicWeights[$topic] = ($topicWeights[$topic] ?? 0) + $w;
            }
            if ($sub !== '') {
                $subtopicWeights[$sub] = ($subtopicWeights[$sub] ?? 0) + $w;
            }
        }
        // Prefer topic-level overview; fallback to legacy if taxonomy empty
        if (!empty($topicWeights)) {
            arsort($topicWeights);
            $categoriesOverview = [];
            foreach ($topicWeights as $cat => $score) {
                $categoriesOverview[] = [
                    'category' => $cat,
                    'score' => round($score, 4),
                ];
            }
        }
        // Map legacy bucket codes to taxonomy-friendly names for display (apply always, safe no-op for real topic names)
        if (is_array($categoriesOverview)) {
            $bucketMap = [
                'EMOSI' => 'Kesehatan Mental & Emosi',
                'SOSIAL' => 'Sosial & Pergaulan', // default for bucket; some items may specifically be "Keluarga & Pola Asuh"
                'AKADEMIK' => 'Akademis & Disiplin',
                'FISIK' => 'Kesehatan Fisik & Gaya Hidup',
                'RELASI' => 'Relasi & Percintaan',
                'KARIER' => 'Karier & Masa Depan',
                'DISIPLIN' => 'Disiplin & Tata Tertib',
                'DIGITAL' => 'Digital Wellbeing',
                'KEAMANAN' => 'Keamanan & Keselamatan',
            ];
            $categoriesOverview = array_map(function($row) use ($bucketMap){
                $cat = (string)($row['category'] ?? '');
                $upper = strtoupper($cat);
                if (isset($bucketMap[$upper])) {
                    $row['category'] = $bucketMap[$upper];
                }
                return $row;
            }, $categoriesOverview);
        }

        // Normalize scores to [0..1] so the Blade can show percentages consistently
        if (is_array($categoriesOverview) && !empty($categoriesOverview)) {
            $sum = 0.0;
            foreach ($categoriesOverview as $r) { $sum += (float)($r['score'] ?? 0); }
            if ($sum > 0) {
                $categoriesOverview = array_map(function($r) use ($sum){
                    $r['score'] = round(((float)($r['score'] ?? 0)) / $sum, 4);
                    return $r;
                }, $categoriesOverview);
            }
        }

        // Rule-based, layperson-friendly paragraph summary
        $autoSummary = null;
        try {
            $avgSent = is_array($summary) ? (float)($summary['avg_sentiment'] ?? 0) : (float) $avg;
            $negRatio = is_array($summary) ? (float)($summary['negative_ratio'] ?? 0) : 0.0;

            // map category labels to friendly Indonesian phrases
            $labelMap = [ // map legacy bucket codes to taxonomy topic names; taxonomy topic names are used as-is
                'EMOSI' => 'Kesehatan Mental & Emosi',
                'SOSIAL' => 'Sosial & Pergaulan',
                'AKADEMIK' => 'Akademis & Disiplin',
                'FISIK' => 'Kesehatan Fisik & Gaya Hidup',
                'RELASI' => 'Relasi & Percintaan',
                'KARIER' => 'Karier & Masa Depan',
                'DISIPLIN' => 'Disiplin & Tata Tertib',
                'DIGITAL' => 'Digital Wellbeing',
                'KEAMANAN' => 'Keamanan & Keselamatan',
            ];
            $topCatsArr = collect($categoriesOverview ?? [])->sortByDesc(fn($c)=> (float)($c['score'] ?? 0))
                ->take(2)
                ->pluck('category')
                ->filter()
                ->map(function($c) use ($labelMap){
                    $upper = strtoupper((string)$c);
                    return $labelMap[$upper] ?? (string)$c;
                })
                ->values()
                ->all();
            $catsTxt = empty($topCatsArr) ? null : implode(' dan ', $topCatsArr);

            // pick 2-3 representative keywords after normalization above
            $kwList = collect($keywords ?? [])->pluck('term')->take(3)->filter()->values()->all();
            $kwTxt = empty($kwList) ? null : implode(', ', $kwList);

            // severity signal from items
            $items = is_array($res) ? ($res['items'] ?? []) : [];
            $severeCount = 0;
            foreach ($items as $it) {
                $neg = (bool)($it['negative_flag'] ?? false);
                $sev = (float)($it['severity'] ?? 0);
                if ($neg && $sev >= 0.7) { $severeCount++; }
            }

            // sentiment phrasing
            $sentTxt = 'netral';
            if ($avgSent <= -0.60) $sentTxt = 'negatif kuat';
            elseif ($avgSent <= -0.35) $sentTxt = 'cukup negatif';
            elseif ($avgSent <= -0.15) $sentTxt = 'sedikit negatif';
            elseif ($avgSent >= 0.35) $sentTxt = 'cukup positif';
            elseif ($avgSent >= 0.60) $sentTxt = 'positif kuat';

            $negPct = number_format($negRatio * 100, 1);
            $avgSentStr = number_format($avgSent, 2);

            // Jika sama sekali tidak ada konten negatif & sentimen >= sedikit positif, buat fallback positif
            if ($negRatio == 0.0 && $severeCount === 0 && $avgSent >= 0.05) {
                $autoSummary = "Input siswa rata-rata bernilai positif atau netral tanpa indikasi masalah dari refleksi diri, laporan teman maupun observasi guru pada rentang ini. Lanjutkan pemantauan rutin.";
            }

            // Build paragraph normal jika belum di-set oleh fallback
            $parts = [];
            if ($avgSent <= -0.15) {
                $parts[] = "Secara umum, curhatan siswa bernada $sentTxt (skor $avgSentStr) dengan $negPct% konten bernuansa negatif.";
            } elseif ($avgSent < 0.15) {
                $parts[] = "Secara umum, ekspresi siswa cenderung netral (skor $avgSentStr) dengan $negPct% konten bernuansa negatif.";
            } else {
                $parts[] = "Secara umum, ekspresi siswa cenderung positif (skor $avgSentStr); porsi konten bernuansa negatif $negPct%.";
            }

            if ($catsTxt) {
                $parts[] = "Topik yang paling sering muncul adalah $catsTxt.";
            }
            if ($kwTxt) {
                $parts[] = "Kata kunci yang menonjol: $kwTxt.";
            }
            if ($severeCount > 0) {
                $parts[] = "Terdapat beberapa keluhan yang tergolong berat dan perlu perhatian lanjutan.";
            }
            $parts[] = "Silakan merujuk rekomendasi di bawah sebagai tindak lanjut awal.";

            // Jangan timpa fallback positif jika sudah di-set
            if ($autoSummary === null || $autoSummary === '') {
                $autoSummary = implode(' ', $parts);
            }
        } catch (\Throwable $e) {
            // fallback to prior short format
            if (is_array($summary)) {
                $negRatio = (float)($summary['negative_ratio'] ?? 0);
                $avgSent = (float)($summary['avg_sentiment'] ?? 0);
                $topCats = collect($categoriesOverview ?? [])->take(3)->map(fn($c)=>$c['category'])->filter()->join(', ');
                $sentimenTrend = $avgSent < -0.35 ? 'cukup negatif' : ($avgSent < -0.15 ? 'sedikit negatif' : ($avgSent < 0.15 ? 'netral' : 'positif'));
                $autoSummary = "Rangkuman otomatis: Sentimen rata-rata $sentimenTrend (".number_format($avgSent,2)."), proporsi teks negatif ".number_format($negRatio*100,1)."%. Top kategori: " . ($topCats ?: '—') . '.';
            }
        }

        return DB::transaction(function () use ($siswaKelasId, $from, $to, $avg, $keywords, $createdByUserId, $usedItems, $joinedText, $summary, $clusters, $categoriesOverview, $autoSummary, $mlResponse) {
            // 3) simpan analisis entry
            $avgMood = \App\Models\PemantauanEmosiSiswa::query()
                ->where('siswa_kelas_id', $siswaKelasId)
                ->whereBetween('tanggal', [$from, $to])
                ->avg('skor');

            /** @var \App\Models\AnalisisEntry $entry */
            $entry = AnalisisEntry::create([
                'siswa_kelas_id' => $siswaKelasId,
                'created_by' => $createdByUserId,
                'skor_sentimen' => round($avg, 3),
                'avg_mood' => $avgMood ? round((float) $avgMood, 2) : null,
                'kata_kunci' => $keywords,   // cast JSON
                'summary' => $summary,
                'clusters' => $clusters,
                'categories_overview' => $categoriesOverview,
                'auto_summary' => $autoSummary,
                'used_items' => $usedItems,  // snapshot sources used
                'source' => 'gabungan',
                'source_id' => null,
                'tanggal_awal_proses' => $from . ' 00:00:00',
                'tanggal_akhir_proses' => $to . ' 23:59:59',
            ]);

            // 4) auto-generate rekomendasi dari master (KATEGORI KECIL BASED)
            // Extract kategori kodes from ML response recommendations
            $detectedKategoriKodes = [];
            if (isset($mlResponse['global_recommendations'])) {
                foreach ($mlResponse['global_recommendations'] as $rec) {
                    if (!empty($rec['kategori_kode'])) {
                        $detectedKategoriKodes[] = $rec['kategori_kode'];
                    }
                }
            }
            
            // Get master rekomendasi yang terkait dengan kategori yang terdeteksi
            $masters = MasterRekomendasi::query()
                ->where('is_active', true)
                ->with('kategoris')
                ->get();
            
            foreach ($masters as $m) {
                $rules = $m->rules ?? [];
                $ok = true;

                // Rule 1: Check sentiment threshold
                if (isset($rules['min_neg_score'])) {
                    $ok = $ok && ($entry->skor_sentimen <= (float) $rules['min_neg_score']);
                }

                // Rule 2: Check kategori match (NEW - Per Kategori Kecil)
                if ($ok && !empty($detectedKategoriKodes)) {
                    // Check if master rekomendasi is linked to any detected kategori
                    $masterKategoriKodes = $m->kategoris->pluck('kode')->toArray();
                    $hasMatchingKategori = !empty(array_intersect($detectedKategoriKodes, $masterKategoriKodes));
                    
                    // Only suggest if kategori matches
                    $ok = $ok && $hasMatchingKategori;
                }

                if ($ok) {
                    // Calculate match score based on kategori confidence
                    $matchScore = 1.0;
                    foreach ($mlResponse['global_recommendations'] ?? [] as $rec) {
                        $kategoriKode = $rec['kategori_kode'] ?? null;
                        if ($kategoriKode && in_array($kategoriKode, $m->kategoris->pluck('kode')->toArray())) {
                            $matchScore = max($matchScore, $rec['score'] ?? 1.0);
                        }
                    }
                    
                    AnalisisRekomendasi::create([
                        'analisis_entry_id' => $entry->id,
                        'master_rekomendasi_id' => $m->id,
                        'judul' => $m->judul,
                        'deskripsi' => $m->deskripsi,
                        'severity' => $m->severity,
                        'match_score' => round($matchScore, 4),
                        'status' => 'suggested',
                    ]);
                }
            }

            return $entry->load('rekomendasis');
        });

    }
}
