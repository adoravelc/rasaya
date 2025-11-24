@extends('layouts.guru')
@section('title', 'Hasil Analisis')

@section('content')
    <div class="container py-4">
        <a href="{{ route('guru.analisis.index') }}" class="btn btn-sm btn-outline-secondary mb-3">← Kembali</a>

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('info'))
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-1"></i>{{ session('info') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i>{{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                @php
                    // Inisialisasi aman semua variabel tampilan analisis agar tidak undefined.
                    $sentimenScore = (float) ($analisis->skor_sentimen ?? 0);
                    $sentimenDesc = $sentimenDesc ?? 'Skor sentimen';
                    $sentimenScaleInfo = $sentimenScaleInfo ?? 'Skala −1 (merah/negatif) → 0 (hijau/netral)';
                    $moodDesc = $moodDesc ?? '';
                    $moodScaleInfo = $moodScaleInfo ?? '';
                    $clamped = max(-1, min(1, $sentimenScore));
                    $sentimenMarkerPct = (($clamped + 1) / 2) * 100;
                @endphp
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                        <div class="small text-muted">Siswa</div>
                        <div class="fw-semibold">
                            {{ optional($analisis->siswaKelas->siswa->user)->name }}
                            <span class="text-muted">({{ optional($analisis->siswaKelas->siswa->user)->identifier }})</span>
                        </div>
                        @if (!$isWali)
                            <div class="small">
                                Kelas: <strong>{{ optional($analisis->siswaKelas->kelas)->label }}</strong>
                            </div>
                        @endif
                    </div>
                </div>

                <h5 class="mb-1">Ringkasan</h5>
                <div class="mb-2">
                    <form id="attention-form" class="d-inline-flex align-items-center gap-2" method="post"
                        action="{{ route('guru.analisis.attention', $analisis->id) }}" onsubmit="return false;">
                        @csrf
                        <input type="hidden" name="needs_attention" id="attention-input"
                            value="{{ $analisis->needs_attention ? '1' : '0' }}">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="attention-switch"
                                {{ $analisis->needs_attention ? 'checked' : '' }}>
                            <label class="form-check-label" for="attention-switch">Tandai perlu perhatian</label>
                        </div>
                        <span id="attention-status"
                            class="small {{ $analisis->needs_attention ? 'text-danger' : 'text-muted' }}">
                            {{ $analisis->needs_attention ? 'Butuh perhatian' : 'Normal' }}
                        </span>
                    </form>
                </div>

                <div id="handling-block" class="mb-2" style="{{ $analisis->needs_attention ? '' : 'display:none;' }}">
                    <div class="small text-muted mb-1">Status Penanganan</div>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button"
                            class="btn btn-outline-warning {{ $analisis->handling_status === 'handled' ? 'active' : '' }}"
                            onclick="updateHandlingStatus('{{ $analisis->id }}', 'handled')">
                            Sedang Ditangani
                        </button>
                        <button type="button"
                            class="btn btn-outline-success {{ $analisis->handling_status === 'resolved' ? 'active' : '' }}"
                            onclick="updateHandlingStatus('{{ $analisis->id }}', 'resolved')">
                            Sudah Selesai
                        </button>
                    </div>
                    @if ($analisis->handling_status)
                        <span id="handling-badge"
                            class="badge ms-2 {{ $analisis->handling_status === 'handled' ? 'text-bg-warning' : 'text-bg-success' }}">
                            {{ $analisis->handling_status === 'handled' ? 'Sedang Ditangani' : 'Selesai' }}
                        </span>
                    @endif
                </div>
                <div class="text-muted small mb-3">
                    Rentang: {{ \Illuminate\Support\Carbon::parse($analisis->tanggal_awal_proses)->toDateString() }} —
                    {{ \Illuminate\Support\Carbon::parse($analisis->tanggal_akhir_proses)->toDateString() }}
                </div>
                @php
                    $user = auth()->user();
                @endphp
                @php $guru = $user && $user->role === 'guru' ? \App\Models\Guru::where('user_id', $user->id)->first() : null; @endphp
                <div id="attention-actions-block"
                    class="mb-3 p-2 rounded-2 d-flex flex-wrap gap-2 align-items-center {{ $analisis->needs_attention && $user && $user->role === 'guru' ? '' : 'd-none' }}"
                    style="background:#f1f5f9;border:1px solid #e2e8f0;">
                    <div class="small fw-semibold text-danger me-2"><i class="bi bi-exclamation-circle me-1"></i>Perlu
                        Tindak Lanjut</div>
                    @if ($guru && $guru->jenis === 'bk')
                        <form method="post" action="{{ route('guru.referrals.analysis.direct', $analisis->id) }}"
                            class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary" style="background:#0d6efd"
                                onclick="return confirm('Buat referral internal & jadwalkan konseling privat?')">
                                <i class="bi bi-calendar-plus me-1"></i>Jadwalkan Konseling Privat
                            </button>
                        </form>
                    @elseif($guru)
                        <form method="post" action="{{ route('guru.referrals.store') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="siswa_kelas_id" value="{{ $analisis->siswaKelas->id }}">
                            <button type="submit" class="btn btn-sm btn-outline-primary"
                                onclick="return confirm('Ajukan konseling BK untuk siswa ini?')">
                                <i class="bi bi-send me-1"></i>Ajukan Konseling BK
                            </button>
                        </form>
                    @endif
                </div>
                @php
                    // Skor sentimen -1 (sangat negatif) .. +1 (sangat positif)
                    $clamped = max(-1, min(1, $sentimenScore));
                    $sentimenMarkerPct = (($clamped + 1) / 2) * 100;
                    $sentimenScaleInfo = $sentimenScaleInfo ?? '−1 sangat negatif • 0 netral • +1 sangat positif';
                    $moodDesc = $moodDesc ?? '';
                    $moodScaleInfo = $moodScaleInfo ?? '';
                @endphp
                <div class="mb-3">
                    <div class="small text-muted mb-1">Skor Sentimen (visual)</div>
                    <div class="sentimen-bar position-relative"
                        title="Skor Sentimen: {{ number_format($sentimenScore, 2) }}"
                        aria-label="Skor Sentimen: {{ number_format($sentimenScore, 2) }}"
                        style="height:18px;border-radius:9px;max-width:420px;background:linear-gradient(90deg,#dc2626 0%,#fbbf24 50%,#16a34a 100%);">
                        <div class="sentimen-marker"
                            style="position:absolute;top:-3px;bottom:-3px;left:calc({{ $sentimenMarkerPct }}% - 3px);width:8px;background:#fff;border:2px solid #1e293b;border-radius:4px;box-shadow:0 2px 6px rgba(0,0,0,.35);">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-2 small text-muted" style="max-width:420px;">
                        <span>-1</span><span>0</span><span>+1</span>
                    </div>
                    <span class="d-block small mt-1" style="font-weight:500;color:#334155;">{{ $sentimenDesc }}: <span
                            class="badge text-bg-light">{{ number_format($sentimenScore, 2) }}</span></span>
                    <span class="d-block small text-muted fst-italic">{{ $sentimenScaleInfo }}</span>
                </div>
                <div class="mt-3 mb-3">Skor Mood Rata-rata: <strong>{{ $avgMood ?? $analisis->avg_mood }}</strong>
                    <span class="d-block small text-muted">{{ $moodDesc }}</span>
                    <span class="d-block small text-muted fst-italic">{{ $moodScaleInfo }}</span>
                </div>
                @if (!empty($topEmojis) && isset($topEmojis[0]))
                    <div class="mt-3 mb-3">
                        <span class="text-muted">Emoji paling sering:</span>
                        <span class="badge text-bg-light"
                            title="muncul ×{{ $topEmojis[0]['count'] ?? 0 }}">{{ $topEmojis[0]['emoji'] ?? '—' }}
                            <small class="text-muted">×{{ $topEmojis[0]['count'] ?? 0 }}</small></span>
                    </div>
                    <div class="border-top my-3"></div>
                @endif
                {{-- Kata kunci disembunyikan sesuai permintaan untuk fokus ke kategori --}}
                @if (!empty($analisis->categories_overview))
                    <div class="mt-3">
                        <h6 class="mb-2">Top Kategori (Ranking)</h6>
                        <div class="small text-muted mb-2">Ditentukan dari gabungan pernyataan negatif dan tingkat
                            keparahan.</div>
                        @php
                            $allCats = collect($analisis->categories_overview ?? [])
                                ->sortByDesc(fn($r) => (float) ($r['score'] ?? 0))
                                ->values();
                            // filter kategori kecil (default: >= 5%)
                            $minScore = 0.05;
                            $cats = $allCats->filter(fn($r) => (float) ($r['score'] ?? 0) >= $minScore)->values();
                            if ($cats->isEmpty()) {
                                $cats = $allCats->take(5);
                            }
                            $max = max(0.0001, (float) ($cats->first()['score'] ?? 0.0001));
                        @endphp
                        <div class="list-group">
                            @foreach ($cats as $row)
                                @php
                                    $name = (string) ($row['category'] ?? '-');
                                    $catScore = (float) ($row['score'] ?? 0);
                                    $pct = (int) round(($catScore / $max) * 100);
                                    $pctTxt = number_format($catScore * 100, 1) . '%';
                                    $reasons = collect($row['reasons'] ?? [])
                                        ->filter()
                                        ->values()
                                        ->take(3)
                                        ->all();
                                @endphp
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>{{ $name }}</strong>
                                        <span class="small text-muted">{{ $pctTxt }}</span>
                                    </div>
                                    <div class="progress mt-1" style="height:8px;">
                                        <div class="progress-bar" role="progressbar" style="width: {{ $pct }}%"
                                            aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    @if (!empty($reasons))
                                        <div class="small text-muted mt-1">
                                            Alasan:
                                            @foreach ($reasons as $i => $r)
                                                <span
                                                    class="badge rounded-pill text-bg-light ms-0 me-1 mb-1">{{ $r }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if ($allCats->count() > $cats->count())
                            <div class="form-text mt-1">Kategori dengan porsi di bawah 5% disembunyikan.</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        @if (!empty($analisis->summary) || !empty($analisis->auto_summary))
            <div class="mt-4">
                <h6>Catatan Sistem</h6>
                @if (!empty($analisis->summary))
                    <p class="text-muted small mb-1">{!! htmlspecialchars($analisis->summary['notes'] ?? '–', ENT_QUOTES, 'UTF-8') !!}</p>
                @endif
                @if (!empty($analisis->auto_summary))
                    <div class="alert alert-primary py-2 px-3 small mb-0">
                        <strong>Kesimpulan Otomatis:</strong>
                        <span class="ms-1">{{ $analisis->auto_summary }}</span>
                    </div>
                @endif
                @if (!empty($mlWarnings))
                    <div class="mt-2 small">
                        @foreach ($mlWarnings as $w)
                            <div class="text-warning">⚠ {{ $w }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Klasterisasi disembunyikan sesuai permintaan. Fokus pada ranking kategori dan alasannya. --}}


        <div class="card mt-4">
            <div class="card-header"><strong>Rekomendasi Sistem</strong></div>
            <div class="list-group list-group-flush">
                @forelse($analisis->rekomendasis as $r)
                    {{-- 
                Logic Filter & Sorting sudah dipindah ke Controller.
                View hanya bertugas merender apa yang diberikan.
            --}}

                    <div class="list-group-item d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">
                                <a href="javascript:void(0)"
                                    onclick="openRekomDetail({{ $analisis->id }}, {{ $r->id }})">{{ $r->judul }}</a>
                            </div>
                            <div class="small text-muted">Klik judul untuk lihat detail</div>

                            {{-- Tampilkan Kategori & Severity untuk konfirmasi visual --}}
                            @php
                                $cats = $r->master?->kategoris->pluck('nama')->toArray() ?? [];
                                $catStr = !empty($cats) ? implode(', ', $cats) : 'Umum';
                                $minScore = $r->master?->rules['min_neg_score'] ?? 0;
                            @endphp
                            <div class="mt-1">
                                <span class="badge bg-light text-dark border">{{ $catStr }}</span>
                                <span class="badge bg-light text-secondary border ms-1" title="Minimal Skor Sentimen">Min:
                                    {{ $minScore }}</span>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted mb-1">Status: <strong>{{ $r->status }}</strong></div>
                            @if ($r->status === 'suggested')
                                <form class="d-inline" method="post"
                                    action="{{ route('guru.analisis.decide', [$analisis->id, $r->id]) }}"
                                    onsubmit="window.__accepted = true;">
                                    @csrf
                                    <input type="hidden" name="action" value="accept">
                                    <button class="btn btn-sm btn-success">Terima</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                    onclick="openReject({{ $analisis->id }}, {{ $r->id }})">Tolak</button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-muted">Belum ada rekomendasi yang sesuai dengan profil masalah siswa.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Semua Input dalam Rentang</strong>
                <span class="small text-muted">
                    Total:
                    {{ ($refleksisSelf->count() ?? 0) + ($friendReports->count() ?? 0) + ($guruNotes->count() ?? 0) }}
                    item
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <h6 class="mb-2">Refleksi Diri <span
                                class="badge text-bg-secondary">{{ $refleksisSelf->count() ?? 0 }}</span></h6>
                        <div class="list-group">
                            @forelse(($refleksisSelf ?? collect()) as $it)
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        @php
                                            $k = strtolower(trim((string) ($it->kondisi_siswa ?? '')));
                                            if ($k === 'grey') {
                                                $k = 'gray';
                                            }
                                            $pal = [
                                                'green' => ['bg' => '#c9f2da', 'bd' => '#198754'],
                                                'yellow' => ['bg' => '#ffefb3', 'bd' => '#ffc107'],
                                                'orange' => ['bg' => '#ffd8b0', 'bd' => '#fd7e14'],
                                                'red' => ['bg' => '#ffc9cf', 'bd' => '#dc3545'],
                                                'blue' => ['bg' => '#cfe7ff', 'bd' => '#0d6efd'],
                                                'gray' => ['bg' => '#f1f3f5', 'bd' => '#6c757d'],
                                            ];
                                            $c = $pal[$k] ?? $pal['gray'];
                                        @endphp
                                        <div class="list-group-item"
                                            style="background-color: {{ $c['bg'] }}; border-left: 4px solid {{ $c['bd'] }};">
                                            {{ \Illuminate\Support\Carbon::parse($it->tanggal)->toDateString() }}</div>
                                        @if (!empty($it->avg_emosi))
                                            <div class="small">Mood:
                                                <strong>{{ number_format($it->avg_emosi, 2) }}</strong>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="mt-1">{{ \Illuminate\Support\Str::limit($it->teks, 120) }}</div>
                                    @if (($it->kategoris ?? collect())->count())
                                        <div class="mt-1 small">
                                            @foreach ($it->kategoris as $k)
                                                <span class="badge text-bg-light">{{ $k->nama }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="text-muted small">Tidak ada data.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h6 class="mb-2">Laporan Teman <span
                                class="badge text-bg-secondary">{{ $friendReports->count() ?? 0 }}</span></h6>
                        <div class="list-group">
                            @forelse(($friendReports ?? collect()) as $it)
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div class="small text-muted">
                                            {{ \Illuminate\Support\Carbon::parse($it->tanggal)->toDateString() }}</div>
                                        @if ($it->siswaKelas && $it->siswaKelas->siswa && $it->siswaKelas->siswa->user)
                                            <div class="small">Pelapor:
                                                <strong>{{ $it->siswaKelas->siswa->user->name }}</strong>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="mt-1">{{ \Illuminate\Support\Str::limit($it->teks, 120) }}</div>
                                    @if (($it->kategoris ?? collect())->count())
                                        <div class="mt-1 small">
                                            @foreach ($it->kategoris as $k)
                                                <span class="badge text-bg-light">{{ $k->nama }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="text-muted small">Tidak ada data.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h6 class="mb-2">Observasi Guru <span
                                class="badge text-bg-secondary">{{ $guruNotes->count() ?? 0 }}</span></h6>
                        <div class="list-group">
                            @forelse(($guruNotes ?? collect()) as $it)
                                @php
                                    $k = strtolower(trim((string) ($it->kondisi_siswa ?? '')));
                                    // align with observasi index mapping
                                    if ($k === 'aman') {
                                        $k = 'green';
                                    }
                                    if ($k === 'gray') {
                                        $k = 'grey';
                                    }
                                    $pal = [
                                        'green' => ['bg' => '#dcfce7', 'bd' => '#16a34a'],
                                        'yellow' => ['bg' => '#fef9c3', 'bd' => '#f59e0b'],
                                        'orange' => ['bg' => '#ffedd5', 'bd' => '#fb923c'],
                                        'red' => ['bg' => '#fee2e2', 'bd' => '#ef4444'],
                                        'black' => ['bg' => '#e5e7eb', 'bd' => '#111827'],
                                        'grey' => ['bg' => '#f3f4f6', 'bd' => '#9ca3af'],
                                    ];
                                    $c = $pal[$k] ?? $pal['grey'];
                                @endphp
                                <div class="list-group-item"
                                    style="background-color: {{ $c['bg'] }}; border-left: 4px solid {{ $c['bd'] }};">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="small text-muted">
                                            {{ \Illuminate\Support\Carbon::parse($it->tanggal)->toDateString() }}</div>
                                        <div class="small d-flex align-items-center">
                                            <span class="rounded-circle me-1"
                                                style="display:inline-block;width:10px;height:10px;background: {{ $c['bd'] }}; border:1px solid #cbd5e1"></span>
                                            <span>Kondisi:
                                                <strong>{{ strtoupper($it->kondisi_siswa ?? '-') }}</strong></span>
                                        </div>
                                    </div>
                                    <div class="mt-1">{{ \Illuminate\Support\Str::limit($it->teks, 120) }}</div>
                                    @if (($it->kategoris ?? collect())->count())
                                        <div class="mt-1 small">
                                            @foreach ($it->kategoris as $k)
                                                <span class="badge text-bg-light">{{ $k->nama }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="text-muted small">Tidak ada data.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="rekomDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rekomDetailTitle">Detail Rekomendasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <div class="small text-muted">Judul</div>
                        <div id="rekomDetailJudul" class="fw-semibold">—</div>
                    </div>
                    <div class="mb-2">
                        <div class="small text-muted">Kategori</div>
                        <div id="rekomDetailKategori" class="text-primary">—</div>
                    </div>
                    <div class="mb-2">
                        <div class="small text-muted">Deskripsi</div>
                        <div id="rekomDetailDeskripsi">—</div>
                    </div>
                    <div class="mb-2 d-flex align-items-center gap-2">
                        <div class="small text-muted">Severity</div>
                        <span id="rekomDetailSeverity" class="badge text-bg-secondary">—</span>
                    </div>
                    <div class="mb-2">
                        <div class="small text-muted">Skor Sentimen Minimal</div>
                        <div id="rekomDetailMinScore">—</div>
                        <div class="form-text">Rekomendasi muncul jika skor sentimen siswa sama atau lebih rendah dari
                            nilai ini (lebih negatif).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tolak Rekomendasi Sistem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="rej-analisis-id" value="{{ $analisis->id }}">
                    <input type="hidden" id="rej-rekom-id" value="">

                    <div class="mb-3">
                        <label class="form-label">Kategori Masalah (pilih salah satu)</label>
                        <select id="rej-kategori" class="form-select">
                            <option value="">— Pilih Kategori —</option>
                            @foreach ($kategoris ?? collect() as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="rej-alt-container" class="d-none">
                        <div class="mb-2">Pilih rekomendasi tindakan alternatif (maks 5):</div>
                        <div id="rej-alt-list" class="list-group small"></div>
                    </div>
                    <div id="rej-error" class="text-danger small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger" onclick="submitReject()">Simpan Penolakan</button>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script>
        // ===== GLOBAL SCOPE - Accessible from inline handlers =====
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (!csrf) console.warn('CSRF token tidak ditemukan');

        let rejModal;
        let rekomDetailModal;

        // Init modals on DOM ready
        document.addEventListener('DOMContentLoaded', () => {
            const rejectModalEl = document.getElementById('rejectModal');
            const rekomDetailModalEl = document.getElementById('rekomDetailModal');

            if (!rejectModalEl || !rekomDetailModalEl) {
                console.error('Modal elements not found');
                return;
            }

            rejModal = new bootstrap.Modal(rejectModalEl, {
                backdrop: 'static'
            });
            rekomDetailModal = new bootstrap.Modal(rekomDetailModalEl, {
                backdrop: 'static'
            });

            // Attach event listener for kategori dropdown
            const sel = document.getElementById('rej-kategori');
            if (sel) sel.addEventListener('change', loadAlternatives);
        });

        // ===== GLOBAL FUNCTIONS =====
        async function openRekomDetail(analisisId, rekomId) {
            try {
                document.getElementById('rekomDetailTitle').textContent = 'Detail Rekomendasi';
                document.getElementById('rekomDetailJudul').textContent = '–';
                document.getElementById('rekomDetailKategori').textContent = '–';
                document.getElementById('rekomDetailDeskripsi').textContent = '–';
                document.getElementById('rekomDetailSeverity').textContent = '–';
                document.getElementById('rekomDetailMinScore').textContent = '–';

                const url = `${location.origin}/guru/analisis/${analisisId}/rekomendasi/${rekomId}`;
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!res.ok) throw new Error('Gagal memuat detail');
                const data = await res.json();

                document.getElementById('rekomDetailTitle').textContent = data.judul || 'Detail Rekomendasi';
                document.getElementById('rekomDetailJudul').textContent = data.judul || '–';
                document.getElementById('rekomDetailKategori').textContent = data.kategori || 'Umum';
                document.getElementById('rekomDetailDeskripsi').textContent = data.deskripsi || '–';
                document.getElementById('rekomDetailSeverity').textContent = data.severity || '–';

                const ms = (typeof data.min_neg_score === 'number') ? data.min_neg_score : null;
                document.getElementById('rekomDetailMinScore').textContent = (ms !== null) ? ms.toFixed(2) : '–';

                rekomDetailModal.show();
            } catch (err) {
                alert(err.message || 'Gagal memuat detail rekomendasi');
            }
        }

        function openReject(analisisId, rekomId) {
            document.getElementById('rej-analisis-id').value = analisisId;
            document.getElementById('rej-rekom-id').value = rekomId;
            document.getElementById('rej-kategori').value = '';
            document.getElementById('rej-alt-list').innerHTML = '';
            document.getElementById('rej-alt-container').classList.add('d-none');
            document.getElementById('rej-error').innerText = '';
            rejModal.show();
        }

        async function loadAlternatives() {
            const analisisId = document.getElementById('rej-analisis-id').value;
            const rekomId = document.getElementById('rej-rekom-id').value;
            const kategoriId = document.getElementById('rej-kategori').value;

            document.getElementById('rej-error').innerText = '';
            document.getElementById('rej-alt-list').innerHTML = '';
            document.getElementById('rej-alt-container').classList.add('d-none');

            if (!kategoriId) return;

            try {
                const url =
                    `${location.origin}/guru/analisis/${analisisId}/rekomendasi/${rekomId}/alternatives?kategori_id=${encodeURIComponent(kategoriId)}`;
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!res.ok) throw new Error('Gagal memuat alternatif');
                const data = await res.json();

                const list = document.getElementById('rej-alt-list');
                (data.items || []).forEach(it => {
                    const id = `alt-${it.id}`;
                    const el = document.createElement('label');
                    el.className = 'list-group-item list-group-item-action';
                    el.innerHTML = `
                        <input type="radio" class="form-check-input me-2" name="rej-alt" value="${it.id}" id="${id}">
                        <span class="fw-semibold">${it.judul}</span>
                        <div class="text-muted">${it.deskripsi || ''}</div>
                        <span class="badge bg-secondary">${it.severity}</span>
                    `;
                    list.appendChild(el);
                });

                document.getElementById('rej-alt-container').classList.remove('d-none');
            } catch (err) {
                document.getElementById('rej-error').innerText = err.message || 'Gagal memuat alternatif';
            }
        }

        async function submitReject() {
            const analisisId = document.getElementById('rej-analisis-id').value;
            const rekomId = document.getElementById('rej-rekom-id').value;
            const kategoriId = document.getElementById('rej-kategori').value;
            const alt = document.querySelector('input[name="rej-alt"]:checked');
            const altId = alt ? alt.value : '';

            document.getElementById('rej-error').innerText = '';

            if (!kategoriId || !altId) {
                document.getElementById('rej-error').innerText =
                    'Pilih kategori dan salah satu rekomendasi alternatif.';
                return;
            }

            const fd = new FormData();
            fd.append('action', 'reject');
            fd.append('kategori_id', kategoriId);
            fd.append('selected_master_rekomendasi_id', altId);

            try {
                const res = await fetch(
                    `${location.origin}/guru/analisis/${analisisId}/rekomendasi/${rekomId}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        body: fd
                    }
                );

                if (res.ok) {
                    rejModal.hide();
                    location.reload();
                    return;
                }

                const data = await res.json().catch(() => ({}));
                document.getElementById('rej-error').innerText = (data.message || '') + ' ' +
                    (data.errors ? JSON.stringify(data.errors) : '');
            } catch (err) {
                document.getElementById('rej-error').innerText = err.message || 'Gagal menyimpan';
            }
        }

        async function updateHandlingStatus(analisisId, status) {
            const url = `${location.origin}/guru/analisis/${analisisId}/handling-status`;
            const fd = new FormData();
            fd.append('_token', csrf);
            fd.append('handling_status', status);

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (res.ok) {
                    location.reload();
                } else {
                    alert('Gagal mengupdate status penanganan');
                }
            } catch (err) {
                alert('Error: ' + (err.message || 'Gagal mengupdate status'));
            }
        }

        // Hot toggle needs_attention without full reload
        (function initAttentionToggle() {
            const sw = document.getElementById('attention-switch');
            if (!sw) return;
            sw.addEventListener('change', async function() {
                const needs = sw.checked ? '1' : '0';
                const analisisId = {{ $analisis->id }};
                const url = `${location.origin}/guru/analisis/${analisisId}/attention`;
                const fd = new FormData();
                fd.append('_token', csrf);
                fd.append('needs_attention', needs);
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    if (!res.ok) {
                        throw new Error('Gagal menyimpan');
                    }
                    const data = await res.json();
                    const statusSpan = document.getElementById('attention-status');
                    const handlingBlock = document.getElementById('handling-block');
                    const actionsBlock = document.getElementById('attention-actions-block');
                    if (data.needs_attention) {
                        statusSpan.classList.remove('text-muted');
                        statusSpan.classList.add('text-danger');
                        statusSpan.textContent = 'Butuh perhatian';
                        if (handlingBlock) handlingBlock.style.display = '';
                        if (actionsBlock) actionsBlock.classList.remove('d-none');
                    } else {
                        statusSpan.classList.remove('text-danger');
                        statusSpan.classList.add('text-muted');
                        statusSpan.textContent = 'Normal';
                        if (handlingBlock) handlingBlock.style.display = 'none';
                        if (actionsBlock) actionsBlock.classList.add('d-none');
                    }
                    document.getElementById('attention-input').value = data.needs_attention ? '1' : '0';
                } catch (err) {
                    alert(err.message || 'Gagal memperbarui status');
                    // revert checkbox
                    sw.checked = !sw.checked;
                }
            });
        })();

        // Auto-finalize on page leave
        (function() {
            window.__accepted = window.__accepted || false;
            window.__finalized = false;

            const finalize = () => {
                if (window.__finalized) return;
                if (!window.__accepted) return;
                window.__finalized = true;

                try {
                    const analisisId = {{ $analisis->id }};
                    const url = `${location.origin}/guru/analisis/${analisisId}/finalize`;
                    const data = new FormData();
                    data.append('_token', csrf);

                    fetch(url, {
                        method: 'POST',
                        body: data,
                        keepalive: true,
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                } catch (e) {}
            };

            window.addEventListener('beforeunload', finalize);
            window.addEventListener('pagehide', finalize);
        })();

        // (Duplicate attention toggle listener removed)
    </script>
@endpush
