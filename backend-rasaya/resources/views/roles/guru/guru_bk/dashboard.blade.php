@extends('layouts.guru')

@section('title', 'Dashboard Guru BK')

@section('content')
    <div class="container-fluid">
        <div class="row">
            {{-- Main --}}
            <main class="col-12 col-md-9 col-lg-10 p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h2 class="h4 mb-1">Dashboard Guru BK</h2>
                        <div class="text-muted">Halo, {{ auth()->user()->name }}</div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="text-muted small mb-1">Statistik</div>
                                        <div class="fs-5 fw-semibold">Tren Emosi Siswa (Semua)</div>
                                    </div>
                                    <a href="{{ route('guru.tren_emosi.index') }}"
                                        class="btn btn-outline-primary btn-sm">Lihat semua</a>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-3">
                                    <select id="bk-period" class="form-select form-select-sm" style="max-width:180px;">
                                        <option value="daily">Harian</option>
                                        <option value="weekly">Mingguan</option>
                                        <option value="monthly">Bulanan</option>
                                    </select>
                                    <small id="bk-meta" class="text-muted"></small>
                                </div>
                                <canvas id="bk-emosi-chart" height="100" class="mt-3"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="text-muted small mb-1">Observasi</div>
                                        <div class="fs-5 fw-semibold">Input Guru</div>
                                    </div>
                                    <span class="display-6">📝</span>
                                </div>
                                <a href="{{ route('guru.observasi.index') }}"
                                    class="btn btn-primary btn-sm mt-3 stretched-link">Buka</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card shadow-sm h-100 border-primary">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="text-muted small mb-1">Konseling</div>
                                        <div class="fs-5 fw-semibold">Kelola Slot</div>
                                    </div>
                                    <span class="display-6">📅</span>
                                </div>
                                <a href="{{ route('guru.guru_bk.slots.view') }}"
                                    class="btn btn-primary btn-sm mt-3 stretched-link">Atur Slot</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="text-muted small mb-1">Mood Tracker</div>
                                        <div class="fs-5 fw-semibold">Ringkas</div>
                                    </div>
                                    <span class="display-6">📊</span>
                                </div>
                                <a href="{{ route('guru.tren_emosi.index') }}"
                                   class="btn btn-primary btn-sm mt-3 stretched-link">Lihat Tren Emosi</a>
                            </div>
                        </div>
                    </div>
                </div>

                @if (($attentionList ?? collect())->isNotEmpty())
                    <div class="card mt-4 shadow-sm">
                        <div class="card-header bg-white fw-semibold">Siswa Perlu Perhatian</div>
                        <div class="list-group list-group-flush">
                            @foreach ($attentionList as $a)
                                <a href="{{ route('guru.analisis.show', $a->id) }}"
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold">{{ optional($a->siswaKelas->siswa->user)->name }}</div>
                                        <div class="small text-muted">
                                            {{ optional($a->siswaKelas->kelas)->label }} — TA
                                            {{ optional($a->siswaKelas->kelas?->tahunAjaran)->nama }}
                                        </div>
                                    </div>
                                    <div class="text-end small text-muted">
                                        <div>Analisis: {{ optional($a->created_at)->diffForHumans() }}</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="card mt-4 shadow-sm">
                    <div class="card-header bg-white fw-semibold">Hari Ini</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3 h-100">
                                    <div class="small text-muted">Tanggal</div>
                                    <div class="fs-5">{{ now('Asia/Makassar')->translatedFormat('l, d M Y') }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3 h-100">
                                    <div class="small text-muted">Akun</div>
                                    <div class="fs-5">{{ auth()->user()->email }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3 h-100">
                                    <div class="small text-muted">Peran</div>
                                    <div class="fs-5">Guru BK</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function() {
            const sel = document.getElementById('bk-period');
            const meta = document.getElementById('bk-meta');
            let chart;
            // Emoji scale aligned with Flutter UI (highest -> 🤩, then 😄, 🙂, 😐, 🙁 for lowest)
            const scoreEmoji = (y) => {
                const s = Math.round(y);
                if (s >= 10) return '🤩';
                if (s >= 9) return '😍';
                if (s >= 8) return '😎';
                if (s >= 7) return '😊';
                if (s >= 6) return '😴';
                if (s >= 5) return '😐';
                if (s >= 4) return '😟';
                if (s >= 3) return '😔';
                if (s >= 2) return '😭';
                return '😓';
            };
            const lerp = (a, b, t) => a + (b - a) * t;
            const scoreToColor = (s) => {
                // map 1..5 to red (#ef4444) -> yellow (#f59e0b) -> green (#16a34a)
                const clamp = Math.max(1, Math.min(5, s || 3));
                const t = (clamp - 1) / 4; // 0..1
                const stops = [{
                        r: 239,
                        g: 68,
                        b: 68
                    }, // red
                    {
                        r: 245,
                        g: 158,
                        b: 11
                    }, // amber
                    {
                        r: 22,
                        g: 163,
                        b: 74
                    } // green
                ];
                const s1 = t < 0.5 ? stops[0] : stops[1];
                const s2 = t < 0.5 ? stops[1] : stops[2];
                const tt = t < 0.5 ? (t / 0.5) : ((t - 0.5) / 0.5);
                const r = Math.round(lerp(s1.r, s2.r, tt));
                const g = Math.round(lerp(s1.g, s2.g, tt));
                const b = Math.round(lerp(s1.b, s2.b, tt));
                return `rgb(${r}, ${g}, ${b})`;
            };
            const todayLocal = () => {
                const d = new Date();
                d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
                return d.toISOString().slice(0, 10);
            };
            async function load() {
                const params = new URLSearchParams({
                    period: sel.value || 'daily',
                    from: todayLocal(),
                    to: todayLocal()
                });
                const res = await fetch(`{{ route('guru.tren_emosi.data') }}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                const ys = data.datasets?.[0]?.data || [];
                const labels = data.labels || [];
                const ctx = document.getElementById('bk-emosi-chart').getContext('2d');
                if (chart) chart.destroy();
                chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Rata-rata Emosi',
                            data: ys,
                            pointBackgroundColor: ys.map(scoreToColor),
                            pointBorderColor: ys.map(scoreToColor),
                            borderColor: ys.length ? scoreToColor(ys.reduce((a, b) => a + b, 0) / ys
                                .length) : '#94a3b8',
                            backgroundColor: 'rgba(148,163,184,.15)', // neutral fill
                            tension: .25,
                            fill: true
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                suggestedMin: 1,
                                suggestedMax: 5,
                                ticks: {
                                    stepSize: 1,
                                    callback: (v) => scoreEmoji(v)
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => ` ${scoreEmoji(ctx.parsed.y)}  avg ${ctx.parsed.y}`
                                }
                            }
                        },
                        segment: {
                            borderColor: ctx => scoreToColor(ctx.p1.parsed.y)
                        }
                    }
                });
                meta.textContent = `Periode: ${data.period} — ${data.from} s/d ${data.to}`;
            }
            sel?.addEventListener('change', load);
            load();
        })();
    </script>
@endpush
