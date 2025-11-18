@extends('layouts.admin')

@section('title', 'Manajemen Kategori')

@section('page-header')
    <div>
        <h3 class="mb-1">Manajemen Kategori</h3>
    <div class="text-muted">Kelola kode, nama, deskripsi, dan status aktif.</div>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <input type="text" id="q" class="form-control form-control-sm" placeholder="Cari kode/nama…" value="{{ $qTerm ?? '' }}" style="width:220px" onkeydown="if(event.key==='Enter'){applyFilters()}">
        <select class="form-select form-select-sm" id="f-aktif" onchange="applyFilters()" style="width:180px">
            <option value="" {{ request('aktif') === null ? 'selected' : '' }}>Semua status</option>
            <option value="1" {{ request('aktif') === '1' ? 'selected' : '' }}>Aktif</option>
            <option value="0" {{ request('aktif') === '0' ? 'selected' : '' }}>Nonaktif</option>
        </select>
        <select class="form-select form-select-sm" id="f-master" onchange="applyFilters()" style="width:260px">
            <option value="">Semua Topik Besar</option>
            @isset($masters)
                @foreach($masters as $m)
                    <option value="{{ $m->id }}" {{ (string)($masterId ?? '') === (string)$m->id ? 'selected' : '' }}>[{{ $m->kode }}] {{ $m->nama }}</option>
                @endforeach
            @endisset
        </select>
        <button class="btn btn-primary" onclick="openCreateBig()">+ Kategori Besar</button>
        <button class="btn btn-outline-primary" onclick="openCreateSmall()">+ Kategori Kecil</button>
    </div>
@endsection

