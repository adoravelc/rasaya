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
                @foreach ($kategoris as $k)
                    <option value="{{ $k->id }}" {{ optional($selectedKategori)->id === $k->id ? 'selected' : '' }}>
                        [{{ $k->kode }}] {{ $k->nama }}
                    </option>
                @endforeach
            </select>
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
                                <th style="width:180px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="rows">
                            @forelse ($rows as $i => $r)
                                <tr data-id="{{ $r->id }}" data-rules='@json($r->rules)'>
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
                                <select id="m-kategori" class="form-select" required onchange="onKategoriChange()">
                                    <option value="">— Pilih Kategori —</option>
                                    @foreach ($kategoris as $k)
                                        <option value="{{ $k->id }}" data-kode="{{ $k->kode }}" {{ optional($selectedKategori)->id === $k->id ? 'selected' : '' }}>
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
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="m-active" checked>
                                    <label class="form-check-label" for="m-active">Aktif</label>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Score Sentimen Minimal</label>
                                <input type="number" step="0.01" id="m-min_neg_score" class="form-control" placeholder="-0.05">
                                <div class="form-text" id="min-score-help">
                                    Saran awal: <strong>-0.05 (Ringan)</strong>
                                    <ul class="mb-1 mt-1">
                                        <li>Ringan = -0.05 hingga -0.14</li>
                                        <li>Sedang = -0.15 hingga -0.29</li>
                                        <li>Berat = ≤ -0.30</li>
                                    </ul>
                                    Rekomendasi akan muncul jika kondisi siswa sama atau lebih berat dari nilai ini. Semakin mendekati -1.00, kondisinya makin berat.
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Kata kunci dalam input</label>
                                <input id="m-any_keywords" class="form-control" placeholder="pisahkan dengan koma, mis: tugas, ujian, nilai">
                                <div class="form-text">Jika salah satu kata kunci muncul pada teks input, rekomendasi ini lebih relevan.</div>
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
@endsection

@push('scripts')
    <script>
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const base = '/admin/rekomendasi';
    const kategoriIdFilter = {{ optional($selectedKategori)->id ?? 'null' }};
        const modalEl = document.getElementById('modal');
        let bsModal;

        document.addEventListener('DOMContentLoaded', () => {
            bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static' });

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
            document.getElementById('m-min_neg_score').value = '';
            document.getElementById('m-any_keywords').value = '';
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
            document.getElementById('m-min_neg_score').value = rules.min_neg_score ?? '';
            document.getElementById('m-any_keywords').value = Array.isArray(rules.any_keywords) ? rules.any_keywords.join(', ') : '';
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
                min_neg_score: document.getElementById('m-min_neg_score').value || null,
                any_keywords: document.getElementById('m-any_keywords').value.trim(),
            };
            const url = id ? `${base}/${id}?kategori_id=${kid}` : `${base}?kategori_id=${kid}`;
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
            const sev = document.getElementById('m-severity').value;
            const help = document.getElementById('min-score-help');
            let saran = '';
            if (sev === 'low') saran = '-0.05 (Ringan)';
            if (sev === 'medium') saran = '-0.15 (Sedang)';
            if (sev === 'high') saran = '-0.30 (Berat)';
            help.innerHTML = `Saran untuk pilihan ini: <strong>${saran}</strong>
                <ul class="mb-1 mt-1">
                    <li>Ringan = -0.05 hingga -0.14</li>
                    <li>Sedang = -0.15 hingga -0.29</li>
                    <li>Berat = ≤ -0.30</li>
                </ul>
                Rekomendasi akan muncul jika kondisi siswa sama atau lebih berat dari nilai ini. Semakin mendekati -1.00, kondisinya makin berat.`;
        });
    </script>
@endpush
