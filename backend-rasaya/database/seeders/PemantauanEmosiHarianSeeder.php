<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PemantauanEmosiHarianSeeder extends Seeder
{
    use ConciseSeederHelpers;

    public function run(): void
    {
        $sk = DB::table('siswa_kelass')->pluck('id')->all();

        foreach ($sk as $skId) {
            // ambil mood dominan dari refleksi pribadi (meta->mood) klo ada
            $moods = DB::table('input_siswas')
                ->where('siswa_kelas_id',$skId)
                ->where('is_friend',false)
                ->pluck('meta')->all();
            $cnt=[];
            foreach ($moods as $m) {
                $x = $m? json_decode($m,true):null;
                if ($x && isset($x['mood'])) $cnt[$x['mood']] = ($cnt[$x['mood']] ?? 0)+1;
            }
            arsort($cnt);
            $dom = $cnt? array_key_first($cnt) : 'sedih';

            $rows=[];
            foreach ($this->buildMoodSlots() as [$dt,$sesi]) {
                $rows[] = [
                    'siswa_kelas_id'=>$skId,
                    'tanggal'=>$dt->toDateString(),
                    'sesi'=>$sesi,
                    'skor'=>rand(2,4),
                    'gambar'=>null,
                    'catatan'=>"Mood {$dom} {$sesi}, coba tenang dan atur napas.",
                    'created_at'=>$this->now(),'updated_at'=>$this->now(),
                ];
            }
            $rows=$this->dedupeMood($rows);
            DB::table('pemantauan_emosi_siswas')->insert($rows);
        }
    }
}
