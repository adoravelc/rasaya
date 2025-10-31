<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class RefleksiPribadiSeeder extends Seeder
{
    use ConciseSeederHelpers;

    private array $templates = [
        'sedih' => [
            "Beta hati susah skali hari ini, tugas menumpuk, kepala pusing. Besok beta coba more focus.",
            "Rasa down, none bantu kerja kelompok. Beta will try fix it pelan-pelan.",
            "Beta malas keluar kamar, pikiran kacau. Need rest dikit baru lanjut.",
        ],
        'marah' => [
            "Beta jengkel lia teman omong kasar, bikin panas hati. Next time beta tahan diri.",
            "Tadi guru tegur keras, beta marah tapi beta salah juga. Will improve.",
            "Emosi naik, group project kacau lagi. Beta usahakan atur ulang.",
        ],
        'cemas' => [
            "Beta takut nilai turun, ulangan dekat sekali. Beta belajar step by step.",
            "Perut rasa tegang, many things at once. Beta bikin jadwal dulu.",
            "Beta worry mama bapa, tugas sekolah banyak. Beta coba minta bantuan.",
        ],
        'lelah' => [
            "Capek berat, tidur kurang, badan lemas. Beta butuh rehat sedikit.",
            "Beta lelah pikul banyak hal. Try to slow down and breathe.",
            "Tenaga habis, pikiran kosong. Besok bangun pagi lebih cepat.",
        ],
        'campur' => [
            "Perasaan campur—sedih sama marah. Beta keep calm pelan-pelan.",
            "Hati kacau, bingung mau mulai dari mana. Beta catat to-do dulu.",
            "Beta pusing dan kesal, tapi masih semangat sedikit. One step at a time.",
        ],
    ];
    private array $moods = ['sedih','marah','cemas','lelah','campur'];

    public function run(): void
    {
        // Matikan query log untuk percepatan
        DB::connection()->disableQueryLog();

        // Siapkan tanggal fixed: 8 hari (26 Okt s/d 2 Nov)
        $dates = [];
        $d = new \DateTimeImmutable($this->dateStart, new \DateTimeZone($this->tz));
        $end = new \DateTimeImmutable($this->dateEnd, new \DateTimeZone($this->tz));
        while ($d <= $end) {
            $dates[] = $d->format('Y-m-d');
            $d = $d->modify('+1 day');
        }
        // Pastikan panjangnya 8
        // if (count($dates) !== 8) { /* aman saja, tapi di rentang kamu ini 8 */ }

        $now = $this->now();
        $rows = [];

        // Ambil semua siswa_kelas
        $skIds = DB::table('siswa_kelass')->pluck('id')->all();

        foreach ($skIds as $skId) {
            // 1 entry per hari → 8 total, patuh unique (siswa_kelas_id, tanggal, is_friend)
            foreach ($dates as $tgl) {
                $mood = Arr::random($this->moods);
                $rows[] = [
                    'siswa_kelas_id'          => $skId,
                    'siswa_dilapor_kelas_id'  => null,
                    'is_friend'               => false,
                    'tanggal'                 => $tgl,
                    'teks'                    => Arr::random($this->templates[$mood]),
                    'avg_emosi'               => null,
                    'gambar'                  => null,
                    'status_upload'           => 1,
                    'meta'                    => json_encode(['mood' => $mood]),
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ];
            }
        }

        // Bulk insert sekali (bisa di-chunk kalau mau lebih kecil)
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('input_siswas')->insert($chunk);
        }
    }
}
