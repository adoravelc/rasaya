<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class ObservasiBkSeeder extends Seeder
{
    use ConciseSeederHelpers;

    public function run(): void
    {
        $bk = DB::table('users')->where('name','Natalia')->where('role','guru')->value('id');
        if (!$bk) $bk = $this->ensureGuru('Natalia','bk');

        $sk = DB::table('siswa_kelass')->pluck('id')->all();
        $notes = [
            "Siswa melaporkan kecemasan menghadapi ujian, diberikan teknik pernapasan 4-7-8.",
            "Dinamika kelompok memicu konflik, disarankan komunikasi asertif.",
            "Kesulitan tidur, diberikan sleep hygiene checklist.",
            "Tekanan dari rumah mempengaruhi fokus, rencanakan coping plan.",
        ];
        foreach ($sk as $skId) {
            $rows=[];
            for($i=0;$i<3;$i++){
                $rows[]=[
                    'guru_id'=>$bk,'siswa_kelas_id'=>$skId,'tanggal'=>$this->rdate()->toDateString(),
                    'teks'=>Arr::random($notes),'gambar'=>null,
                    'kondisi_siswa'=>Arr::random(['orange','red','black','grey']),
                    'created_at'=>$this->now(),'updated_at'=>$this->now(),
                ];
            }
            $rows=$this->dedupeGuruUnique($rows);
            DB::table('input_gurus')->insert($rows);
        }
    }
}
