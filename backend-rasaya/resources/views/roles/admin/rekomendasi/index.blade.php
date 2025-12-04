@extends('layouts.admin')

@section('title', 'Manajemen Rekomendasi')

@section('page-header')
    <div>
        <h3 class="mb-1">Manajemen Rekomendasi</h3>
    <div class="text-muted">Kelola rekomendasi tindakan; bisa filter berdasarkan kategori di kanan.</div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <form method="GET" action="" class="d-flex gap-2">
            <select name="kategori_id" class="form-select form-select-sm" style="width:260px" onchange="this.form.submit()">
                <option value="">— Pilih Kategori —</option>
                <option value="-1" {{ request('kategori_id') === '-1' ? 'selected' : '' }}>🔴 Tidak memiliki kategori</option>
                @foreach ($kategoris as $k)
                    <option value="{{ $k->id }}" {{ optional($selectedKategori)->id === $k->id ? 'selected' : '' }}>
                        [{{ $k->kode }}] {{ $k->nama }}
                    </option>
                @endforeach
            </select>
            <a href="{{ route('admin.rekomendasi.requests') }}" class="btn btn-sm btn-info">📥 Request Tambah Rekomendasi</a>
            <button class="btn btn-primary" type="button" onclick="openCreate()">+ Tambah Rekomendasi</button>
        </form>
    </div>
@endsection

