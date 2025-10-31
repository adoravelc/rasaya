<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SiswaXiiIps2Seeder extends Seeder
{
    use ConciseSeederHelpers;

    private array $namaSiswa = [
        'Andi Pratama','Budi Santoso','Citra Anindya','Dewi Kartika','Eka Saputra',
        'Fajar Maulana','Galih Wicaksono','Hana Lestari','Irfan Hidayat','Joko Setiawan',
        'Kartika Sari','Lestari Wulandari','Maya Salsabila','Nadia Putri','Oky Prakoso',
        'Putra Aditya','Qori Ramadhani','Rina Maharani','Satria Nugraha','Tio Pradipta',
        'Umi Kalsum','Vina Oktaviani','Wahyu Nugroho','Xaverius Damar','Yuni Safitri',
        'Zaki Firmansyah','Rizky Ananda','Naufal Akbar','Alya Meisya','Rahma Aulia',
    ];

    public function run(): void
    {
        $taId  = $this->ensureTahunAjaran();
        $kelas = DB::table('kelass')->where('tahun_ajaran_id',$taId)->where('tingkat','XII')->where('rombel',2)->first();

        $users = []; $siswas=[]; $roster=[];
        foreach ($this->namaSiswa as $nm) {
            $email = Str::slug($nm,'.').rand(100,999).'@rasaya.id';
            $users[] = [
                'identifier'=>(string)rand(100000,999999),
                'role'=>'siswa','name'=>$nm,'email'=>$email,
                'password'=>Hash::make('1'),'email_verified_at'=>$this->now(),
                'created_at'=>$this->now(),'updated_at'=>$this->now(),
            ];
        }
        // insert users batch
        $chunks = array_chunk($users, 100);
        $userIds = [];
        foreach ($chunks as $ch) {
            DB::table('users')->insert($ch);
            $startId = DB::getPdo()->lastInsertId();
            // tidak selalu sequential di semua DBMS, fallback ambil by email:
            foreach ($ch as $u) {
                $userIds[] = (int) DB::table('users')->where('email',$u['email'])->value('id');
            }
        }
        foreach ($userIds as $uid) {
            $siswas[] = ['user_id'=>$uid,'created_at'=>$this->now(),'updated_at'=>$this->now()];
        }
        DB::table('siswas')->insert($siswas);

        foreach ($userIds as $uid) {
            $roster[] = [
                'tahun_ajaran_id'=>$taId,'kelas_id'=>$kelas->id,'siswa_id'=>$uid,
                'is_active'=>true,'joined_at'=>$this->dateStart,
                'created_at'=>$this->now(),'updated_at'=>$this->now(),
            ];
        }
        DB::table('siswa_kelass')->insert($roster);
    }
}
