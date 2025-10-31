<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class ObservasiWaliSeeder extends Seeder
{
    use ConciseSeederHelpers;

    public function run(): void
    {
        $wali = DB::table('users')->where('name','David')->where('role','guru')->value('id');
        if (!$wali) $wali = $this->ensureGuru('David','wali_kelas');

        $sk = DB::table('siswa_kelass')->pluck('id')->all();
        $notes = [
            "Siswa tampak lelah di pagi hari, disarankan istirahat cukup.",
            "Perhatian mudah teralih, perlu pendampingan belajar.",
            "Interaksi dengan teman menurun, observasi lanjutan diperlukan.",
            "Tugas dikumpul terlambat, perlu manajemen waktu.",
            "Respon saat ditegur baik, menunjukkan kemauan berubah.",
        ];
        foreach ($sk as $skId) {
            $rows=[];
            for($i=0;$i<5;$i++){
                $rows[]=[
                    'guru_id'=>$wali,'siswa_kelas_id'=>$skId,'tanggal'=>$this->rdate()->toDateString(),
                    'teks'=>Arr::random($notes),'gambar'=>null,
                    'kondisi_siswa'=>Arr::random(['yellow','orange','red','grey']),
                    'created_at'=>$this->now(),'updated_at'=>$this->now(),
                ];
            }
            $rows=$this->dedupeGuruUnique($rows);
            DB::table('input_gurus')->insert($rows);
        }
    }
}
