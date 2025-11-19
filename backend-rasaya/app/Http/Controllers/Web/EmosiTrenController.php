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
            'group' => ['nullable', 'in:time,kelas,siswa'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'span' => ['nullable', 'in:day,week,month,year'],
            'kelas_id' => ['nullable', 'integer'],
            'siswa_kelas_id' => ['nullable', 'integer'],
            'score_min' => ['nullable', 'numeric'],
            'score_max' => ['nullable', 'numeric'],
            'detail' => ['nullable', 'boolean'], // jika true, kembalikan items enriched untuk kotak detail
        ]);

        $period = $r->input('period', 'daily');
        $group = $r->input('group', 'time'); // time|kelas|siswa

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
            // default: the whole day; FE can pass custom from/to for multiple days view
            $from = (clone $anchor)->startOfDay();
            $to = (clone $anchor)->endOfDay();
        }

        // Optional span override to fill full buckets on trend page
        $span = $r->input('span');
        if ($span === 'week' && $period === 'daily') {
            $from = (clone $anchor)->startOfWeek(Carbon::MONDAY);
            $to = (clone $anchor)->endOfWeek(Carbon::SUNDAY);
        } elseif ($span === 'month' && $period === 'weekly') {
            $from = (clone $anchor)->startOfMonth();
            $to = (clone $anchor)->endOfMonth();
        } elseif ($span === 'year' && $period === 'monthly') {
            $from = (clone $anchor)->startOfYear();
            $to = (clone $anchor)->endOfYear();
        }

        $user = $r->user();
        $guru = optional($user)->guru;

        $q = PemantauanEmosiSiswa::query();
        $q->whereDate('tanggal', '>=', $from->toDateString())
          ->whereDate('tanggal', '<=', $to->toDateString());

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

        // score range for color scale (default assume 1..5 emoji scale)
        $scoreMin = (float) ($r->input('score_min', 1));
        $scoreMax = (float) ($r->input('score_max', 10));
        $colorFor = function (float $val) use ($scoreMin, $scoreMax): string {
            $v = max($scoreMin, min($scoreMax, $val));
            $t = ($v - $scoreMin) / max(1e-6, ($scoreMax - $scoreMin)); // 0..1
            // three-stop gradient: red (#ef4444) -> amber (#f59e0b) -> green (#10b981)
            $stops = [
                [0.0, [239,68,68]],
                [0.5, [245,158,11]],
                [1.0, [16,185,129]],
            ];
            // find segment
            for ($i=1;$i<count($stops);$i++) {
                if ($t <= $stops[$i][0]) {
                    [$t0,$c0] = $stops[$i-1];
                    [$t1,$c1] = $stops[$i];
                    $w = ($t - $t0) / max(1e-6, $t1 - $t0);
                    $r = (int) round($c0[0] + ($c1[0]-$c0[0])*$w);
                    $g = (int) round($c0[1] + ($c1[1]-$c0[1])*$w);
                    $b = (int) round($c0[2] + ($c1[2]-$c0[2])*$w);
                    return sprintf('#%02x%02x%02x', $r, $g, $b);
                }
            }
            return '#10b981';
        };

        // Build aggregation by group
        $rows = collect();
        $labels = [];
        $avg = [];
        $min = [];
        $max = [];
        $count = [];
        $colors = [];

        if ($group === 'time') {
            if ($period === 'monthly') {
                $select = [
                    DB::raw("DATE_FORMAT(tanggal, '%Y-%m') as bucket"),
                    DB::raw('AVG(skor) as avg_skor'),
                    DB::raw('MIN(skor) as min_skor'),
                    DB::raw('MAX(skor) as max_skor'),
                    DB::raw('COUNT(*) as n'),
                ];
                $rows = $q->select($select)
                    ->groupBy(DB::raw("DATE_FORMAT(tanggal, '%Y-%m')"))
                    ->orderBy('bucket')
                    ->get();
                // map results
                $map = collect($rows)->keyBy('bucket');
                // build full month buckets across range (month or year depending on span)
                $cur = (clone $from)->startOfMonth();
                while ($cur <= $to) {
                    $key = $cur->format('Y-m');
                    $labels[] = $cur->locale('id')->isoFormat('MMMM YYYY');
                    if ($map->has($key)) {
                        $r2 = $map->get($key);
                        $avg[] = round((float) $r2->avg_skor, 2);
                        $min[] = (float) $r2->min_skor;
                        $max[] = (float) $r2->max_skor;
                        $count[] = (int) $r2->n;
                        $colors[] = $colorFor((float) $r2->avg_skor);
                    } else {
                        $avg[] = null; $min[] = null; $max[] = null; $count[] = 0; $colors[] = 'rgba(0,0,0,0)';
                    }
                    $cur->addMonth();
                }
            } elseif ($period === 'weekly') {
                $select = [
                    DB::raw("DATE_FORMAT(tanggal, '%x-%v') as bucket"), // ISO year-week
                    DB::raw('AVG(skor) as avg_skor'),
                    DB::raw('MIN(skor) as min_skor'),
                    DB::raw('MAX(skor) as max_skor'),
                    DB::raw('COUNT(*) as n'),
                ];
                $rows = $q->select($select)
                    ->groupBy(DB::raw("DATE_FORMAT(tanggal, '%x-%v')"))
                    ->orderBy('bucket')
                    ->get();
                $map = collect($rows)->keyBy('bucket');
                // iterate weeks across range start..end
                $cur = (clone $from)->startOfWeek();
                while ($cur <= $to) {
                    $key = $cur->format('o-\WW'); // e.g., 2025-W47, but our map expects %x-%v
                    $key = $cur->format('o-') . str_pad($cur->isoWeek,2,'0',STR_PAD_LEFT); // 2025-47
                    $start = (clone $cur)->startOfWeek();
                    $end = (clone $cur)->endOfWeek();
                    $labels[] = $start->locale('id')->isoFormat('D MMM') . '–' . $end->locale('id')->isoFormat('D MMM');
                    if ($map->has($key)) {
                        $r2 = $map->get($key);
                        $avg[] = round((float) $r2->avg_skor, 2);
                        $min[] = (float) $r2->min_skor;
                        $max[] = (float) $r2->max_skor;
                        $count[] = (int) $r2->n;
                        $colors[] = $colorFor((float) $r2->avg_skor);
                    } else {
                        $avg[] = null; $min[] = null; $max[] = null; $count[] = 0; $colors[] = 'rgba(0,0,0,0)';
                    }
                    $cur->addWeek();
                }
            } else { // daily => bucket by DATE
                $select = [
                    DB::raw('DATE(tanggal) as bucket'),
                    DB::raw('AVG(skor) as avg_skor'),
                    DB::raw('MIN(skor) as min_skor'),
                    DB::raw('MAX(skor) as max_skor'),
                    DB::raw('COUNT(*) as n'),
                ];
                $rows = $q->select($select)->groupBy(DB::raw('DATE(tanggal)'))->orderBy('bucket')->get();
                $map = collect($rows)->keyBy('bucket');
                // iterate all days across range (usually one week for trend page)
                $cur = (clone $from)->startOfDay();
                while ($cur <= $to) {
                    $key = $cur->toDateString();
                    $labels[] = $cur->locale('id')->isoFormat('dddd');
                    if ($map->has($key)) {
                        $r2 = $map->get($key);
                        $avg[] = round((float) $r2->avg_skor, 2);
                        $min[] = (float) $r2->min_skor;
                        $max[] = (float) $r2->max_skor;
                        $count[] = (int) $r2->n;
                        $colors[] = $colorFor((float) $r2->avg_skor);
                    } else {
                        $avg[] = null; $min[] = null; $max[] = null; $count[] = 0; $colors[] = 'rgba(0,0,0,0)';
                    }
                    $cur->addDay();
                }
            }
        } elseif ($group === 'kelas') {
            // Group by kelas id
            $select = [
                DB::raw('sk.kelas_id as bucket'),
                DB::raw('AVG(pemantauan_emosi_siswas.skor) as avg_skor'),
                DB::raw('MIN(pemantauan_emosi_siswas.skor) as min_skor'),
                DB::raw('MAX(pemantauan_emosi_siswas.skor) as max_skor'),
                DB::raw('COUNT(*) as n'),
            ];
            $rows = $q->join('siswa_kelass as sk', 'sk.id', '=', 'pemantauan_emosi_siswas.siswa_kelas_id')
                ->select($select)
                ->groupBy('sk.kelas_id')
                ->orderBy('sk.kelas_id')
                ->get();
            $kelasMap = Kelas::whereIn('id', $rows->pluck('bucket')->all())
                ->get()->keyBy('id');
            foreach ($rows as $r2) {
                $k = $kelasMap->get((int)$r2->bucket);
                $labels[] = $k?->label ?? ('Kelas '.$r2->bucket);
                $avg[] = round((float) $r2->avg_skor, 2);
                $min[] = (float) $r2->min_skor;
                $max[] = (float) $r2->max_skor;
                $count[] = (int) $r2->n;
                $colors[] = $colorFor((float) $r2->avg_skor);
            }
        } else { // siswa
            // If no kelas specified and role is wali_kelas, pick their latest kelas
            if (!$kelasId && $guru && $guru->jenis === 'wali_kelas') {
                $kelasId = Kelas::where('wali_guru_id', $user->id)->latest('tahun_ajaran_id')->value('id') ?? 0;
            }
            if ($kelasId) {
                $q->whereHas('siswaKelas', fn($qq)=>$qq->where('kelas_id', $kelasId));
            }
            $select = [
                DB::raw('pemantauan_emosi_siswas.siswa_kelas_id as bucket'),
                DB::raw('AVG(pemantauan_emosi_siswas.skor) as avg_skor'),
                DB::raw('MIN(pemantauan_emosi_siswas.skor) as min_skor'),
                DB::raw('MAX(pemantauan_emosi_siswas.skor) as max_skor'),
                DB::raw('COUNT(*) as n'),
            ];
            $rows = $q->select($select)
                ->groupBy('pemantauan_emosi_siswas.siswa_kelas_id')
                ->orderBy('pemantauan_emosi_siswas.siswa_kelas_id')
                ->get();
            $siswas = SiswaKelas::with(['siswa.user'])
                ->whereIn('id', $rows->pluck('bucket')->all())
                ->get()->keyBy('id');
            foreach ($rows as $r2) {
                $sk = $siswas->get((int)$r2->bucket);
                $nm = optional($sk?->siswa?->user)->name ?? ('Siswa '.$r2->bucket);
                $labels[] = $nm;
                $avg[] = round((float) $r2->avg_skor, 2);
                $min[] = (float) $r2->min_skor;
                $max[] = (float) $r2->max_skor;
                $count[] = (int) $r2->n;
                $colors[] = $colorFor((float) $r2->avg_skor);
            }
        }

        // Detail items (kotak di bawah chart) sesuai grouping
        $detail = (bool) $r->boolean('detail', false);
        $items = [];
        if ($detail) {
            if ($group === 'kelas') {
                // daftar kelas dengan nilai agregat (pakai rows yang sudah dihitung)
                foreach ($rows as $r2) {
                    $kelasIdTmp = (int) $r2->bucket;
                    $k = $kelasMap->get($kelasIdTmp);
                    $items[] = [
                        'id' => $kelasIdTmp,
                        'label' => $k?->label ?? ('Kelas '.$kelasIdTmp),
                        'avg' => round((float) $r2->avg_skor, 2),
                        'min' => (float) $r2->min_skor,
                        'max' => (float) $r2->max_skor,
                        'count' => (int) $r2->n,
                        'color' => $colorFor((float) $r2->avg_skor),
                    ];
                }
            } elseif ($group === 'siswa') {
                foreach ($rows as $r2) {
                    $sid = (int) $r2->bucket;
                    $sk = $siswas->get($sid);
                    $items[] = [
                        'id' => $sid,
                        'label' => optional($sk?->siswa?->user)->name ?? ('Siswa '.$sid),
                        'avg' => round((float) $r2->avg_skor, 2),
                        'min' => (float) $r2->min_skor,
                        'max' => (float) $r2->max_skor,
                        'count' => (int) $r2->n,
                        'color' => $colorFor((float) $r2->avg_skor),
                    ];
                }
            } elseif ($group === 'time') {
                // detail per bucket waktu
                foreach ($rows as $i=>$r2) {
                    $items[] = [
                        'index' => $i,
                        'label' => $labels[$i] ?? $r2->bucket,
                        'avg' => round((float) $r2->avg_skor, 2),
                        'min' => (float) $r2->min_skor,
                        'max' => (float) $r2->max_skor,
                        'count' => (int) $r2->n,
                        'color' => $colors[$i] ?? $colorFor((float) $r2->avg_skor),
                    ];
                }
            }
        }

        return response()->json([
            'period' => $period,
            'group' => $group,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'labels' => $labels,
            'series' => [
                ['key' => 'avg', 'data' => $avg],
                ['key' => 'min', 'data' => $min],
                ['key' => 'max', 'data' => $max],
                ['key' => 'count', 'data' => $count],
            ],
            // Back-compat for existing FE that expects datasets[0].data
            'datasets' => [
                [ 'label' => 'Rata-rata Emosi', 'data' => $avg ],
                [ 'label' => 'Jumlah Sampel', 'data' => $count ],
            ],
            'colors' => $colors, // same order as labels for quick rendering
            'scale' => ['min' => $scoreMin, 'max' => $scoreMax],
            'items' => $items,
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
