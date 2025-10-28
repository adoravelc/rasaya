<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterRekomendasi;
use App\Models\KategoriMasalah;

class MasterRekomendasiBulkSeeder extends Seeder
{
    public function run(): void
    {
        $cats = KategoriMasalah::all()->keyBy(fn($c) => mb_strtolower(trim($c->nama)));

        $defs = [
            'akademik' => [
                ['kode' => 'AKD_01', 'judul' => 'Pendampingan manajemen waktu', 'severity' => 'low', 'keywords' => ['waktu','jadwal','prioritas','deadline']],
                ['kode' => 'AKD_02', 'judul' => 'Teknik belajar efektif (Pomodoro)', 'severity' => 'low', 'keywords' => ['belajar','konsentrasi','pomodoro']],
                ['kode' => 'AKD_03', 'judul' => 'Bimbingan perencanaan tugas mingguan', 'severity' => 'low', 'keywords' => ['tugas','PR','rencana']],
                ['kode' => 'AKD_04', 'judul' => 'Remedial terstruktur mapel sulit', 'severity' => 'medium', 'keywords' => ['remedial','nilai','ujian']],
                ['kode' => 'AKD_05', 'judul' => 'Mentoring sebaya akademik', 'severity' => 'low', 'keywords' => ['mentor','teman','kelompok']],
                ['kode' => 'AKD_06', 'judul' => 'Simulasi ujian dan refleksi', 'severity' => 'medium', 'keywords' => ['ujian','simulasi','coba']],
                ['kode' => 'AKD_07', 'judul' => 'Rujukan bimbingan belajar eksternal', 'severity' => 'medium', 'keywords' => ['bimbel','rujukan']],
                ['kode' => 'AKD_08', 'judul' => 'Konseling motivasi akademik', 'severity' => 'medium', 'keywords' => ['motivasi','malas','semangat']],
                ['kode' => 'AKD_09', 'judul' => 'Strategi catatan ringkas', 'severity' => 'low', 'keywords' => ['catatan','ringkas','highlight']],
                ['kode' => 'AKD_10', 'judul' => 'Kolaborasi dengan guru mapel', 'severity' => 'medium', 'keywords' => ['komunikasi','guru','mapel']],
            ],
            'emosi' => [
                ['kode' => 'EMO_01', 'judul' => 'Latihan pernapasan dan grounding', 'severity' => 'low', 'keywords' => ['cemas','gelisah','napas']],
                ['kode' => 'EMO_02', 'judul' => 'Jurnal emosi harian', 'severity' => 'low', 'keywords' => ['jurnal','mood','emosi']],
                ['kode' => 'EMO_03', 'judul' => 'Sesi konseling reguler', 'severity' => 'medium', 'keywords' => ['sedih','stres','konseling']],
                ['kode' => 'EMO_04', 'judul' => 'Rujukan psikolog sekolah', 'severity' => 'high', 'keywords' => ['krisis','psikolog','rujukan']],
                ['kode' => 'EMO_05', 'judul' => 'Teknik CBT untuk pikiran negatif', 'severity' => 'medium', 'keywords' => ['negatif','CBT','pikiran']],
                ['kode' => 'EMO_06', 'judul' => 'Manajemen stres (sleep, diet, aktivitas)', 'severity' => 'medium', 'keywords' => ['stres','tidur','olahraga']],
                ['kode' => 'EMO_07', 'judul' => 'Kelompok dukungan sebaya', 'severity' => 'low', 'keywords' => ['dukungan','teman','berbagi']],
                ['kode' => 'EMO_08', 'judul' => 'Teknik mindfulness dasar', 'severity' => 'low', 'keywords' => ['mindfulness','meditasi','fokus']],
                ['kode' => 'EMO_09', 'judul' => 'Rencana keamanan (safety plan)', 'severity' => 'high', 'keywords' => ['bahaya','aman','safety']],
                ['kode' => 'EMO_10', 'judul' => 'Edukasi kesehatan mental', 'severity' => 'low', 'keywords' => ['edukasi','mental','kesehatan']],
            ],
            'sosial' => [
                ['kode' => 'SOS_01', 'judul' => 'Mediasi konflik teman', 'severity' => 'medium', 'keywords' => ['konflik','teman','mediasi']],
                ['kode' => 'SOS_02', 'judul' => 'Pelatihan komunikasi asertif', 'severity' => 'low', 'keywords' => ['asertif','komunikasi','tolak']],
                ['kode' => 'SOS_03', 'judul' => 'Intervensi anti-perundungan', 'severity' => 'high', 'keywords' => ['bully','perundungan','lapor']],
                ['kode' => 'SOS_04', 'judul' => 'Keterampilan empati dan kerja tim', 'severity' => 'low', 'keywords' => ['empati','tim','kolaborasi']],
                ['kode' => 'SOS_05', 'judul' => 'Koordinasi dengan wali/orang tua', 'severity' => 'medium', 'keywords' => ['orang tua','komunikasi','wali']],
                ['kode' => 'SOS_06', 'judul' => 'Penempatan pendamping sebaya', 'severity' => 'low', 'keywords' => ['pendamping','buddy','sekolah']],
                ['kode' => 'SOS_07', 'judul' => 'Pelatihan resolusi konflik', 'severity' => 'medium', 'keywords' => ['resolusi','cekcok','damai']],
                ['kode' => 'SOS_08', 'judul' => 'Edukasi etika digital', 'severity' => 'low', 'keywords' => ['siber','chat','media']],
                ['kode' => 'SOS_09', 'judul' => 'Pengawasan area rawan konflik', 'severity' => 'medium', 'keywords' => ['pengawasan','rawan','zona']],
                ['kode' => 'SOS_10', 'judul' => 'Program pertemanan inklusif', 'severity' => 'low', 'keywords' => ['inklusif','teman baru','kelas']],
            ],
            'disiplin' => [
                ['kode' => 'DIS_01', 'judul' => 'Kontrak kehadiran', 'severity' => 'medium', 'keywords' => ['hadir','telat','bolos']],
                ['kode' => 'DIS_02', 'judul' => 'Pendampingan rutinitas pagi', 'severity' => 'low', 'keywords' => ['rutinitas','bangun','kesiapan']],
                ['kode' => 'DIS_03', 'judul' => 'Sanksi edukatif proporsional', 'severity' => 'medium', 'keywords' => ['sanksi','edukatif','aturan']],
                ['kode' => 'DIS_04', 'judul' => 'Konseling nilai kedisiplinan', 'severity' => 'low', 'keywords' => ['disiplin','komitmen','nilai']],
                ['kode' => 'DIS_05', 'judul' => 'Koordinasi dengan orang tua', 'severity' => 'medium', 'keywords' => ['orang tua','laporan','monitor']],
                ['kode' => 'DIS_06', 'judul' => 'Penguatan peraturan kelas', 'severity' => 'low', 'keywords' => ['aturan','kelas','ketertiban']],
                ['kode' => 'DIS_07', 'judul' => 'Pendampingan manajemen gadget', 'severity' => 'low', 'keywords' => ['gadget','hp','distraksi']],
                ['kode' => 'DIS_08', 'judul' => 'Program penghargaan kehadiran', 'severity' => 'low', 'keywords' => ['penghargaan','hadir','rekap']],
                ['kode' => 'DIS_09', 'judul' => 'Intervensi kebiasaan telat', 'severity' => 'medium', 'keywords' => ['telat','terlambat','alasan']],
                ['kode' => 'DIS_10', 'judul' => 'Rujukan layanan sosial (eksternal)', 'severity' => 'high', 'keywords' => ['eksternal','dukungan','layanan']],
            ],
        ];

        foreach ($defs as $catName => $items) {
            $cat = $cats[$catName] ?? null;
            if (!$cat) continue;

            foreach ($items as $it) {
                $payload = [
                    'judul' => $it['judul'],
                    'deskripsi' => $it['deskripsi'] ?? null,
                    'severity' => $it['severity'],
                    'is_active' => true,
                    'rules' => [
                        'min_neg_score' => in_array($it['severity'], ['high']) ? -0.15 : (in_array($it['severity'], ['medium']) ? -0.10 : -0.05),
                        'any_keywords' => $it['keywords'],
                    ],
                    'tags' => array_values(array_unique(array_merge([$catName], $it['keywords']))),
                ];

                $m = MasterRekomendasi::updateOrCreate(['kode' => $it['kode']], $payload);
                // Ensure pivot link to chosen category exists
                $m->kategoris()->syncWithoutDetaching([$cat->id]);
            }
        }
    }
}