@section('content')
    @if (true)
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:64px">#</th>
                                <th>Kode</th>
                                <th>Judul</th>
                                <th>Deskripsi</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th style="width:230px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="rows">
                            @forelse ($rows as $i => $r)
                                <tr data-id="{{ $r->id }}" data-rules='@json($r->rules)' data-kategori-id="{{ $r->kategoris->first()?->id ?? '' }}">
                                    <td>{{ $rows->firstItem() + $i }}</td>
                                    <td class="td-kode">{{ $r->kode }}</td>
                                    <td class="td-judul">{{ $r->judul }}</td>
                                    <td class="td-deskripsi">{{ $r->deskripsi ?? '—' }}</td>
                                    <td class="td-severity text-capitalize">{{ $r->severity }}</td>
                                    <td class="td-is_active">
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input toggle-active" type="checkbox" role="switch"
                                                data-id="{{ $r->id }}" {{ $r->is_active ? 'checked' : '' }}>
                                        </div>
                                    </td>
                                    <td class="actions">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="openDetail({{ $r->id }})">Detail</button>
                                            <button class="btn btn-outline-secondary" onclick="openEdit({{ $r->id }})">Edit</button>
                                            <button class="btn btn-outline-danger" onclick="doDelete({{ $r->id }})">Hapus</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Belum ada rekomendasi untuk kategori ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3">
            {{ $rows->withQueryString()->links() }}
        </div>
    @endif

    {{-- Modal --}}
    <div class="modal fade" id="modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="m-title" class="modal-title">Form Rekomendasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="m-form" onsubmit="submitForm(event)">
                        @csrf
                        <input type="hidden" id="m-id">
                        <div class="row g-3 mb-1">
                            <div class="col-md-6">
                                <label class="form-label">Kategori</label>
                                <input type="text" id="m-kategori-search" class="form-control mb-2" placeholder="Cari kategori..." onkeyup="filterKategori()">
                                <select id="m-kategori" class="form-select" required onchange="onKategoriChange()" size="5" style="height:auto">
                                    <option value="">— Pilih Kategori —</option>
                                    @foreach ($kategoris as $k)
                                        <option value="{{ $k->id }}" data-kode="{{ $k->kode }}" data-nama="[{{ $k->kode }}] {{ $k->nama }}" {{ optional($selectedKategori)->id === $k->id ? 'selected' : '' }}>
                                            [{{ $k->kode }}] {{ $k->nama }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kode</label>
                                <div class="input-group">
                                    <input id="m-kode" class="form-control" maxlength="100" placeholder="AKD_01" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="fillSuggestKode()">Saran</button>
                                </div>
                                <div class="form-text">Otomatis mengikuti kategori (format: <code>KODE-KATEGORI_ke-N</code>).</div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Judul</label>
                                <input id="m-judul" class="form-control" maxlength="255" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Deskripsi<span class="text-danger">*</span></label>
                                <textarea id="m-deskripsi" class="form-control" rows="2" required></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Severity</label>
                                <select id="m-severity" class="form-select" required>
                                    <option value="low">low</option>
                                    <option value="medium">medium</option>
                                    <option value="high">high</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tingkat Keparahan</label>
                                <input type="range" min="-1.00" max="0.00" step="0.01" id="m-min_score" class="form-range" oninput="updateMinScoreLabel()">
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>-1.00 (berat)</span>
                                    <span>-0.50</span>
                                    <span>0.00 (ringan)</span>
                                </div>
                                <div class="form-text" id="min-score-help">
                                    Nilai minimal sentimen: <strong id="m-min_value">-0.05</strong>
                                    <div class="mt-1">Semakin mendekati -1.00, kondisinya makin berat.</div>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="m-active" checked>
                                    <label class="form-check-label" for="m-active">Aktif</label>
                                </div>
                            </div>
                        </div>

                        <pre id="m-error" class="text-danger small mt-3 mb-0" style="white-space:pre-wrap"></pre>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" form="m-form" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail Modal --}}
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Rekomendasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="detailContent">
                        <div class="text-muted">Memuat…</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const base = '/admin/rekomendasi';
    const kategoriIdFilter = {{ optional($selectedKategori)->id ?? 'null' }};
        const modalEl = document.getElementById('modal');
        const detailModalEl = document.getElementById('detailModal');
        let bsModal, bsDetailModal;

        document.addEventListener('DOMContentLoaded', () => {
            bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
            bsDetailModal = new bootstrap.Modal(detailModalEl, { backdrop: 'static' });

            document.querySelectorAll('.toggle-active').forEach(el => {
                el.addEventListener('change', async (e) => {
                    const id = e.target.dataset.id;
                    const is_active = e.target.checked ? 1 : 0;
                    const res = await fetch(`${base}/${id}/active`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body: JSON.stringify({ is_active })
                    });
                    if (!res.ok) {
                        alert('Gagal mengubah status. Coba lagi.');
                        e.target.checked = !e.target.checked;
                    }
                });
            });
        });

        function openCreate() {
            document.getElementById('m-title').innerText = 'Tambah Rekomendasi';
            document.getElementById('m-id').value = '';
            document.getElementById('m-kode').value = '';
            document.getElementById('m-judul').value = '';
            document.getElementById('m-deskripsi').value = '';
            document.getElementById('m-severity').value = 'low';
            document.getElementById('m-active').checked = true;
            // default suggestion from severity
            document.getElementById('m-min_score').value = sevToScore(document.getElementById('m-severity').value);
            updateMinScoreLabel();
            // no keywords in rekomendasi
            document.getElementById('m-error').innerText = '';
            // Preselect kategori by filter if available
            if (kategoriIdFilter) {
                document.getElementById('m-kategori').value = kategoriIdFilter;
            } else {
                document.getElementById('m-kategori').value = '';
            }
            fillSuggestKode();
            bsModal.show();
        }

        function openEdit(id) {
            const tr = document.querySelector(`tr[data-id="${id}"]`);
            document.getElementById('m-title').innerText = 'Edit Rekomendasi';
            document.getElementById('m-id').value = id;
            document.getElementById('m-kode').value = tr.querySelector('.td-kode').innerText.trim();
            document.getElementById('m-judul').value = tr.querySelector('.td-judul').innerText.trim();
            const desc = tr.querySelector('.td-deskripsi').innerText.trim();
            document.getElementById('m-deskripsi').value = (desc === '—' ? '' : desc);
            document.getElementById('m-severity').value = tr.querySelector('.td-severity').innerText.trim().toLowerCase();
            document.getElementById('m-active').checked = tr.querySelector('.toggle-active').checked;
            const rules = JSON.parse(tr.dataset.rules || '{}');
            const current = (typeof rules.min_neg_score === 'number') ? rules.min_neg_score : null;
            document.getElementById('m-min_score').value = (current !== null ? current : sevToScore(document.getElementById('m-severity').value));
            updateMinScoreLabel();
            // Load kategori if available
            const kategoriId = tr.dataset.kategoriId;
            if (kategoriId) {
                document.getElementById('m-kategori').value = kategoriId;
            }
            // no keywords in rekomendasi
            document.getElementById('m-error').innerText = '';
            bsModal.show();
        }

        function onKategoriChange() { fillSuggestKode(); }

        async function fillSuggestKode() {
            const kid = document.getElementById('m-kategori').value;
            if (!kid) { document.getElementById('m-kode').value = ''; return; }
            const res = await fetch(`${base}/suggest-kode?kategori_id=${kid}`);
            const data = await res.json().catch(() => ({}));
            if (data && data.ok) document.getElementById('m-kode').value = data.kode;
        }

        async function submitForm(e) {
            e.preventDefault();
            const kid = document.getElementById('m-kategori').value;
            if (!kid) { alert('Pilih kategori terlebih dahulu.'); return; }
            const id = document.getElementById('m-id').value;
            const payload = {
                kode: document.getElementById('m-kode').value.trim() || null,
                judul: document.getElementById('m-judul').value.trim(),
                deskripsi: document.getElementById('m-deskripsi').value.trim() || null,
                severity: document.getElementById('m-severity').value,
                is_active: document.getElementById('m-active').checked ? 1 : 0,
                min_neg_score: parseFloat(document.getElementById('m-min_score').value),
                kategori_id: parseInt(kid), // Include kategori_id in payload for update
                // no keywords in rekomendasi
            };
            const url = id ? `${base}/${id}` : `${base}?kategori_id=${kid}`;
            const res = await fetch(url, {
                method: id ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                document.getElementById('m-error').innerText = JSON.stringify(data.errors ?? data, null, 2);
                return;
            }
            bsModal.hide();
            location.reload();
        }

        async function doDelete(id) {
            if (!confirm('Hapus rekomendasi dari kategori ini?')) return;
            let url = `${base}/${id}`;
            if (kategoriIdFilter) url += `?kategori_id=${kategoriIdFilter}`;
            const res = await fetch(url, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
            });
            if (res.ok) location.reload();
        }

        // Guidance text made simple and line-by-line for non-technical admins
        document.getElementById('m-severity').addEventListener('change', () => {
            // Suggest a recommended minimum based on severity but allow free adjustment
            document.getElementById('m-min_score').value = sevToScore(document.getElementById('m-severity').value);
            updateMinScoreLabel();
        });
        function sevToScore(sev){
            if (sev === 'high') return -0.30;
            if (sev === 'medium') return -0.15;
            return -0.05;
        }
        function updateMinScoreLabel(){
            const val = parseFloat(document.getElementById('m-min_score').value);
            document.getElementById('m-min_value').textContent = isNaN(val) ? '-' : val.toFixed(2);
        }

        function filterKategori() {
            const search = document.getElementById('m-kategori-search').value.toLowerCase();
            const select = document.getElementById('m-kategori');
            const options = select.querySelectorAll('option');
            options.forEach(opt => {
                if (opt.value === '') {
                    opt.style.display = '';
                    return;
                }
                const text = opt.getAttribute('data-nama') || opt.textContent;
                opt.style.display = text.toLowerCase().includes(search) ? '' : 'none';
            });
        }

        async function openDetail(id) {
            const content = document.getElementById('detailContent');
            content.innerHTML = '<div class="text-muted">Memuat…</div>';
            bsDetailModal.show();
            
            try {
                const res = await fetch(`${base}/${id}/detail`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (!data.ok) throw new Error('Gagal memuat');
                
                const rek = data.rekomendasi;
                const severity = rek.severity || 'low';
                const minScore = (typeof rek.rules?.min_neg_score === 'number') ? rek.rules.min_neg_score.toFixed(2) : '-';
                
                // Build kategori list
                const kategoriList = (rek.kategoris || []).map(k => {
                    const topikBesar = k.topik_besar?.length ? k.topik_besar.map(tb => `<span class="badge bg-light text-dark border me-1">${tb.nama}</span>`).join('') : '<span class="text-muted">—</span>';
                    return `<li><code>[${k.kode}]</code> ${k.nama} <br><small class="text-muted">Topik Besar: ${topikBesar}</small></li>`;
                }).join('') || '<li class="text-muted">(Tidak terkait kategori)</li>';
                
                content.innerHTML = `
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="mb-2">Informasi Dasar</h6>
                            <div><strong>Kode:</strong> <code>${rek.kode}</code></div>
                            <div><strong>Judul:</strong> ${rek.judul}</div>
                            <div><strong>Severity:</strong> <span class="badge bg-${severity === 'high' ? 'danger' : severity === 'medium' ? 'warning' : 'info'}">${severity}</span></div>
                            <div><strong>Status:</strong> <span class="badge ${rek.is_active ? 'bg-success' : 'bg-secondary'}">${rek.is_active ? 'Aktif' : 'Nonaktif'}</span></div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-2">Rules</h6>
                            <div><strong>Min Neg Score:</strong> ${minScore}</div>
                            <h6 class="mb-2 mt-3">Deskripsi</h6>
                            <p class="mb-0">${rek.deskripsi || '<em class="text-muted">Tidak ada deskripsi</em>'}</p>
                        </div>
                        <div class="col-12">
                            <h6 class="mb-2">Kategori Terkait</h6>
                            <ul class="mb-0">${kategoriList}</ul>
                        </div>
                    </div>
                `;
            } catch (e) {
                content.innerHTML = '<div class="text-danger">Gagal memuat detail.</div>';
            }
        }
    </script>
@endpush
