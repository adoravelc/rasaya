<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\PemantauanEmosiSiswa;
use App\Models\SiswaKelas;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmosiTrenController extends Controller
{
    public function index(Request $r)
    {
        $guruJenis = optional($r->user()->guru)->jenis; // 'bk' | 'wali_kelas'
        $kelasOptions = collect();
        $studentOptions = collect();

        if ($guruJenis === 'bk') {
            $kelasOptions = Kelas::with('tahunAjaran')
                ->orderBy('tahun_ajaran_id', 'desc')
                ->orderBy('tingkat')
                ->orderBy('rombel')
                ->get()
                ->map(fn($k)=>[ 'id'=>$k->id, 'label'=>$k->label . ' — ' . ($k->tahunAjaran->nama ?? '-') ]);
        } elseif ($guruJenis === 'wali_kelas') {
            // latest kelas for this WK
            $wkKelasId = Kelas::where('wali_guru_id', $r->user()->id)->latest('tahun_ajaran_id')->value('id');
            if ($wkKelasId) {
                $studentOptions = SiswaKelas::with(['siswa.user'])
                    ->where('kelas_id', $wkKelasId)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get()
                    ->map(fn($sk)=>[ 'id'=>$sk->id, 'label'=>($sk->siswa->user->name ?? '-') . ' (' . ($sk->siswa->user->identifier ?? '-') . ')' ]);
            }
        }

        return view('roles.guru.tren_emosi.index', [
            'defaultPeriod' => 'daily',
            'guruJenis' => $guruJenis,
            'kelasOptions' => $kelasOptions,
            'studentOptions' => $studentOptions,
        ]);
    }

    public function data(Request $r)
    {
        $r->validate([
            'period' => ['nullable', 'in:daily,weekly,monthly'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'kelas_id' => ['nullable', 'integer'],
            'siswa_kelas_id' => ['nullable', 'integer'],
        ]);

        $period = $r->input('period', 'daily');

        // Defaults per period
        $anchor = Carbon::parse($r->input('from', now()->toDateString()));
        $to = Carbon::parse($r->input('to', $anchor->toDateString()));
        if ($period === 'monthly') {
            $from = (clone $anchor)->startOfMonth();
            $to = (clone $anchor)->endOfMonth();
        } elseif ($period === 'weekly') {
            $from = (clone $anchor)->startOfWeek(Carbon::MONDAY);
            $to = (clone $anchor)->endOfWeek(Carbon::SUNDAY);
        } else { // daily
            $from = (clone $anchor)->startOfDay();
            $to = (clone $anchor)->endOfDay();
        }

        $user = $r->user();
        $guru = optional($user)->guru;

        $q = PemantauanEmosiSiswa::query();
        if ($period === 'daily') {
            $q->whereDate('tanggal', $from->toDateString());
        } else {
            $q->whereDate('tanggal', '>=', $from->toDateString())
              ->whereDate('tanggal', '<=', $to->toDateString());
        }

        // Scope for Wali Kelas: only their class students
        if ($guru && $guru->jenis === 'wali_kelas') {
            $q->whereHas('siswaKelas.kelas', function ($qq) use ($user) {
                $qq->where('wali_guru_id', $user->id);
            });
        }

        // Additional filter by kelas or specific siswa
        $kelasId = (int) $r->input('kelas_id', 0);
        $siswaKelasId = (int) $r->input('siswa_kelas_id', 0);
        if ($siswaKelasId) {
            $q->where('siswa_kelas_id', $siswaKelasId);
        } elseif ($kelasId) {
            $q->whereHas('siswaKelas', fn($qq)=> $qq->where('kelas_id', $kelasId));
        }

        // Build aggregation by period
        if ($period === 'daily') {
            // Daily: two buckets by sesi (pagi/sore) for the specific day
            $select = [
                DB::raw('sesi as bucket'),
                DB::raw('AVG(skor) as avg_skor'),
                DB::raw('COUNT(*) as n'),
            ];
            $q->select($select)
                ->groupBy('sesi')
                ->orderByRaw("FIELD(sesi, 'pagi','sore')");
        } else {
            // Weekly/Monthly: bucket by DATE(tanggal)
            $select = [
                DB::raw('DATE(tanggal) as bucket'),
                DB::raw('AVG(skor) as avg_skor'),
                DB::raw('COUNT(*) as n'),
            ];
            $q->select($select)->groupBy(DB::raw('DATE(tanggal)'))->orderBy('bucket');
        }

        $rows = $q->get();

        $labels = [];
        $values = [];
        $counts = [];
        if ($period === 'daily') {
            // Normalize labels order: pagi, sore
            $map = collect($rows)->keyBy('bucket');
            $order = ['pagi' => 'Pagi', 'sore' => 'Sore'];
            foreach ($order as $key => $label) {
                $labels[] = $label;
                $row = $map->get($key);
                $values[] = $row ? round((float) $row->avg_skor, 2) : null;
                $counts[] = $row ? (int) $row->n : 0;
            }
        } else {
            foreach ($rows as $r2) {
                $bucket = Carbon::parse($r2->bucket);
                $labels[] = $bucket->toDateString();
                $values[] = round((float) $r2->avg_skor, 2);
                $counts[] = (int) $r2->n;
            }
        }

        return response()->json([
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'labels' => $labels,
            'datasets' => [
                [ 'label' => 'Rata-rata Emosi', 'data' => $values ],
                [ 'label' => 'Jumlah Sampel', 'data' => $counts ],
            ],
        ]);
    }

    public function siswaByKelas(Request $r)
    {
        $r->validate(['kelas_id' => ['required', 'integer']]);
        $kelasId = (int) $r->kelas_id;
        $rows = SiswaKelas::with('siswa.user')
            ->where('kelas_id', $kelasId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn($sk)=>[ 'id'=>$sk->id, 'label'=>($sk->siswa->user->name ?? '-') . ' (' . ($sk->siswa->user->identifier ?? '-') . ')' ]);
        return response()->json([ 'items' => $rows ]);
    }
}
