<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Arr;

trait ConciseSeederHelpers
{
    protected string $tz = 'Asia/Jakarta';
    protected string $dateStart = '2025-10-26';
    protected string $dateEnd   = '2025-11-02';

    protected function now() { return Carbon::now($this->tz); }
    protected function rdate(): Carbon {
        $s = Carbon::parse($this->dateStart,$this->tz)->startOfDay()->timestamp;
        $e = Carbon::parse($this->dateEnd,$this->tz)->endOfDay()->timestamp;
        return Carbon::createFromTimestamp(rand($s,$e))->setTimezone($this->tz);
    }

    protected function ensureTahunAjaran(): int
    {
        $tbl = 'tahun_ajarans';
        if (!Schema::hasTable($tbl)) return 1;
        $exist = DB::table($tbl)->orderByDesc('id')->first();
        if ($exist) return (int) $exist->id;

        $payload = [];
        foreach (['nama','tahun','kode','label'] as $c) {
            if (Schema::hasColumn($tbl,$c)) { $payload[$c] = '2025/2026'; break; }
        }
        if (Schema::hasColumn($tbl,'mulai'))   $payload['mulai'] = '2025-07-01';
        if (Schema::hasColumn($tbl,'selesai')) $payload['selesai'] = '2026-06-30';
        if (Schema::hasColumn($tbl,'created_at')) $payload['created_at'] = $this->now();
        if (Schema::hasColumn($tbl,'updated_at')) $payload['updated_at'] = $this->now();
        return DB::table($tbl)->insertGetId($payload);
    }

    protected function findJurusanIPS(): ?int
    {
        $tbl = 'jurusans';
        if (!Schema::hasTable($tbl)) return null;
        $cols = array_filter(['nama','kode','singkatan','label'], fn($c)=>Schema::hasColumn($tbl,$c));
        if (empty($cols)) { $r = DB::table($tbl)->first(); return $r? (int)$r->id : null; }
        $q = DB::table($tbl);
        foreach ($cols as $c) $q->orWhere($c,'IPS');
        $r = $q->first();
        return $r? (int)$r->id : null;
    }

    protected function ensureGuru(string $name, string $jenis): int
    {
        $u = DB::table('users')->where('name',$name)->where('role','guru')->first();
        $userId = $u? (int)$u->id : DB::table('users')->insertGetId([
            'identifier' => Str::slug($name).rand(100,999),
            'role' => 'guru',
            'name' => $name,
            'email' => Str::slug($name,'.').rand(100,999).'@rasaya.id',
            'password' => Hash::make('1'),
            'email_verified_at' => $this->now(),
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);
        $g = DB::table('gurus')->where('user_id',$userId)->first();
        if (!$g) DB::table('gurus')->insert([
            'user_id'=>$userId,'jenis'=>$jenis,'created_at'=>$this->now(),'updated_at'=>$this->now()
        ]);
        return $userId;
    }

    protected function ensureKelas(int $tahunAjaranId, string $tingkat, int $rombel, ?int $jurusanId, ?int $waliUserId): int
    {
        $q = DB::table('kelass')
            ->where('tahun_ajaran_id',$tahunAjaranId)
            ->where('tingkat',$tingkat)
            ->where('rombel',$rombel);
        if (Schema::hasColumn('kelass','jurusan_id')) $q->where('jurusan_id',$jurusanId);
        $exist = $q->first();
        if ($exist) return (int)$exist->id;

        $payload = [
            'tahun_ajaran_id'=>$tahunAjaranId,'tingkat'=>$tingkat,'rombel'=>$rombel,
            'created_at'=>$this->now(),'updated_at'=>$this->now()
        ];
        if (Schema::hasColumn('kelass','jurusan_id')) $payload['jurusan_id']=$jurusanId;
        if (Schema::hasColumn('kelass','wali_guru_id')) $payload['wali_guru_id']=$waliUserId;
        return DB::table('kelass')->insertGetId($payload);
    }

    protected function buildMoodSlots(): array
    {
        $slots = [];
        $start = Carbon::parse($this->dateStart,$this->tz);
        $end   = Carbon::parse($this->dateEnd,$this->tz);
        for ($d=$start->copy(); $d->lte($end); $d->addDay()) {
            $slots[] = [$d->copy()->setTime(7, rand(0,59)),  'pagi'];
            $slots[] = [$d->copy()->setTime(17, rand(0,59)), 'sore'];
        }
        while (count($slots) < 20) {
            $d = Carbon::parse($this->dateStart,$this->tz)->addDays(rand(0,7))->setTime(rand(6,10),rand(0,59));
            $slots[] = [$d,'pagi'];
        }
        $keyed=[]; foreach($slots as $s){ $k=$s[0]->toDateString().'#'.$s[1]; $keyed[$k]=$s; }
        return array_values($keyed);
    }

    protected function dedupePerTypePerDay(array $rows, bool $isFriend): array
    {
        $seen=[];
        foreach ($rows as &$r) {
            $key=$r['siswa_kelas_id'].'#'.$r['tanggal'].'#'.($isFriend?1:0);
            while(isset($seen[$key])){
                $dt=Carbon::parse($r['tanggal'],$this->tz)->addDay();
                if ($dt->gt(Carbon::parse($this->dateEnd,$this->tz))) $dt=Carbon::parse($this->dateStart,$this->tz);
                $r['tanggal']=$dt->toDateString(); $key=$r['siswa_kelas_id'].'#'.$r['tanggal'].'#'.($isFriend?1:0);
            }
            $seen[$key]=true;
        }
        return $rows;
    }
    protected function dedupeGuruUnique(array $rows): array
    {
        $seen=[];
        foreach ($rows as &$r) {
            $key=$r['guru_id'].'#'.$r['siswa_kelas_id'].'#'.$r['tanggal'];
            while(isset($seen[$key])){
                $dt=Carbon::parse($r['tanggal'],$this->tz)->addDay();
                if ($dt->gt(Carbon::parse($this->dateEnd,$this->tz))) $dt=Carbon::parse($this->dateStart,$this->tz);
                $r['tanggal']=$dt->toDateString(); $key=$r['guru_id'].'#'.$r['siswa_kelas_id'].'#'.$r['tanggal'];
            }
            $seen[$key]=true;
        }
        return $rows;
    }
    protected function dedupeMood(array $rows): array
    {
        $seen=[];
        foreach ($rows as &$r) {
            $key=$r['siswa_kelas_id'].'#'.$r['tanggal'].'#'.$r['sesi'];
            while(isset($seen[$key])){
                $dt=Carbon::parse($r['tanggal'],$this->tz)->addDay();
                if ($dt->gt(Carbon::parse($this->dateEnd,$this->tz))) $dt=Carbon::parse($this->dateStart,$this->tz);
                $r['tanggal']=$dt->toDateString(); $key=$r['siswa_kelas_id'].'#'.$r['tanggal'].'#'.$r['sesi'];
            }
            $seen[$key]=true;
        }
        return $rows;
    }
}