@section('content')
    {{-- Tabel --}}
    <div class="card shadow-sm border-0">
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
                    <tbody id="rows">
                        @forelse ($rows as $i => $k)
                            <tr data-id="{{ $k->id }}">
                                <td>{{ $rows->firstItem() + $i }}</td>
                                <td class="td-masters">
                                    @php($tops = $k->topikBesars ?? collect())
                                    @if($tops->count())
                                        @foreach($tops as $t)
                                            <span class="badge bg-light text-dark border me-1 mb-1">{{ $t->nama }}</span>
                                        @endforeach
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="td-kode">{{ $k->kode }}</td>
                                <td class="td-nama">{{ $k->nama }}</td>
                                <td class="td-is_active">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input toggle-active" type="checkbox" role="switch"
                                            data-id="{{ $k->id }}" {{ $k->is_active ? 'checked' : '' }}>
                                    </div>
                                </td>
                                <td class="actions">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="openDetail({{ $k->id }})">Detail</button>
                                        <button class="btn btn-outline-secondary"
                                            onclick="openEdit({{ $k->id }})">Edit</button>
                                        <button class="btn btn-outline-danger"
                                            onclick="doDelete({{ $k->id }})">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada data.</td>
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

    {{-- Modal --}}
    <div class="modal fade" id="modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="m-title" class="modal-title">Form Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="m-form" onsubmit="submitForm(event)">
                        @csrf
                        <input type="hidden" id="m-id">
                        <input type="hidden" id="m-kind" value="small">
                        <div class="mb-3">
                            <label class="form-label">Nama</label>
                            <input id="m-nama" class="form-control" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi (opsional)</label>
                            <textarea id="m-deskripsi" class="form-control" rows="2" maxlength="255"></textarea>
                        </div>
                        <div class="mb-3" id="m-master-wrap" style="display:none">
                            <label class="form-label">Topik Besar</label>
                            <select id="m-master" class="form-select">
                                <option value="">Pilih Topik Besar…</option>
                                @isset($masters)
                                    @foreach($masters as $m)
                                        <option value="{{ $m->id }}">[{{ $m->kode }}] {{ $m->nama }}</option>
                                    @endforeach
                                @endisset
                            </select>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="m-active" checked>
                            <label class="form-check-label" for="m-active">Aktif</label>
                        </div>
                        <pre id="m-error" class="text-danger small mb-0" style="white-space:pre-wrap"></pre>
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
        const modalEl = document.getElementById('modal');
        const detailEl = document.getElementById('detailModal');
        let bsModal;
        let bsDetailModal;
        function applyFilters(){
            const url = new URL(location.href);
            const aktif = document.getElementById('f-aktif').value;
            const master = document.getElementById('f-master').value;
            const q = document.getElementById('q').value.trim();
            if (aktif) url.searchParams.set('aktif', aktif); else url.searchParams.delete('aktif');
            if (master) url.searchParams.set('master_id', master); else url.searchParams.delete('master_id');
            if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
            location.href = url.toString();
        }

        document.addEventListener('DOMContentLoaded', () => {
            bsModal = new bootstrap.Modal(modalEl, {
                backdrop: 'static'
            });
            bsDetailModal = new bootstrap.Modal(detailEl, { backdrop: 'static' });

            // toggle aktif/nonaktif
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
                        body: JSON.stringify({
                            is_active
                        })
                    });
                    if (!res.ok) {
                        alert('Gagal mengubah status. Coba lagi.');
                        e.target.checked = !e.target.checked;
                    }
                });
            });
        });

        function openCreateBig() {
            document.getElementById('m-title').innerText = 'Tambah Kategori Besar';
            document.getElementById('m-id').value = '';
            document.getElementById('m-kind').value = 'big';
            document.getElementById('m-nama').value = '';
            document.getElementById('m-deskripsi').value = '';
            document.getElementById('m-active').checked = true;
            document.getElementById('m-master-wrap').style.display = 'none';
            document.getElementById('m-error').innerText = '';
            bsModal.show();
        }

        function openCreateSmall() {
            document.getElementById('m-title').innerText = 'Tambah Kategori Kecil';
            document.getElementById('m-id').value = '';
            document.getElementById('m-kind').value = 'small';
            document.getElementById('m-nama').value = '';
            document.getElementById('m-deskripsi').value = '';
            document.getElementById('m-active').checked = true;
            document.getElementById('m-master-wrap').style.display = '';
            // default pilih sesuai filter jika ada
            const currentMaster = document.getElementById('f-master')?.value || '';
            if (currentMaster) {
                const sel = document.getElementById('m-master');
                Array.from(sel.options).forEach(o => o.selected = (o.value === currentMaster));
            } else {
                document.getElementById('m-master').selectedIndex = 0;
            }
            document.getElementById('m-error').innerText = '';
            bsModal.show();
        }

        function openEdit(id) {
            const tr = document.querySelector(`tr[data-id="${id}"]`);
            document.getElementById('m-title').innerText = 'Edit Kategori';
            document.getElementById('m-id').value = id;
            document.getElementById('m-nama').value = tr.querySelector('.td-nama').innerText.trim();
            const desc = tr.querySelector('.td-deskripsi').innerText.trim();
            document.getElementById('m-deskripsi').value = (desc === '—' ? '' : desc);
            // status switch ada di kolom, ambil dari checkbox
            document.getElementById('m-active').checked = tr.querySelector('.toggle-active').checked;
            document.getElementById('m-error').innerText = '';
            bsModal.show();
        }

        async function submitForm(e) {
            e.preventDefault();
            const id = document.getElementById('m-id').value;
            const kind = document.getElementById('m-kind').value;
            const payload = {
                nama: document.getElementById('m-nama').value.trim(),
                deskripsi: document.getElementById('m-deskripsi').value.trim() || null,
                is_active: document.getElementById('m-active').checked ? 1 : 0,
            };
            // Tentukan endpoint & payload sesuai jenis
            let url = id ? `${base}/${id}` : base;
            let method = id ? 'PUT' : 'POST';
            if (!id && kind === 'big') {
                url = `${base}/master`;
                method = 'POST';
            }
            if (!id && kind === 'small') {
                const masterId = document.getElementById('m-master').value;
                if (!masterId) {
                    document.getElementById('m-error').innerText = 'Pilih Topik Besar terlebih dahulu.';
                    return;
                }
                payload.master_id = masterId;
            }
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
                document.getElementById('m-error').innerText = JSON.stringify(data.errors ?? data, null, 2);
                return;
            }
            bsModal.hide();
            // keep current filters on reload
            location.href = location.href;
        }

        async function doDelete(id) {
            if (!confirm('Hapus kategori?')) return;
            const res = await fetch(`${base}/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            });
            if (res.ok) location.reload();
        }
        async function restore(id) {
            const res = await fetch(`${base}/${id}/restore`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            });
            if (res.ok) location.reload();
        }
        async function forceDel(id) {
            if (!confirm('Hapus permanen?')) return;
            const res = await fetch(`${base}/${id}/force`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            });
            if (res.ok) location.reload();
        }

        async function openDetail(id){
            const content = document.getElementById('detailContent');
            content.innerHTML = '<div class="text-muted">Memuat…</div>';
            bsDetailModal.show();
            try{
                const res = await fetch(`${base}/${id}/detail`, { headers: { 'Accept':'application/json' } });
                const data = await res.json();
                if(!data.ok) throw new Error('Gagal memuat');
                const kb = data.kategori;
                const tops = (kb.topik_besar||[]).map(t=>`<li><code>[${t.kode}]</code> ${t.nama}${t.deskripsi?` — <span class="text-muted">${t.deskripsi}</span>`:''}</li>`).join('') || '<li class="text-muted">(Belum terhubung)</li>';
                const rekoms = (data.rekomendasis||[]).map(r=>`<li><code>${r.kode}</code> — ${r.judul} <span class="badge ${r.is_active?'bg-success':'bg-secondary'}">${r.is_active?'aktif':'nonaktif'}</span></li>`).join('') || '<li class="text-muted">(Belum ada rekomendasi)</li>';
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
            }catch(e){
                content.innerHTML = '<div class="text-danger">Gagal memuat detail.</div>';
            }
        }
    </script>
@endpush
