@extends('layouts.admin')

@section('title', 'Manajemen Kategori')

@section('page-header')
    <div>
        <h3 class="mb-1">Manajemen Kategori</h3>
        <div class="text-muted">Kelola kode, nama, deskripsi, dan status aktif.</div>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <select class="form-select form-select-sm" id="view-mode" onchange="switchView()" style="width:200px">
            <option value="small" selected>Kategori Kecil</option>
            <option value="big">Kategori Besar</option>
        </select>
        <input type="text" id="q" class="form-control form-control-sm" placeholder="Cari kode/nama…"
            value="{{ $qTerm ?? '' }}" style="width:220px" onkeydown="if(event.key==='Enter'){applyFilters()}">
        <select class="form-select form-select-sm" id="f-aktif" onchange="applyFilters()" style="width:180px">
            <option value="" {{ request('aktif') === null ? 'selected' : '' }}>Semua status</option>
            <option value="1" {{ request('aktif') === '1' ? 'selected' : '' }}>Aktif</option>
            <option value="0" {{ request('aktif') === '0' ? 'selected' : '' }}>Nonaktif</option>
        </select>
        <select class="form-select form-select-sm" id="f-master" onchange="applyFilters()" style="width:260px">
            <option value="">Semua Topik Besar</option>
            @isset($masters)
                @foreach ($masters as $m)
                    <option value="{{ $m->id }}" {{ (string) ($masterId ?? '') === (string) $m->id ? 'selected' : '' }}>
                        [{{ $m->kode }}] {{ $m->nama }}</option>
                @endforeach
            @endisset
        </select>
        <button class="btn btn-primary" id="btn-add" onclick="openCreateSmall()">+ Kategori Kecil</button>
    </div>
@endsection

