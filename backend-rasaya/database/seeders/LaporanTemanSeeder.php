<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class LaporanTemanSeeder extends Seeder
{
    use ConciseSeederHelpers;

    public function run(): void
    {
        $roster = DB::table('siswa_kelass')->select('id','kelas_id')->get();
        $byKelas = [];
        foreach ($roster as $r) $byKelas[$r->kelas_id][] = $r->id;

        $template = [
            "Teman satu meja ribut kecil soal tugas, beta coba pisah dorang.",
            "Ada teman jadi pendiam karena masalah rumah, perlu diajak bicara pelan-pelan.",
            "Group chat panas, beberapa kata kasar muncul, perlu mediasi.",
            "Teman terlambat sering, mungkin ada masalah transport. Bisa tanya baik-baik.",
            "Dua teman adu argumen pas presentasi, suasana kelas tegang.",
        ];

        foreach ($byKelas as $kelasId => $list) {
            foreach ($list as $mine) {
                $rows=[];
                for($i=0;$i<5;$i++){
                    $target = Arr::random(array_values(array_filter($list,fn($x)=>$x!==$mine)));
                    $rows[] = [
                        'siswa_kelas_id'=>$mine,
                        'siswa_dilapor_kelas_id'=>$target,
                        'is_friend'=>true,
                        'tanggal'=>$this->rdate()->toDateString(),
                        'teks'=>Arr::random($template),
                        'avg_emosi'=>null,'gambar'=>null,'status_upload'=>1,'meta'=>null,
                        'created_at'=>$this->now(),'updated_at'=>$this->now(),
                    ];
                }
                $rows=$this->dedupePerTypePerDay($rows,true);
                DB::table('input_siswas')->insert($rows);
            }
        }
    }
}
