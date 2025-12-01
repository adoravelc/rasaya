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

                                @if(!auth()->user()->password_changed_at && auth()->user()->initial_password)
                                    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
                                        <span class="fw-semibold">Segera ubah password anda.</span>
                                        <span class="small">Gunakan token awal sebagai Password lama pada form ubah password.</span>
                                        <a href="{{ route('guru.profile.index', ['pwd' => 1]) }}" class="btn btn-sm btn-outline-dark ms-auto">Ubah Sekarang</a>
                                    </div>
                                @endif

                {{-- Jadwal Konseling Mendatang --}}
                @if($upcomingSchedules->count() > 0)
                <div class="card shadow-sm mb-4" style="border-left: 4px solid var(--guru-pink); background: rgba(236, 72, 153, 0.03);">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h5 class="mb-0" style="color: var(--guru-pink-dark);">
                                    <i class="bi bi-calendar-check me-2"></i>Jadwal Konseling Mendatang
                                </h5>
                                <small class="text-muted">7 hari ke depan</small>
                            </div>
                            <a href="{{ route('guru.guru_bk.slots.view') }}" class="btn btn-sm" style="background: var(--guru-navy); color: white;">
                                <i class="bi bi-gear me-1"></i>Kelola Jadwal
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr style="background: rgba(236, 72, 153, 0.08);">
                                        <th style="color: var(--guru-navy);">Waktu</th>
                                        <th style="color: var(--guru-navy);">Siswa</th>
                                        <th style="color: var(--guru-navy);">Kelas</th>
                                        <th style="color: var(--guru-navy);">Status</th>
                                        <th style="color: var(--guru-navy);">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($upcomingSchedules as $booking)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold" style="color: var(--guru-navy);">
                                                {{ $booking->slot->start_at->format('d M Y') }}
                                            </div>
                                            <small class="text-muted">
                                                {{ $booking->slot->start_at->format('H:i') }} - {{ $booking->slot->end_at->format('H:i') }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="fw-medium">{{ $booking->siswaKelas->siswa->user->name }}</div>
                                            <small class="text-muted">{{ $booking->siswaKelas->siswa->user->identifier }}</small>
                                        </td>
                                        <td>
                                            @if($booking->siswaKelas->kelas)
                                                <div class="fw-medium">{{ $booking->siswaKelas->kelas->label }}</div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($booking->status === 'booked')
                                                <span class="badge bg-success">Terpesan</span>
                                            @elseif($booking->status === 'completed')
                                                <span class="badge bg-primary">Selesai</span>
                                            @elseif($booking->status === 'canceled')
                                                <span class="badge bg-danger">Dibatalkan</span>
                                            @elseif($booking->status === 'no_show')
                                                <span class="badge bg-secondary">Tidak Hadir</span>
                                            @endif
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#updateStatusModal"
                                                    data-booking-id="{{ $booking->id }}"
                                                    data-booking-status="{{ $booking->status }}"
                                                    data-siswa-name="{{ $booking->siswaKelas->siswa->user->name }}"
                                                    data-slot-start="{{ $booking->slot->start_at->toIso8601String() }}">
                                                <i class="bi bi-pencil-square"></i> Ubah
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
                {{-- Pending Referrals --}}
                @if(isset($pendingReferrals) && $pendingReferrals->count() > 0)
                <div class="card shadow-sm mb-4" style="border-left:4px solid var(--guru-navy);background:rgba(30,58,138,0.04);">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h5 class="mb-0" style="color:var(--guru-navy);"><i class="bi bi-person-raised-hand me-2"></i>Permintaan Konseling (Referral)</h5>
                                <small class="text-muted">Guru lain / Wali Kelas mengajukan siswa untuk konseling</small>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr style="background:rgba(30,58,138,0.08);">
                                        <th style="color:var(--guru-navy);">Siswa</th>
                                        <th style="color:var(--guru-navy);">Diajukan Oleh</th>
                                        <th style="color:var(--guru-navy);">Kelas</th>
                                        <th style="color:var(--guru-navy);">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingReferrals as $ref)
                                    <tr>
                                        <td>
                                            <div class="fw-medium">{{ optional($ref->siswaKelas->siswa->user)->name }}</div>
                                            <small class="text-muted">{{ optional($ref->siswaKelas->siswa->user)->identifier }}</small>
                                        </td>
                                        <td>
                                            <div class="fw-medium">{{ optional($ref->submittedBy)->name }}</div>
                                            <small class="text-muted">{{ optional($ref->submittedBy)->identifier }}</small>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-light">{{ optional($ref->siswaKelas->kelas)->label }}</span>
                                        </td>
                                        <td>
                                            <form method="post" action="{{ route('guru.referrals.accept', $ref->id) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-primary" onclick="return confirm('Terima referral dan jadwalkan konseling privat?')">
                                                    <i class="bi bi-check2-circle me-1"></i>Terima
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
                                
                {{-- Chart Section --}}
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm card-guru-accent">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="text-muted small mb-1">📈 Statistik</div>
                                        <div class="fs-5 fw-semibold">Tren Emosi Siswa (Semua)</div>
                                    </div>
                                    <a href="{{ route('guru.tren_emosi.index') }}"
                                        class="btn btn-sm" style="background: var(--guru-navy); color: white;">Lihat Semua</a>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-3">
                                    <select id="bk-period" class="form-select form-select-sm" style="max-width:180px;">
                                        <option value="daily">Harian</option>
                                        <option value="weekly">Mingguan</option>
                                        <option value="monthly">Bulanan</option>
                                    </select>
                                    <small id="bk-meta" class="text-muted"></small>
                                </div>
                                <canvas id="bk-emosi-chart" height="140" class="mt-3"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Menu Cards Grid --}}
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card shadow-sm h-100 card-guru-pink">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fs-2">📝</span>
                                    <span class="badge rounded-pill" style="background: var(--guru-pink-dark);">Data Input</span>
                                </div>
                                <div class="text-muted small mb-1">Observasi</div>
                                <div class="fs-6 fw-semibold mb-3">Input Guru</div>
                                <a href="{{ route('guru.observasi.index') }}"
                                    class="btn btn-sm w-100 stretched-link" style="background: var(--guru-navy); color: white;">Buka</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card shadow-sm h-100 card-guru-pink">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fs-2">🔍</span>
                                    <span class="badge rounded-pill" style="background: var(--guru-navy);">Analisis</span>
                                </div>
                                <div class="text-muted small mb-1">Review</div>
                                <div class="fs-6 fw-semibold mb-3">Analisis Input</div>
                                <a href="{{ route('guru.analisis.index') }}"
                                   class="btn btn-sm w-100 stretched-link" style="background: var(--guru-pink-dark); color: white;">Lihat</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card shadow-sm h-100 card-guru-pink">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fs-2">📅</span>
                                    <span class="badge rounded-pill" style="background: var(--guru-pink-dark);">Konseling</span>
                                </div>
                                <div class="text-muted small mb-1">Layanan BK</div>
                                <div class="fs-6 fw-semibold mb-3">Kelola Slot</div>
                                <a href="{{ route('guru.guru_bk.slots.view') }}"
                                    class="btn btn-sm w-100 stretched-link" style="background: var(--guru-navy); color: white;">Atur Slot</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card shadow-sm h-100 card-guru-pink">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fs-2">📊</span>
                                    <span class="badge rounded-pill" style="background: var(--guru-navy);">Monitoring</span>
                                </div>
                                <div class="text-muted small mb-1">Pelacak Suasana Hati</div>
                                <div class="fs-6 fw-semibold mb-3">Tren Emosi</div>
                                <a href="{{ route('guru.tren_emosi.index') }}"
                                   class="btn btn-sm w-100 stretched-link" style="background: var(--guru-pink-dark); color: white;">Lihat Data</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card shadow-sm h-100 card-guru-pink">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fs-2">💭</span>
                                    <span class="badge rounded-pill" style="background: var(--guru-pink-dark);">Jurnal</span>
                                </div>
                                <div class="text-muted small mb-1">Laporan Diri</div>
                                <div class="fs-6 fw-semibold mb-3">Refleksi Siswa</div>
                                <a href="{{ route('guru.refleksi.index') }}"
                                    class="btn btn-sm w-100 stretched-link" style="background: var(--guru-navy); color: white;">Baca</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card shadow-sm h-100 card-guru-pink">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fs-2">📚</span>
                                    <span class="badge rounded-pill" style="background: var(--guru-navy);">Riwayat</span>
                                </div>
                                <div class="text-muted small mb-1">Riwayat Data</div>
                                <div class="fs-6 fw-semibold mb-3">History Refleksi (Lintas Tahun)</div>
                                <a href="{{ route('guru.bk.refleksi-history') }}"
                                   class="btn btn-sm w-100 stretched-link" style="background: var(--guru-pink-dark); color: white;">Lihat</a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Siswa Butuh Perhatian (Merah) --}}
                @if (($attentionList ?? collect())->isNotEmpty())
                    <div class="card mt-4 shadow-sm border-danger">
                        <div class="card-header bg-danger text-white fw-semibold">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Siswa Butuh Perhatian
                        </div>
                        <div class="list-group list-group-flush">
                            @foreach ($attentionList as $a)
                                @php($ageDays = optional($a->created_at)->diffInDays(now()))
                                @php($overdue = $ageDays >= 2)
                                <a href="{{ route('guru.analisis.show', $a->id) }}"
                                   class="list-group-item list-group-item-action list-group-item-danger d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold {{ $overdue ? 'text-danger' : '' }}">{{ optional($a->siswaKelas->siswa->user)->name }}
                                            @if($overdue)
                                                <span class="badge bg-danger ms-2">Reminder {{ $ageDays }} hari</span>
                                            @else
                                                <span class="badge bg-danger ms-2">Butuh Perhatian</span>
                                            @endif
                                        </div>
                                        <div class="small text-muted">
                                            {{ optional($a->siswaKelas->kelas)->label }} — TA {{ optional($a->siswaKelas->kelas?->tahunAjaran)->nama }}
                                        </div>
                                    </div>
                                    <div class="text-end small text-muted">
                                        <div>{{ optional($a->created_at)->diffForHumans() }}</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Siswa Sedang Ditangani (Orange) --}}
                @if (($handledList ?? collect())->isNotEmpty())
                    <div class="card mt-4 shadow-sm border-warning">
                        <div class="card-header bg-warning text-dark fw-semibold">
                            <i class="bi bi-hourglass-split me-2"></i>Siswa Sedang Ditangani
                        </div>
                        <div class="list-group list-group-flush">
                            @foreach ($handledList as $a)
                                @php($ageDays = optional($a->created_at)->diffInDays(now()))
                                <a href="{{ route('guru.analisis.show', $a->id) }}"
                                   class="list-group-item list-group-item-action list-group-item-warning d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold">{{ optional($a->siswaKelas->siswa->user)->name }}
                                            <span class="badge bg-warning text-dark ms-2">Sedang Ditangani</span>
                                        </div>
                                        <div class="small text-muted">
                                            {{ optional($a->siswaKelas->kelas)->label }} — TA {{ optional($a->siswaKelas->kelas?->tahunAjaran)->nama }}
                                        </div>
                                    </div>
                                    <div class="text-end small text-muted">
                                        <div>{{ optional($a->created_at)->diffForHumans() }}</div>
                                        <div class="small">{{ $ageDays }} hari ditangani</div>
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
                // Default BK dashboard: bar per kelas (hari ini)
                const params = new URLSearchParams({ period: sel.value || 'daily', from: todayLocal(), to: todayLocal(), group: 'kelas' });
                const res = await fetch(`{{ route('guru.tren_emosi.data') }}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                const ys = data.datasets?.[0]?.data || [];
                const labels = data.labels || [];
                const colors = data.colors || ys.map(scoreToColor);
                const ctx = document.getElementById('bk-emosi-chart').getContext('2d');
                if (chart) chart.destroy();
                chart = new Chart(ctx, {
                    type: 'bar',
                    data: { labels, datasets: [{ label: 'Rata-rata Emosi', data: ys, backgroundColor: colors, borderWidth: 0, borderRadius: 6 }] },
                    options: { indexAxis: 'y', scales: { x: { suggestedMin: 1, suggestedMax: 10, ticks: { stepSize: 1, callback: (v)=>scoreEmoji(v) } }, y: { ticks: { autoSkip: false } } }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx)=>` ${scoreEmoji(ctx.parsed.x)}  avg ${ctx.parsed.x}` } } } }
                });
                meta.textContent = `Periode: ${data.period} — ${data.from} s/d ${data.to}`;
            }
            sel?.addEventListener('change', load);
            load();
        })();
    </script>

    {{-- Modal Update Status Booking --}}
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="updateStatusForm" method="POST" action="">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateStatusModalLabel">Ubah Status Booking</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">
                            <strong>Siswa:</strong> <span id="modalSiswaName"></span>
                        </p>
                        <div class="mb-3">
                            <label for="statusSelect" class="form-label">Status Baru</label>
                            <select class="form-select" id="statusSelect" name="status" required>
                                <option value="">-- Pilih Status --</option>
                                <option value="completed">Completed</option>
                                <option value="canceled">Canceled</option>
                                <option value="no_show">No Show</option>
                            </select>
                            <div class="form-text" id="noShowHintDashboard" style="display: none;">
                                <i class="bi bi-info-circle"></i> "No Show" hanya bisa dipilih setelah waktu konseling dimulai
                            </div>
                        </div>
                        <div class="mb-3" id="cancelReasonGroup" style="display: none;">
                            <label for="cancelReasonInput" class="form-label">Alasan Pembatalan <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="cancelReasonInput" name="cancel_reason" rows="3" placeholder="Wajib diisi jika membatalkan konseling"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Script untuk modal update status
        document.addEventListener('DOMContentLoaded', function() {
            const updateStatusModal = document.getElementById('updateStatusModal');
            const updateStatusForm = document.getElementById('updateStatusForm');
            const statusSelect = document.getElementById('statusSelect');
            const cancelReasonGroup = document.getElementById('cancelReasonGroup');
            const noShowHintDashboard = document.getElementById('noShowHintDashboard');
            const modalSiswaName = document.getElementById('modalSiswaName');

            // Show/hide cancel reason field and no_show hint
            statusSelect.addEventListener('change', function() {
                if (this.value === 'canceled') {
                    cancelReasonGroup.style.display = 'block';
                    noShowHintDashboard.style.display = 'none';
                } else if (this.value === 'no_show') {
                    cancelReasonGroup.style.display = 'none';
                    noShowHintDashboard.style.display = 'block';
                } else {
                    cancelReasonGroup.style.display = 'none';
                    noShowHintDashboard.style.display = 'none';
                }
            });

            // Populate modal with booking data
            updateStatusModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const bookingId = button.getAttribute('data-booking-id');
                const bookingStatus = button.getAttribute('data-booking-status');
                const siswaName = button.getAttribute('data-siswa-name');
                const slotStartAt = button.getAttribute('data-slot-start');

                // Set form action
                updateStatusForm.action = `/guru/bk/bookings/${bookingId}/status`;

                // Reset form
                statusSelect.value = '';
                document.getElementById('cancelReasonInput').value = '';
                cancelReasonGroup.style.display = 'none';
                noShowHintDashboard.style.display = 'none';

                // Set siswa name
                modalSiswaName.textContent = siswaName;

                // Check if no_show is allowed (only after start time)
                if (slotStartAt) {
                    // Get current time in WITA timezone
                    const nowWita = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Makassar' }));
                    const startAt = new Date(slotStartAt);
                    const noShowOption = statusSelect.querySelector('option[value="no_show"]');
                    
                    if (startAt > nowWita) {
                        noShowOption.disabled = true;
                        noShowOption.textContent = 'No Show (Belum bisa dipilih)';
                    } else {
                        noShowOption.disabled = false;
                        noShowOption.textContent = 'No Show';
                    }
                }
            });
        });
    </script>
@endpush