@section('content')
    {{-- Tabel Kategori Kecil --}}
    <div class="card shadow-sm border-0" id="table-small">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:64px">#</th>
                            <th>Topik Besar</th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Status</th>
                            <th style="width:210px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rows-small">
                        @forelse ($rows as $i => $k)
                            <tr data-id="{{ $k->id }}" data-kind="small">
                                <td>{{ $rows->firstItem() + $i }}</td>
                                <td class="td-masters">
                                    @php($tops = $k->topikBesars ?? collect())
                                    @if ($tops->count())
                                        @foreach ($tops as $t)
                                            <span class="badge bg-light text-dark border me-1 mb-1" data-master-id="{{ $t->id }}">{{ $t->nama }}</span>
                                        @endforeach
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted" data-kode>{{ $k->kode }}</small>
                                </td>
                                <td>
                                    <span class="fw-bold" data-nama>{{ $k->nama }}</span>
                                    <span class="d-none" data-deskripsi>{{ $k->deskripsi }}</span>
                                </td>
                                <td class="td-is_active">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input toggle-active" type="checkbox" role="switch"
                                            data-id="{{ $k->id }}" {{ $k->is_active ? 'checked' : '' }}>
                                    </div>
                                </td>
                                <td class="actions">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="openDetail({{ $k->id }})">Detail</button>
                                        <button class="btn btn-outline-secondary" onclick="openEditSmall({{ $k->id }})">Edit</button>
                                        <button class="btn btn-outline-danger" onclick="doDelete({{ $k->id }})">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada data kategori kecil.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Tabel Kategori Besar --}}
    <div class="card shadow-sm border-0 d-none" id="table-big">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:64px">#</th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Deskripsi</th>
                            <th>Status</th>
                            <th style="width:210px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rows-big">
                        @isset($masters)
                            @foreach ($masters as $i => $m)
                                <tr data-id="{{ $m->id }}" data-kind="big">
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <code data-kode>{{ $m->kode }}</code>
                                    </td>
                                    <td>
                                        <span class="fw-bold" data-nama>{{ $m->nama }}</span>
                                    </td>
                                    <td>
                                        {{ Str::limit($m->deskripsi, 60) }}
                                        <span class="d-none" data-deskripsi>{{ $m->deskripsi }}</span>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input toggle-active-big" type="checkbox" role="switch"
                                                data-id="{{ $m->id }}" {{ $m->is_active ? 'checked' : '' }}>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-secondary" onclick="openEditBig({{ $m->id }})">Edit</button>
                                            <button class="btn btn-outline-danger" onclick="doDeleteBig({{ $m->id }})">Hapus</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada data kategori besar.</td>
                            </tr>
                        @endisset
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3" id="pagination-small">
        {{ $rows->withQueryString()->links() }}
    </div>
    <div class="mt-3 d-none" id="pagination-big"></div>

    {{-- Trashed --}}
    <div class="mt-4">
        <h5 class="mb-2">Terhapus (soft delete)</h5>
        @if ($trashed->isEmpty())
            <div class="text-muted">Tidak ada data terhapus.</div>
        @else
            <ul class="list-group" id="trashed">
                @foreach ($trashed as $t)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ $t->kode }} — {{ $t->nama }}
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-success"
                                onclick="restore({{ $t->id }})">Pulihkan</button>
                            <button class="btn btn-outline-danger" onclick="forceDel({{ $t->id }})">Hapus
                                Permanen</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Modal Kategori Kecil --}}
    <div class="modal fade" id="modalSmall" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="ms-title" class="modal-title">Form Kategori Kecil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="ms-form" onsubmit="submitFormSmall(event)">
                        @csrf
                        <input type="hidden" id="ms-id">
                        <div class="mb-3">
                            <label class="form-label">Topik Besar <span class="text-danger">*</span></label>
                            <select id="ms-master" class="form-select" required>
                                <option value="">Pilih Topik Besar…</option>
                                @isset($masters)
                                    @foreach ($masters as $m)
                                        <option value="{{ $m->id }}">[{{ $m->kode }}] {{ $m->nama }}</option>
                                    @endforeach
                                @endisset
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama <span class="text-danger">*</span></label>
                            <input id="ms-nama" class="form-control" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi (opsional)</label>
                            <textarea id="ms-deskripsi" class="form-control" rows="2" maxlength="255"></textarea>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="ms-active" checked>
                            <label class="form-check-label" for="ms-active">Aktif</label>
                        </div>
                        <pre id="ms-error" class="text-danger small mb-0" style="white-space:pre-wrap"></pre>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" form="ms-form" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Kategori Besar --}}
    <div class="modal fade" id="modalBig" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="mb-title" class="modal-title">Form Kategori Besar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="mb-form" onsubmit="submitFormBig(event)">
                        @csrf
                        <input type="hidden" id="mb-id">
                        <div class="mb-3">
                            <label class="form-label">Nama <span class="text-danger">*</span></label>
                            <input id="mb-nama" class="form-control" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi (opsional)</label>
                            <textarea id="mb-deskripsi" class="form-control" rows="2" maxlength="255"></textarea>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="mb-active" checked>
                            <label class="form-check-label" for="mb-active">Aktif</label>
                        </div>
                        <pre id="mb-error" class="text-danger small mb-0" style="white-space:pre-wrap"></pre>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" form="mb-form" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail Modal --}}
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Kategori</h5>
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
        const base = '/admin/kategori';
        const modalSmallEl = document.getElementById('modalSmall');
        const modalBigEl = document.getElementById('modalBig');
        const detailEl = document.getElementById('detailModal');
        let bsModalSmall, bsModalBig, bsDetailModal;

        function switchView() {
            const mode = document.getElementById('view-mode').value;
            const tableSmall = document.getElementById('table-small');
            const tableBig = document.getElementById('table-big');
            const paginationSmall = document.getElementById('pagination-small');
            const paginationBig = document.getElementById('pagination-big');
            const btnAdd = document.getElementById('btn-add');
            const filterMaster = document.getElementById('f-master');

            if (mode === 'big') {
                tableSmall.classList.add('d-none');
                tableBig.classList.remove('d-none');
                paginationSmall.classList.add('d-none');
                paginationBig.classList.remove('d-none');
                btnAdd.textContent = '+ Kategori Besar';
                btnAdd.onclick = openCreateBig;
                filterMaster.disabled = true;
            } else {
                tableSmall.classList.remove('d-none');
                tableBig.classList.add('d-none');
                paginationSmall.classList.remove('d-none');
                paginationBig.classList.add('d-none');
                btnAdd.textContent = '+ Kategori Kecil';
                btnAdd.onclick = openCreateSmall;
                filterMaster.disabled = false;
            }
        }

        function applyFilters() {
            const url = new URL(location.href);
            const aktif = document.getElementById('f-aktif').value;
            const master = document.getElementById('f-master').value;
            const q = document.getElementById('q').value.trim();
            if (aktif) url.searchParams.set('aktif', aktif);
            else url.searchParams.delete('aktif');
            if (master) url.searchParams.set('master_id', master);
            else url.searchParams.delete('master_id');
            if (q) url.searchParams.set('q', q);
            else url.searchParams.delete('q');
            location.href = url.toString();
        }

        document.addEventListener('DOMContentLoaded', () => {
            bsModalSmall = new bootstrap.Modal(modalSmallEl, { backdrop: 'static' });
            bsModalBig = new bootstrap.Modal(modalBigEl, { backdrop: 'static' });
            bsDetailModal = new bootstrap.Modal(detailEl, { backdrop: 'static' });

            // Toggle aktif kategori kecil
            document.querySelectorAll('.toggle-active').forEach(el => {
                el.addEventListener('change', async (e) => {
                    const id = e.target.dataset.id;
                    const is_active = e.target.checked ? 1 : 0;
                    const res = await fetch(`${base}/${id}/active`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ is_active })
                    });
                    if (!res.ok) {
                        alert('Gagal mengubah status. Coba lagi.');
                        e.target.checked = !e.target.checked;
                    }
                });
            });

            // Toggle aktif kategori besar
            document.querySelectorAll('.toggle-active-big').forEach(el => {
                el.addEventListener('change', async (e) => {
                    const id = e.target.dataset.id;
                    const is_active = e.target.checked ? 1 : 0;
                    const res = await fetch(`${base}/master/${id}/active`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ is_active })
                    });
                    if (!res.ok) {
                        alert('Gagal mengubah status. Coba lagi.');
                        e.target.checked = !e.target.checked;
                    }
                });
            });
        });

        // === KATEGORI KECIL ===
        function openCreateSmall() {
            document.getElementById('ms-title').innerText = 'Tambah Kategori Kecil';
            document.getElementById('ms-id').value = '';
            document.getElementById('ms-nama').value = '';
            document.getElementById('ms-deskripsi').value = '';
            document.getElementById('ms-active').checked = true;
            document.getElementById('ms-error').innerText = '';
            const currentMaster = document.getElementById('f-master')?.value || '';
            document.getElementById('ms-master').value = currentMaster;
            bsModalSmall.show();
        }

        function openEditSmall(id) {
            const row = document.querySelector(`tr[data-id="${id}"][data-kind="small"]`);
            if (!row) {
                alert('Data tidak ditemukan.');
                return;
            }
            const nama = row.querySelector('[data-nama]')?.textContent.trim() || '';
            const deskripsi = row.querySelector('span[data-deskripsi].d-none')?.textContent.trim() || '';
            const masterId = row.querySelector('.td-masters .badge[data-master-id]')?.dataset.masterId || '';
            const isActive = row.querySelector('.toggle-active')?.checked || false;

            document.getElementById('ms-title').innerText = 'Edit Kategori Kecil';
            document.getElementById('ms-id').value = id;
            document.getElementById('ms-nama').value = nama;
            document.getElementById('ms-deskripsi').value = deskripsi;
            document.getElementById('ms-master').value = masterId;
            document.getElementById('ms-active').checked = isActive;
            document.getElementById('ms-error').innerText = '';
            bsModalSmall.show();
        }

        async function submitFormSmall(e) {
            e.preventDefault();
            const id = document.getElementById('ms-id').value;
            const masterId = document.getElementById('ms-master').value;
            if (!masterId) {
                document.getElementById('ms-error').innerText = 'Pilih Topik Besar terlebih dahulu.';
                return;
            }
            const payload = {
                nama: document.getElementById('ms-nama').value.trim(),
                deskripsi: document.getElementById('ms-deskripsi').value.trim() || null,
                is_active: document.getElementById('ms-active').checked ? 1 : 0,
                master_id: masterId
            };
            const url = id ? `${base}/${id}` : base;
            const method = id ? 'PUT' : 'POST';
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                document.getElementById('ms-error').innerText = JSON.stringify(data.errors ?? data, null, 2);
                return;
            }
            bsModalSmall.hide();
            location.reload();
        }

        // === KATEGORI BESAR ===
        function openCreateBig() {
            document.getElementById('mb-title').innerText = 'Tambah Kategori Besar';
            document.getElementById('mb-id').value = '';
            document.getElementById('mb-nama').value = '';
            document.getElementById('mb-deskripsi').value = '';
            document.getElementById('mb-active').checked = true;
            document.getElementById('mb-error').innerText = '';
            bsModalBig.show();
        }

        function openEditBig(id) {
            const row = document.querySelector(`tr[data-id="${id}"][data-kind="big"]`);
            if (!row) {
                alert('Data tidak ditemukan.');
                return;
            }
            const nama = row.querySelector('[data-nama]')?.textContent.trim() || '';
            const deskripsi = row.querySelector('span[data-deskripsi].d-none')?.textContent.trim() || '';
            const isActive = row.querySelector('.toggle-active-big')?.checked || false;

            document.getElementById('mb-title').innerText = 'Edit Kategori Besar';
            document.getElementById('mb-id').value = id;
            document.getElementById('mb-nama').value = nama;
            document.getElementById('mb-deskripsi').value = deskripsi;
            document.getElementById('mb-active').checked = isActive;
            document.getElementById('mb-error').innerText = '';
            bsModalBig.show();
        }

        async function submitFormBig(e) {
            e.preventDefault();
            const id = document.getElementById('mb-id').value;
            const payload = {
                nama: document.getElementById('mb-nama').value.trim(),
                deskripsi: document.getElementById('mb-deskripsi').value.trim() || null,
                is_active: document.getElementById('mb-active').checked ? 1 : 0,
            };
            const url = id ? `${base}/master/${id}` : `${base}/master`;
            const method = id ? 'PUT' : 'POST';
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                document.getElementById('mb-error').innerText = JSON.stringify(data.errors ?? data, null, 2);
                return;
            }
            bsModalBig.hide();
            location.reload();
        }

        async function doDelete(id) {
            if (!confirm('Hapus kategori kecil?')) return;
            try {
                const res = await fetch(`${base}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    }
                });
                if (res.ok) {
                    alert('Berhasil dihapus.');
                    location.reload();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert('Gagal menghapus: ' + (data.message || res.statusText));
                }
            } catch (e) {
                alert('Terjadi kesalahan: ' + e.message);
            }
        }

        async function doDeleteBig(id) {
            if (!confirm('Hapus kategori besar? Ini akan mempengaruhi kategori kecil yang terkait.')) return;
            try {
                const res = await fetch(`${base}/master/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    }
                });
                if (res.ok) {
                    alert('Berhasil dihapus.');
                    location.reload();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert('Gagal menghapus: ' + (data.message || res.statusText));
                }
            } catch (e) {
                alert('Terjadi kesalahan: ' + e.message);
            }
        }

        async function restore(id) {
            try {
                const res = await fetch(`${base}/${id}/restore`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    }
                });
                if (res.ok) {
                    alert('Berhasil dipulihkan.');
                    location.reload();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert('Gagal memulihkan: ' + (data.message || res.statusText));
                }
            } catch (e) {
                alert('Terjadi kesalahan: ' + e.message);
            }
        }

        async function forceDel(id) {
            if (!confirm('Hapus permanen? Data tidak bisa dikembalikan.')) return;
            try {
                const res = await fetch(`${base}/${id}/force`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    }
                });
                if (res.ok) {
                    alert('Berhasil dihapus permanen.');
                    location.reload();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert('Gagal menghapus permanen: ' + (data.message || res.statusText));
                }
            } catch (e) {
                alert('Terjadi kesalahan: ' + e.message);
            }
        }

        async function openDetail(id) {
            const content = document.getElementById('detailContent');
            content.innerHTML = '<div class="text-muted">Memuat…</div>';
            bsDetailModal.show();
            try {
                const res = await fetch(`${base}/${id}/detail`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (!data.ok) throw new Error('Gagal memuat');
                const kb = data.kategori;
                const tops = (kb.topik_besar || []).map(t =>
                    `<li><code>[${t.kode}]</code> ${t.nama}${t.deskripsi?` — <span class="text-muted">${t.deskripsi}</span>`:''}</li>`
                ).join('') || '<li class="text-muted">(Belum terhubung)</li>';
                const rekoms = (data.rekomendasis || []).map(r =>
                    `<li><code>${r.kode}</code> — ${r.judul} <span class="badge ${r.is_active?'bg-success':'bg-secondary'}">${r.is_active?'aktif':'nonaktif'}</span></li>`
                ).join('') || '<li class="text-muted">(Belum ada rekomendasi)</li>';
                content.innerHTML = `
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="mb-2">Kategori Besar</h6>
                            <ul class="mb-3">${tops}</ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-2">Kategori Kecil</h6>
                            <div><strong>Kode:</strong> <code>${kb.kode}</code></div>
                            <div><strong>Nama:</strong> ${kb.nama}</div>
                            <div><strong>Deskripsi:</strong> <span class="text-muted">${kb.deskripsi||'-'}</span></div>
                        </div>
                        <div class="col-12">
                            <h6 class="mb-2">Rekomendasi Tindakan</h6>
                            <ul class="mb-0">${rekoms}</ul>
                        </div>
                    </div>
                `;
            } catch (e) {
                content.innerHTML = '<div class="text-danger">Gagal memuat detail.</div>';
            }
        }
    </script>
@endpush
