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

        // ambil per_entry untuk rata-rata
        $per = collect($res['per_entry'] ?? []);
        $avg = $per->avg(fn($x) => (float) ($x['sentiment'] ?? 0.0));

        // gunakan keyphrases dari ML (konversi ke format {term,count})
        $kps = collect($res['keyphrases'] ?? []);
        $keywords = $kps->map(function ($it) {
            $w = (float) ($it['weight'] ?? 0);
            $count = max(1, (int) round($w * 10));
            return ['term' => (string) $it['term'], 'count' => $count];
        })->values()->all();

        // gabungan teks untuk rule master rekomendasi
        $joinedText = mb_strtolower(collect($payload)->pluck('text')->join(' '));

        // simpan ke DB + generate rekomendasi
        return DB::transaction(function () use ($siswaKelasId, $from, $to, $avg, $keywords, $createdByUserId, $usedItems, $joinedText) {
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
                'used_items' => $usedItems,  // snapshot sources used
                'source' => 'gabungan',
                'source_id' => null,
                'tanggal_awal_proses' => $from . ' 00:00:00',
                'tanggal_akhir_proses' => $to . ' 23:59:59',
            ]);

            // 4) auto-generate rekomendasi dari master
            $masters = MasterRekomendasi::query()->where('is_active', true)->get();
            foreach ($masters as $m) {
                $rules = $m->rules ?? [];
                $ok = true;

                if (isset($rules['min_neg_score'])) {
                    $ok = $ok && ($entry->skor_sentimen <= (float) $rules['min_neg_score']);
                }
                if ($ok && !empty($rules['any_keywords'])) {
                    $found = false;
                    foreach ($rules['any_keywords'] as $kw) {
                        if (str_contains($joinedText, mb_strtolower($kw))) {
                            $found = true;
                            break;
                        }
                    }
                    $ok = $found;
                }

                if ($ok) {
                    AnalisisRekomendasi::create([
                        'analisis_entry_id' => $entry->id,
                        'master_rekomendasi_id' => $m->id,
                        'judul' => $m->judul,
                        'deskripsi' => $m->deskripsi,
                        'severity' => $m->severity,
                        'match_score' => 1.0,
                        'status' => 'suggested',
                    ]);
                }
            }

            return $entry->load('rekomendasis');
        });

    }
}
