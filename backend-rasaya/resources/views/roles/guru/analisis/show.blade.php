@extends('layouts.guru')
@section('title', 'Hasil Analisis')

@section('content')
    <div class="container py-4">
        <a href="{{ route('guru.analisis.index') }}" class="btn btn-sm btn-outline-secondary mb-3">← Kembali</a>

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                        <div class="small text-muted">Siswa</div>
                        <div class="fw-semibold">
                            {{ optional($analisis->siswaKelas->siswa->user)->name }}
                            <span class="text-muted">({{ optional($analisis->siswaKelas->siswa->user)->identifier }})</span>
                        </div>
                        @unless ($isWali)
                            <div class="small">
                                Kelas: <strong>{{ optional($analisis->siswaKelas->kelas)->label }}</strong>
                            </div>
                        @endunless
                    </div>
                </div>

                <h5 class="mb-1">Ringkasan</h5>
                <div class="mb-2">
                    <form id="attention-form" class="d-inline-flex align-items-center gap-2" method="post" action="{{ route('guru.analisis.attention', $analisis->id) }}" onsubmit="return false;">
                        @csrf
                        <input type="hidden" name="needs_attention" id="attention-input" value="{{ $analisis->needs_attention ? '1' : '0' }}">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="attention-switch" {{ $analisis->needs_attention ? 'checked' : '' }}>
                            <label class="form-check-label" for="attention-switch">Tandai perlu perhatian</label>
                        </div>
                        <span id="attention-status" class="small {{ $analisis->needs_attention ? 'text-danger' : 'text-muted' }}">
                            {{ $analisis->needs_attention ? 'Butuh perhatian' : 'Normal' }}
                        </span>
                    </form>
                </div>
                <div class="text-muted small mb-2">
                    Rentang: {{ \Illuminate\Support\Carbon::parse($analisis->tanggal_awal_proses)->toDateString() }} —
                    {{ \Illuminate\Support\Carbon::parse($analisis->tanggal_akhir_proses)->toDateString() }}
                </div>
                <div>Skor Sentimen Rata-rata: <strong>{{ $analisis->skor_sentimen }}</strong>
                    <span class="d-block small text-muted">{{ $sentimenDesc }}</span>
                    <span class="d-block small text-muted fst-italic">{{ $sentimenScaleInfo }}</span>
                </div>
                <div class="mt-2">Skor Mood Rata-rata: <strong>{{ $avgMood ?? $analisis->avg_mood }}</strong>
                    <span class="d-block small text-muted">{{ $moodDesc }}</span>
                    <span class="d-block small text-muted fst-italic">{{ $moodScaleInfo }}</span>
                </div>
                @if(($topEmojis ?? collect())->isNotEmpty())
                    <div class="mt-2">
                        <span class="text-muted">Top Emojis:</span>
                        @foreach ($topEmojis as $e)
                            <span class="badge text-bg-light" title="skor {{ $e['skor'] }}">{{ $e['emoji'] }} <small class="text-muted">×{{ $e['count'] }}</small></span>
                        @endforeach
                    </div>
                @endif
                <div class="mt-2">
                    <span class="text-muted">Kata kunci:</span>
                    @foreach ($analisis->kata_kunci ?? [] as $kw)
                        <span class="badge text-bg-light">{{ $kw['term'] ?? '' }} <small
                                class="text-muted">×{{ $kw['count'] ?? 1 }}</small></span>
                    @endforeach
                </div>
                @if (!empty($analisis->categories_overview))
                    <div class="mt-2">
                        <span class="text-muted">Kategori Sistem (ranking):</span>
                        @foreach (($analisis->categories_overview ?? []) as $co)
                            <span class="badge text-bg-secondary">{{ $co['category'] ?? '-' }}
                                <small class="text-muted">{{ isset($co['score']) ? number_format($co['score'] * 100, 1) . '%' : '' }}</small>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        @if (!empty($analisis->summary) || !empty($analisis->auto_summary))
            <div class="mt-3">
                <h6>Catatan Sistem</h6>
                @if (!empty($analisis->summary))
                    <p class="text-muted small mb-1">{{ $analisis->summary['notes'] ?? '—' }}</p>
                @endif
                @if (!empty($analisis->auto_summary))
                    <div class="small">Kesimpulan Otomatis: <span class="text-muted">{{ $analisis->auto_summary }}</span></div>
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

        @if (!empty($analisis->clusters))
            <div class="mt-4">
                <h5 class="mb-2">Hasil Klasterisasi (Data Negatif)</h5>
                <div class="accordion" id="clusterAccordion">
                    @foreach ($analisis->clusters as $ci => $cl)
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading{{ $ci }}">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapse{{ $ci }}" aria-expanded="false"
                                    aria-controls="collapse{{ $ci }}">
                                    Klaster {{ $ci + 1 }} — Topik:
                                    <span class="ms-1 text-primary small">
                                        {{ implode(', ', array_slice($cl['top_terms'] ?? [], 0, 3)) }}
                                    </span>
                                </button>
                            </h2>
                            <div id="collapse{{ $ci }}" class="accordion-collapse collapse"
                                aria-labelledby="heading{{ $ci }}" data-bs-parent="#clusterAccordion">
                                <div class="accordion-body small">
                                    <div class="mb-2">
                                        <strong>Top Terms:</strong>
                                        @foreach ($cl['top_terms'] ?? [] as $term)
                                            <span class="badge text-bg-light">{{ $term }}</span>
                                        @endforeach
                                    </div>
                                    <div>
                                        <strong>Contoh Teks:</strong>
                                        <ul class="small text-muted mb-0">
                                            @foreach ($cl['examples'] ?? [] as $ex)
                                                <li>{{ $ex }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif


        <div class="card">
            <div class="card-header"><strong>Rekomendasi Sistem</strong></div>
            <div class="list-group list-group-flush">
                @forelse($analisis->rekomendasis as $r)
                    <div class="list-group-item d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">
                                <a href="javascript:void(0)" onclick="openRekomDetail({{ $analisis->id }}, {{ $r->id }})">{{ $r->judul }}</a>
                            </div>
                            <div class="small text-muted">Klik judul untuk lihat detail</div>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted mb-1">Status: <strong>{{ $r->status }}</strong></div>
                            @if ($r->status === 'suggested')
                                <form class="d-inline" method="post" action="{{ route('guru.analisis.decide', [$analisis->id, $r->id]) }}" onsubmit="window.__accepted = true;">
                                    @csrf
                                    <input type="hidden" name="action" value="accept">
                                    <button class="btn btn-sm btn-success">Terima</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="openReject({{ $analisis->id }}, {{ $r->id }})">Tolak</button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-muted">Belum ada rekomendasi.</div>
                @endforelse
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Semua Input dalam Rentang</strong>
                <span class="small text-muted">
                    Total:
                    {{ ($refleksisSelf->count() ?? 0) + ($friendReports->count() ?? 0) + ($guruNotes->count() ?? 0) }} item
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <h6 class="mb-2">Refleksi Diri <span
                                class="badge text-bg-secondary">{{ $refleksisSelf->count() ?? 0 }}</span></h6>
                        <div class="col-md-4">
                            @forelse(($refleksisSelf ?? collect()) as $it)
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                    @php
                                    $k = strtolower(trim((string)($it->kondisi_siswa ?? '')));
                                    if ($k === 'grey') { $k = 'gray'; }
                                        $pal = [
                                            'green'  => ['bg' => '#c9f2da', 'bd' => '#198754'],
                                            'yellow' => ['bg' => '#ffefb3', 'bd' => '#ffc107'],
                                            'orange' => ['bg' => '#ffd8b0', 'bd' => '#fd7e14'],
                                            'red'    => ['bg' => '#ffc9cf', 'bd' => '#dc3545'],
                                            'blue'   => ['bg' => '#cfe7ff', 'bd' => '#0d6efd'],
                                            'gray'   => ['bg' => '#f1f3f5', 'bd' => '#6c757d'],
                                        ];
                                        $c = $pal[$k] ?? $pal['gray'];
                                    @endphp
                                    <div class="list-group-item" style="background-color: {{ $c['bg'] }}; border-left: 4px solid {{ $c['bd'] }};">
                                            {{ \Illuminate\Support\Carbon::parse($it->tanggal)->toDateString() }}</div>
                                        @if (!empty($it->avg_emosi))
                                            <div class="small">Mood:
                                                <strong>{{ number_format($it->avg_emosi, 2) }}</strong></div>
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
                                                <strong>{{ $it->siswaKelas->siswa->user->name }}</strong></div>
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
                                    $k = strtolower(trim((string)($it->kondisi_siswa ?? '')));
                                    // align with observasi index mapping
                                    if ($k === 'aman') { $k = 'green'; }
                                    if ($k === 'gray') { $k = 'grey'; }
                                    $pal = [
                                        'green'  => ['bg' => '#dcfce7', 'bd' => '#16a34a'],
                                        'yellow' => ['bg' => '#fef9c3', 'bd' => '#f59e0b'],
                                        'orange' => ['bg' => '#ffedd5', 'bd' => '#fb923c'],
                                        'red'    => ['bg' => '#fee2e2', 'bd' => '#ef4444'],
                                        'black'  => ['bg' => '#e5e7eb', 'bd' => '#111827'],
                                        'grey'   => ['bg' => '#f3f4f6', 'bd' => '#9ca3af'],
                                    ];
                                    $c = $pal[$k] ?? $pal['grey'];
                                @endphp
                                <div class="list-group-item" style="background-color: {{ $c['bg'] }}; border-left: 4px solid {{ $c['bd'] }};">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="small text-muted">
                                            {{ \Illuminate\Support\Carbon::parse($it->tanggal)->toDateString() }}</div>
                                        <div class="small d-flex align-items-center">
                                            <span class="rounded-circle me-1" style="display:inline-block;width:10px;height:10px;background: {{ $c['bd'] }}; border:1px solid #cbd5e1"></span>
                                            <span>Kondisi: <strong>{{ strtoupper($it->kondisi_siswa ?? '-') }}</strong></span>
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
                        <div class="form-text">Rekomendasi muncul jika skor sentimen siswa sama atau lebih rendah dari nilai ini (lebih negatif).</div>
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
                            @foreach(($kategoris ?? collect()) as $k)
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
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let rejModal;
    let rekomDetailModal;
    document.addEventListener('DOMContentLoaded', ()=>{
        rejModal = new bootstrap.Modal(document.getElementById('rejectModal'), {backdrop:'static'});
        rekomDetailModal = new bootstrap.Modal(document.getElementById('rekomDetailModal'), {backdrop:'static'});
        const sel = document.getElementById('rej-kategori');
        if (sel) sel.addEventListener('change', loadAlternatives);
    });

    async function openRekomDetail(analisisId, rekomId){
        try{
            // reset content
            document.getElementById('rekomDetailTitle').textContent = 'Detail Rekomendasi';
            document.getElementById('rekomDetailJudul').textContent = '—';
            document.getElementById('rekomDetailDeskripsi').textContent = '—';
            document.getElementById('rekomDetailSeverity').textContent = '—';
            document.getElementById('rekomDetailMinScore').textContent = '—';

            const url = `${location.origin}/guru/analisis/${analisisId}/rekomendasi/${rekomId}`;
            const res = await fetch(url, { headers: { 'Accept':'application/json' } });
            if(!res.ok) throw new Error('Gagal memuat detail');
            const data = await res.json();
            document.getElementById('rekomDetailTitle').textContent = data.judul || 'Detail Rekomendasi';
            document.getElementById('rekomDetailJudul').textContent = data.judul || '—';
            document.getElementById('rekomDetailDeskripsi').textContent = data.deskripsi || '—';
            document.getElementById('rekomDetailSeverity').textContent = (data.severity || '—');
            const ms = (typeof data.min_neg_score === 'number') ? data.min_neg_score : null;
            document.getElementById('rekomDetailMinScore').textContent = (ms !== null) ? ms.toFixed(2) : '—';
            rekomDetailModal.show();
        }catch(err){
            alert(err.message || 'Gagal memuat detail rekomendasi');
        }
    }

    function openReject(analisisId, rekomId){
        document.getElementById('rej-analisis-id').value = analisisId;
        document.getElementById('rej-rekom-id').value = rekomId;
        document.getElementById('rej-kategori').value = '';
        document.getElementById('rej-alt-list').innerHTML = '';
        document.getElementById('rej-alt-container').classList.add('d-none');
        document.getElementById('rej-error').innerText = '';
        rejModal.show();
    }

    async function loadAlternatives(){
        const analisisId = document.getElementById('rej-analisis-id').value;
        const rekomId = document.getElementById('rej-rekom-id').value;
        const kategoriId = document.getElementById('rej-kategori').value;
        document.getElementById('rej-error').innerText = '';
        document.getElementById('rej-alt-list').innerHTML = '';
        document.getElementById('rej-alt-container').classList.add('d-none');
        if(!kategoriId) return;
        try{
            const url = `${location.origin}/guru/analisis/${analisisId}/rekomendasi/${rekomId}/alternatives?kategori_id=${encodeURIComponent(kategoriId)}`;
            const res = await fetch(url, { headers: { 'Accept':'application/json' } });
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
        }catch(err){
            document.getElementById('rej-error').innerText = err.message || 'Gagal memuat alternatif';
        }
    }

    async function submitReject(){
        const analisisId = document.getElementById('rej-analisis-id').value;
        const rekomId = document.getElementById('rej-rekom-id').value;
        const kategoriId = document.getElementById('rej-kategori').value;
        const alt = document.querySelector('input[name="rej-alt"]:checked');
        const altId = alt ? alt.value : '';
        document.getElementById('rej-error').innerText = '';
        if(!kategoriId || !altId){
            document.getElementById('rej-error').innerText = 'Pilih kategori dan salah satu rekomendasi alternatif.';
            return;
        }
        const fd = new FormData();
        fd.append('action', 'reject');
        fd.append('kategori_id', kategoriId);
        fd.append('selected_master_rekomendasi_id', altId);

        try{
            const res = await fetch(`${location.origin}/guru/analisis/${analisisId}/rekomendasi/${rekomId}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept':'application/json' },
                body: fd
            });
            if (res.ok){
                rejModal.hide();
                location.reload();
                return;
            }
            const data = await res.json().catch(()=>({}));
            document.getElementById('rej-error').innerText = (data.message || '') + ' ' + (data.errors ? JSON.stringify(data.errors) : '');
        }catch(err){
            document.getElementById('rej-error').innerText = err.message || 'Gagal menyimpan';
        }
    }

    // Auto-finalize on page leave: reject remaining suggestions when user leaves the page
    (function(){
        window.__accepted = window.__accepted || false;
        window.__finalized = false;
        const finalize = () => {
            if (window.__finalized) return;
            if (!window.__accepted) return; // only if there was at least one acceptance
            window.__finalized = true;
            try {
                const analisisId = {{ $analisis->id }};
                const url = `${location.origin}/guru/analisis/${analisisId}/finalize`;
                const data = new FormData();
                data.append('_token', csrf);
                // keepalive to allow sending during unload
                fetch(url, { method: 'POST', body: data, keepalive: true, headers: { 'Accept':'application/json' } });
            } catch (e) {}
        };
        window.addEventListener('beforeunload', finalize);
        // also when clicking back button
        window.addEventListener('pagehide', finalize);
    })();

    // Toggle needs_attention via fetch when switch changes
    (function(){
        const sw = document.getElementById('attention-switch');
        if (!sw) return;
        sw.addEventListener('change', async (e)=>{
            const checked = !!e.target.checked;
            const analisisId = {{ $analisis->id }};
            const url = `${location.origin}/guru/analisis/${analisisId}/attention`;
            const fd = new FormData();
            fd.append('_token', csrf);
            fd.append('needs_attention', checked ? '1' : '0');
            try{
                const res = await fetch(url, { method: 'POST', body: fd, headers: { 'Accept':'application/json' } });
                if (res.ok){
                    const data = await res.json().catch(()=>({}));
                    const txt = document.getElementById('attention-status');
                    if (txt){
                        const on = !!(data.needs_attention ?? checked);
                        txt.textContent = on ? 'Butuh perhatian' : 'Normal';
                        txt.classList.toggle('text-danger', on);
                        txt.classList.toggle('text-muted', !on);
                    }
                }
            }catch(err){ /* ignore */ }
        })
    })();
</script>
@endpush
