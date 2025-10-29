{{-- resources/views/roles/admin/kelas/index.blade.php --}}
@extends('layouts.admin')
@section('content')
    <div class="row g-3 mb-3">
        {{-- Manajemen Tahun Ajaran --}}
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-0">Tahun Ajaran</h5>
                            <div class="text-muted small">Aktif/nonaktifkan Tahun Ajaran. Hanya satu yang aktif.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary" onclick="openTaCreate()">+ Tambah Tahun Ajaran</button>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.kelas.index') }}">Refresh</a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Nama</th><th>Periode</th><th style="width:130px">Aktif</th></tr></thead>
                            <tbody>
                                @foreach($tahunAjarans->where('is_active', true) as $ta)
                                <tr>
                                    <td class="fw-semibold">{{ $ta->nama }}</td>
                                    <td class="text-muted small">{{ $ta->mulai }} — {{ $ta->selesai }}</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input ta-toggle" type="checkbox" role="switch"
                                                   data-id="{{ $ta->id }}" {{ $ta->is_active ? 'checked' : '' }}>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        {{-- Manajemen Jurusan (per Tahun Ajaran) --}}
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-0">Manajemen Jurusan</h5>
                            <div class="text-muted small">Atur daftar jurusan untuk Tahun Ajaran aktif ini.</div>
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="openJurusanCreate()">+ Tambah Jurusan</button>
                    </div>
                    <ul id="jurusan-list" class="list-group flex-grow-1"></ul>
                </div>
            </div>
        </div>
    </div>
    <div class="mb-3">
        <button class="btn btn-outline-secondary btn-sm" onclick="openTaManager()">Kelola TA yang Disembunyikan</button>
    </div>
    

    {{-- Tabel --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-center px-3 pt-3">
                <h5 class="mb-0">Kelas</h5>
                <button class="btn btn-sm btn-primary" onclick="openCreate()">+ Tambah Kelas</button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:64px">#</th>
                            <th>Label</th>
                            <th>Tingkat</th>
                            <th>Penjurusan</th>
                            <th>Rombel</th>
                            <th>Wali Guru</th>
                            <th style="width:160px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rows">
                        @forelse ($kelas as $i => $k)
                            <tr data-id="{{ $k->id }}" class="kelas-row" style="cursor:pointer">
                                <td>{{ $kelas->firstItem() + $i }}</td>
                                <td class="td-label">{{ $k->label }}</td>
                                <td class="td-tingkat">{{ $k->tingkat }}</td>
                                <td class="td-jur" data-jurusan-id="{{ $k->jurusan_id ?? '' }}">{{ $k->jurusan->nama ?? '-' }}</td>
                                <td class="td-rombel">{{ $k->rombel }}</td>
                                <td class="td-wali" data-wali-id="{{ $k->wali_guru_id ?? '' }}">{{ $k->waliGuru->name ?? '-' }}</td>
                                <td class="actions">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary"
                                            onclick="openEdit({{ $k->id }})">Edit</button>
                                        <button class="btn btn-outline-danger"
                                            onclick="doDelete({{ $k->id }})">Hapus</button>
                                        <button class="btn btn-outline-primary" onclick='openManageSiswa({{ $k->id }}, @json($k->label))'>Kelola Siswa</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $kelas->withQueryString()->links() }}
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
                        {{ $t->label }}
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

    {{-- Modal Bootstrap --}}
    {{-- Modal Tambah Tahun Ajaran --}}
    <div class="modal fade" id="modalTa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Tahun Ajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="ta-form" onsubmit="submitTaForm(event)">
                        <div class="mb-3">
                            <label class="form-label">Nama</label>
                            <input type="text" id="ta-nama" class="form-control" placeholder="2025/2026" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Mulai</label>
                                <input type="date" id="ta-mulai" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Selesai</label>
                                <input type="date" id="ta-selesai" class="form-control">
                            </div>
                        </div>
                        <input type="hidden" id="ta-aktif" value="1">
                        <pre id="ta-error" class="text-danger small mt-3 mb-0" style="white-space:pre-wrap"></pre>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary" form="ta-form">Simpan</button>
                </div>
            </div>
        </div>
    </div>
    {{-- Detail Kelas (read-only) --}}
    <div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="d-title" class="modal-title">Detail Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="text-muted small">Tahun Ajaran</div>
                            <div id="d-ta" class="fw-semibold">-</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Tingkat</div>
                            <div id="d-tingkat" class="fw-semibold">-</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Penjurusan</div>
                            <div id="d-jur" class="fw-semibold">-</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Rombel</div>
                            <div id="d-rombel" class="fw-semibold">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Wali Kelas</div>
                            <div id="d-wali" class="fw-semibold">-</div>
                        </div>
                    </div>
                    <hr>
                    <div>
                        <div class="mb-2 fw-semibold">Siswa dalam Kelas Ini</div>
                        <div id="d-siswa-list" class="small text-muted">Memuat...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- Modal Kelola Tahun Ajaran (inactive & trash) --}}
    <div class="modal fade" id="modalTaManager" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kelola Tahun Ajaran Disembunyikan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <h6 class="mb-2">Tidak Aktif</h6>
                            <ul id="ta-inactive-list" class="list-group small">
                                <li class="list-group-item text-muted">Memuat...</li>
                            </ul>
                        </div>
                        <div class="col-12 col-lg-6">
                            <h6 class="mb-2">Terhapus (soft)</h6>
                            <ul id="ta-trashed-list" class="list-group small">
                                <li class="list-group-item text-muted">Memuat...</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="m-title" class="modal-title">Form Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <form id="m-form" onsubmit="submitForm(event)">
                        <input type="hidden" id="m-id">

                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Tahun Ajaran</label>
                                <select id="m-ta" class="form-select" required>
                                    @foreach ($tahunAjarans as $ta)
                                        <option value="{{ $ta->id }}">{{ $ta->nama }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Tingkat</label>
                                <select id="m-tingkat" class="form-select" required>
                                    <option>X</option>
                                    <option>XI</option>
                                    <option>XII</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Jurusan</label>
                                <select id="m-penjurusan" class="form-select">
                                    <option value="">—</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Rombel (nomor)</label>
                                <input id="m-rombel" type="number" min="1" class="form-control" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Wali Kelas</label>
                                <select id="m-wali" class="form-select" required>
                                    <option value="">— Pilih Wali Kelas —</option>
                                    @foreach($waliOptions as $w)
                                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 d-none d-md-block"></div>

                            <div class="col-12">
                                <pre id="m-error" class="text-danger small mb-0" style="white-space:pre-wrap"></pre>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" form="m-form" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    </div>
            {{-- Modal Kelola Siswa per Kelas --}}
            <div class="modal fade" id="modalSiswa" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 id="ms-title" class="modal-title">Kelola Siswa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="ms-form-add" onsubmit="submitAddSiswa(event)">
                                <input type="hidden" id="ms-kelas-id">
                                <input type="hidden" id="ms-ta" value="{{ $activeTa }}">
                                <div class="mb-2">
                                    <label class="form-label">Pilih Siswa</label>
                                    <select id="ms-siswa" class="form-select" required>
                                        @foreach($siswas as $s)
                                            <option value="{{ $s->user_id }}">{{ $s->user->identifier }} — {{ $s->user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="text-end"><button class="btn btn-primary">Tambah</button></div>
                            </form>
                            <hr>
                            <div id="ms-list">
                                <div class="text-muted small">Memuat data...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
@endsection

@push('scripts')
    <script>
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const base = '/admin/kelas';
    let bsModal, bsModalSiswa, bsModalDetail;

        // Precomputed data for assignments (active TA) and all students
        @php
            $assignmentItems = $assignments->map(function($a){
                return [
                    'kelas_id' => (int) $a->kelas_id,
                    'siswa_id' => (int) $a->siswa_id,
                    'name' => optional(optional($a->siswa)->user)->name,
                    'identifier' => optional(optional($a->siswa)->user)->identifier,
                ];
            })->values()->toArray();
            $siswasItems = $siswas->map(function($s){
                return [
                    'id' => (int) $s->user_id,
                    'name' => optional($s->user)->name,
                    'identifier' => optional($s->user)->identifier,
                ];
            })->values()->toArray();
        @endphp
        const ASSIGNMENTS = {!! json_encode($assignmentItems, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
        const SISWAS = {!! json_encode($siswasItems, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};

        // Precompute wali options and used wali IDs (only from non-trashed classes in active TA)
        @php
            $waliItems = $waliOptions->map(fn($w) => ['id' => (int) $w->id, 'name' => $w->name])->values()->toArray();
            $usedWaliIds = $kelas->pluck('wali_guru_id')->filter()->map(fn($v) => (int) $v)->values()->toArray();
        @endphp
        const WALI_OPTIONS = {!! json_encode($waliItems, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
        const USED_WALI_IDS = {!! json_encode($usedWaliIds, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};

        // Jurusan data (initial render from backend for active TA)
        @php
            $jurusanItems = ($jurusans ?? collect())->map(fn($j)=>['id'=>(int)$j->id,'nama'=>$j->nama])->values()->toArray();
        @endphp
        let JURUSAN = {!! json_encode($jurusanItems, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};

        function esc(s){
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const modalEl = document.getElementById('modal');
            const modalSiswaEl = document.getElementById('modalSiswa');
            const modalDetailEl = document.getElementById('modalDetail');
            if (modalEl) bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
            if (modalSiswaEl) bsModalSiswa = new bootstrap.Modal(modalSiswaEl, { backdrop: 'static' });
            if (modalDetailEl) bsModalDetail = new bootstrap.Modal(modalDetailEl, { backdrop: true });
            renderJurusan();
            rebuildJurusanSelect();
            const taSel = document.getElementById('m-ta');
            if (taSel) taSel.addEventListener('change', () => {
                refreshJurusan();
            });

            // Row click opens detail, but ignore clicks on buttons inside action cell
            document.querySelectorAll('tr.kelas-row').forEach(tr => {
                tr.addEventListener('click', (e) => {
                    const target = e.target;
                    if (target && target.closest && target.closest('.actions')) return;
                    const id = Number(tr.getAttribute('data-id'));
                    openDetail(id);
                });
            });

            // Tahun Ajaran toggle
            document.querySelectorAll('.ta-toggle').forEach(chk => {
                chk.addEventListener('change', async (e) => {
                    const id = Number(chk.getAttribute('data-id'));
                    const isActive = chk.checked;
                    try{
                        const res = await fetch(`{{ url('/admin/tahun-ajaran') }}/${id}/active`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ is_active: isActive })
                        });
                        if(!res.ok){
                            const data = await res.json().catch(()=>({}));
                            throw new Error(data.message || 'Gagal memperbarui TA');
                        }
                        if (!isActive) {
                            // immediately hide row if deactivated
                            const tr = chk.closest('tr');
                            tr && tr.remove();
                        }
                        rasayaToast('success', 'Tahun Ajaran diperbarui');
                    }catch(err){
                        chk.checked = !isActive; // rollback
                        rasayaToast('danger', 'Gagal', [String(err.message||err)]);
                    }
                });
            });

            // TA modal helpers
            window.openTaCreate = function(){
                document.getElementById('ta-nama').value = '';
                document.getElementById('ta-mulai').value = '';
                document.getElementById('ta-selesai').value = '';
                document.getElementById('ta-error').innerText = '';
                (new bootstrap.Modal(document.getElementById('modalTa'))).show();
            }
            window.submitTaForm = async function(e){
                e.preventDefault();
                const payload = {
                    nama: document.getElementById('ta-nama').value.trim(),
                    mulai: document.getElementById('ta-mulai').value || null,
                    selesai: document.getElementById('ta-selesai').value || null,
                    is_active: true,
                };
                const errEl = document.getElementById('ta-error');
                errEl.innerText = '';
                try{
                    const res = await fetch(`{{ url('/admin/tahun-ajaran') }}`, {
                        method: 'POST',
                        headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': token, 'Accept':'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json().catch(()=>({}));
                    if(!res.ok){
                        errEl.innerText = JSON.stringify(data.errors ?? data, null, 2);
                        return;
                    }
                    bootstrap.Modal.getInstance(document.getElementById('modalTa')).hide();
                    rasayaToast('success','Tahun Ajaran ditambahkan & diaktifkan');
                    location.reload();
                }catch(err){
                    errEl.innerText = String(err.message||err);
                }
            }

            // TA Manager modal
            window.openTaManager = async function(){
                const modalEl = document.getElementById('modalTaManager');
                if (!modalEl) return;
                const m = new bootstrap.Modal(modalEl, { backdrop:'static' });
                // load inactive
                try{
                    const res = await fetch(`{{ route('admin.tahun_ajaran.index') }}?is_active=0`, { headers:{ 'Accept':'application/json' } });
                    const data = await res.json();
                    const list = document.getElementById('ta-inactive-list');
                    const rows = (data?.data||[]).filter(i=>!i.is_active);
                    if(rows.length===0){
                        list.innerHTML = '<li class="list-group-item text-muted">Tidak ada.</li>';
                    } else {
                        list.innerHTML = rows.map(i=>`
                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                              <div class="fw-semibold">${esc(i.nama)}</div>
                              <div class="text-muted">${esc(i.mulai||'-')} — ${esc(i.selesai||'-')}</div>
                            </div>
                            <div class="btn-group btn-group-sm">
                              <button class="btn btn-outline-success" onclick="taActivate(${i.id})">Aktifkan</button>
                              <button class="btn btn-outline-danger" onclick="taSoftDelete(${i.id})">Hapus</button>
                            </div>
                          </li>`).join('');
                    }
                }catch(e){ console.error(e); }
                // load trashed
                try{
                    const res2 = await fetch(`{{ route('admin.tahun_ajaran.trashed') }}`, { headers:{ 'Accept':'application/json' } });
                    const data2 = await res2.json();
                    const list2 = document.getElementById('ta-trashed-list');
                    const rows2 = (data2?.data||[]);
                    if(rows2.length===0){
                        list2.innerHTML = '<li class="list-group-item text-muted">Tidak ada.</li>';
                    } else {
                        list2.innerHTML = rows2.map(i=>`
                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                              <div class="fw-semibold">${esc(i.nama)}</div>
                              <div class="text-muted">${esc(i.mulai||'-')} — ${esc(i.selesai||'-')}</div>
                            </div>
                            <div class="btn-group btn-group-sm">
                              <button class="btn btn-outline-success" onclick="taRestore(${i.id})">Pulihkan</button>
                              <button class="btn btn-outline-danger" onclick="taForceDelete(${i.id})">Hapus Permanen</button>
                            </div>
                          </li>`).join('');
                    }
                }catch(e){ console.error(e); }

                m.show();
            }
        });

        async function taActivate(id){
            try{
                const res = await fetch(`{{ url('/admin/tahun-ajaran') }}/${id}/active`, {
                    method: 'PATCH', headers:{ 'Content-Type':'application/json','X-CSRF-TOKEN': token,'Accept':'application/json' },
                    body: JSON.stringify({ is_active: true })
                });
                if(!res.ok) throw new Error('Gagal mengaktifkan');
                rasayaToast('success','Diaktifkan');
                location.reload();
            }catch(e){ rasayaToast('danger','Gagal', [String(e.message||e)]); }
        }
        async function taSoftDelete(id){
            if(!confirm('Hapus (soft delete) Tahun Ajaran ini?')) return;
            try{
                const res = await fetch(`{{ url('/admin/tahun-ajaran') }}/${id}`, { method:'DELETE', headers:{ 'X-CSRF-TOKEN': token, 'Accept':'application/json' }});
                if(!res.ok) throw new Error('Gagal menghapus');
                rasayaToast('success','Dihapus');
                location.reload();
            }catch(e){ rasayaToast('danger','Gagal', [String(e.message||e)]); }
        }
        async function taRestore(id){
            try{
                const res = await fetch(`{{ url('/admin/tahun-ajaran') }}/${id}/restore`, { method:'POST', headers:{ 'X-CSRF-TOKEN': token, 'Accept':'application/json' }});
                if(!res.ok) throw new Error('Gagal memulihkan');
                rasayaToast('success','Dipulihkan');
                location.reload();
            }catch(e){ rasayaToast('danger','Gagal', [String(e.message||e)]); }
        }
        async function taForceDelete(id){
            if(!confirm('Hapus PERMANEN? Ini tidak bisa dibatalkan.')) return;
            try{
                const res = await fetch(`{{ url('/admin/tahun-ajaran') }}/${id}/force`, { method:'DELETE', headers:{ 'X-CSRF-TOKEN': token, 'Accept':'application/json' }});
                if(!res.ok) throw new Error('Gagal hapus permanen');
                rasayaToast('success','Dihapus permanen');
                location.reload();
            }catch(e){ rasayaToast('danger','Gagal', [String(e.message||e)]); }
        }
        async function openDetail(kelasId){
            // Fill header info from row DOM
            const tr = document.querySelector(`tr[data-id="${kelasId}"]`);
            const label = tr?.querySelector('.td-label')?.textContent?.trim() ?? '';
            const tingkat = tr?.querySelector('.td-tingkat')?.textContent?.trim() ?? '-';
            const jur = tr?.querySelector('.td-jur')?.textContent?.trim() ?? '-';
            const rombel = tr?.querySelector('.td-rombel')?.textContent?.trim() ?? '-';
            const wali = tr?.querySelector('.td-wali')?.textContent?.trim() ?? '-';
            document.getElementById('d-title').textContent = `Detail Kelas — ${label}`;
            document.getElementById('d-tingkat').textContent = tingkat;
            document.getElementById('d-jur').textContent = jur;
            document.getElementById('d-rombel').textContent = rombel;
            document.getElementById('d-wali').textContent = wali;
            document.getElementById('d-ta').textContent = `{{ optional($tahunAjarans->firstWhere('id',$activeTa))->nama ?? '-' }}`;

            // Build student list from ASSIGNMENTS filtered by kelas
            const listEl = document.getElementById('d-siswa-list');
            const filtered = ASSIGNMENTS.filter(i => i.kelas_id === kelasId);
            if(filtered.length === 0){
                listEl.innerHTML = '<div class="text-muted">Belum ada siswa.</div>';
            } else {
                listEl.innerHTML = '<ul class="list-group list-group-flush">' + filtered.map(i => `
                    <li class="list-group-item py-2 d-flex justify-content-between align-items-center">
                        <span><strong>${esc(i.identifier)}</strong> — ${esc(i.name)}</span>
                    </li>`).join('') + '</ul>';
            }
            bsModalDetail?.show();
        }

        function rebuildWaliSelect(selectedId = null){
            const sel = document.getElementById('m-wali');
            const available = WALI_OPTIONS.filter(w => !USED_WALI_IDS.includes(w.id) || (selectedId !== null && w.id === Number(selectedId)));
            const opts = ['<option value="">— Pilih Wali Kelas —</option>']
                .concat(available.map(w => `<option value="${w.id}">${esc(w.name)}</option>`));
            sel.innerHTML = opts.join('');
        }

        function renderJurusan(){
            const ul = document.getElementById('jurusan-list');
            if(!ul) return;
            if(!Array.isArray(JURUSAN) || JURUSAN.length===0){
                ul.innerHTML = '<li class="list-group-item text-muted">Belum ada jurusan untuk TA ini.</li>';
                return;
            }
            ul.innerHTML = JURUSAN.map(j => `
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <span>${esc(j.nama)}</span>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="editJurusan(${j.id})">Edit</button>
                    <button class="btn btn-outline-danger" onclick="deleteJurusan(${j.id})">Hapus</button>
                  </div>
                </li>
            `).join('');
        }

        function rebuildJurusanSelect(selected = ''){
            const sel = document.getElementById('m-penjurusan');
            if(!sel) return;
            const opts = ['<option value="">—</option>']
                .concat(JURUSAN.map(j => `<option value="${j.id}">${esc(j.nama)}</option>`));
            sel.innerHTML = opts.join('');
            sel.value = selected || '';
        }

        async function refreshJurusan(){
            try{
                const ta = document.getElementById('m-ta')?.value || '{{ $activeTa }}';
                const res = await fetch(`{{ route('admin.jurusan.index') }}?tahun_ajaran_id=${ta}` , {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });
                const data = await res.json();
                JURUSAN = (data?.data ?? []).map(j => ({ id: Number(j.id), nama: String(j.nama) }));
                renderJurusan();
                rebuildJurusanSelect();
            }catch(e){ console.error(e); }
        }

        function openJurusanCreate(){
            const nama = prompt('Nama jurusan baru:');
            if(!nama) return;
            submitJurusan(null, nama);
        }

        function editJurusan(id){
            const cur = JURUSAN.find(j=>j.id===id);
            if(!cur) return;
            const nama = prompt('Ubah nama jurusan:', cur.nama);
            if(!nama || nama===cur.nama) return;
            submitJurusan(id, nama);
        }

        async function submitJurusan(id, nama){
            const ta = document.getElementById('m-ta')?.value || '{{ $activeTa }}';
            const url = id ? `{{ url('/admin/jurusan') }}/${id}` : `{{ route('admin.jurusan.store') }}`;
            const method = id ? 'PUT' : 'POST';
            const res = await fetch(url, {
                method,
                headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': token, 'Accept':'application/json' },
                body: JSON.stringify({ tahun_ajaran_id: Number(ta), nama })
            });
            if(res.ok){
                rasayaToast('success', id? 'Jurusan diperbarui':'Jurusan ditambahkan');
                await refreshJurusan();
            }else{
                const data = await res.json().catch(()=>({}));
                rasayaToast('danger', 'Gagal menyimpan jurusan', data.errors ? Object.values(data.errors).flat() : [data.message||'Unknown error']);
            }
        }

        async function deleteJurusan(id){
            if(!confirm('Hapus jurusan ini?')) return;
            const res = await fetch(`{{ url('/admin/jurusan') }}/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': token, 'Accept':'application/json' }
            });
            if(res.ok){
                rasayaToast('success','Jurusan dihapus');
                await refreshJurusan();
            }else{
                const data = await res.json().catch(()=>({}));
                rasayaToast('danger','Gagal menghapus', data.errors ? Object.values(data.errors).flat() : [data.message||'Unknown error']);
            }
        }

        function openCreate() {
            document.getElementById('m-title').innerText = 'Tambah Kelas';
            document.getElementById('m-id').value = '';
            document.getElementById('m-ta').value = '{{ $activeTa }}';
            document.getElementById('m-tingkat').value = 'X';
            document.getElementById('m-penjurusan').value = '';
            document.getElementById('m-rombel').value = '';
            rebuildWaliSelect(null);
            document.getElementById('m-wali').value = '';
            document.getElementById('m-error').innerText = '';
            rebuildJurusanSelect('');
            bsModal.show();
        }

        function openEdit(id) {
            const tr = document.querySelector(`tr[data-id="${id}"]`);
            document.getElementById('m-title').innerText = 'Edit Kelas';
            document.getElementById('m-id').value = id;
            document.getElementById('m-ta').value = '{{ $activeTa }}';
            document.getElementById('m-tingkat').value = tr.querySelector('.td-tingkat').innerText.trim();
            document.getElementById('m-penjurusan').value = tr.querySelector('.td-jur').dataset.jurusanId || '';
            document.getElementById('m-rombel').value = tr.querySelector('.td-rombel').innerText.trim();
            const currentWali = tr.querySelector('.td-wali').dataset.waliId || '';
            rebuildWaliSelect(currentWali ? Number(currentWali) : null);
            document.getElementById('m-wali').value = currentWali;
            document.getElementById('m-error').innerText = '';
            rebuildJurusanSelect(document.getElementById('m-penjurusan').value);
            bsModal.show();
        }

        async function submitForm(e) {
            e.preventDefault();
            const id = document.getElementById('m-id').value;
            const payload = {
                tahun_ajaran_id: document.getElementById('m-ta').value,
                tingkat: document.getElementById('m-tingkat').value,
                jurusan_id: document.getElementById('m-penjurusan').value ? Number(document.getElementById('m-penjurusan').value) : null,
                rombel: Number(document.getElementById('m-rombel').value),
                wali_guru_id: document.getElementById('m-wali').value || null,
            };

            const res = await fetch(id ? `${base}/${id}` : base, {
                method: id ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                document.getElementById('m-error').innerText = JSON.stringify((data.errors ?? data), null, 2);
                return;
            }
            bsModal.hide();
            location.reload();
        }

        async function doDelete(id) {
            if (!confirm('Hapus kelas?')) return;
            const res = await fetch(`${base}/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
            });
            if (res.ok) location.reload();
        }

        async function restore(id) {
            const res = await fetch(`${base}/${id}/restore`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
            });
            if (res.ok) location.reload();
        }

        async function forceDel(id) {
            if (!confirm('Hapus permanen?')) return;
            const res = await fetch(`${base}/${id}/force`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
            });
            if (res.ok) location.reload();
        }

        // === Kelola Siswa per Kelas ===
        function openManageSiswa(kelasId, label){
            document.getElementById('ms-title').innerText = `Kelola Siswa — ${label}`;
            document.getElementById('ms-kelas-id').value = kelasId;

            // Rebuild the siswa select to only include those NOT assigned in the active TA
            const assignedIds = new Set(ASSIGNMENTS.map(a => a.siswa_id));
            const available = SISWAS.filter(s => !assignedIds.has(s.id));
            const sel = document.getElementById('ms-siswa');
            if (available.length === 0) {
                sel.innerHTML = '<option value="" disabled selected>Tidak ada siswa tersedia</option>';
                sel.disabled = true;
            } else {
                sel.disabled = false;
                sel.innerHTML = available.map(s => `<option value="${s.id}">${esc(s.identifier)} — ${esc(s.name)}</option>`).join('');
            }

            loadSiswaList(kelasId);
            if (!bsModalSiswa) {
                const modalSiswaEl = document.getElementById('modalSiswa');
                bsModalSiswa = new bootstrap.Modal(modalSiswaEl, { backdrop: 'static' });
            }
            bsModalSiswa.show();
        }

        async function loadSiswaList(kelasId){
            const listEl = document.getElementById('ms-list');
            const filtered = ASSIGNMENTS.filter(i => i.kelas_id === kelasId);
            if(filtered.length === 0){
                listEl.innerHTML = '<div class="text-muted small">Belum ada siswa.</div>';
                return;
            }
            listEl.innerHTML = '<ul class="list-group">' + filtered.map(i => `
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div><strong>${i.name}</strong> <span class="text-muted">(${i.identifier})</span></div>
                    <button class="btn btn-sm btn-outline-danger" onclick="removeSiswa(${kelasId}, ${i.siswa_id})">Keluarkan</button>
                </li>`).join('') + '</ul>';
        }

        async function submitAddSiswa(e){
            e.preventDefault();
            const kelasId = Number(document.getElementById('ms-kelas-id').value);
            const siswaId = Number(document.getElementById('ms-siswa').value);
            const ta = Number(document.getElementById('ms-ta').value);
            const res = await fetch(`{{ route('admin.siswa_kelas.store') }}`,{
                method:'POST',
                headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': token, 'Accept':'application/json' },
                body: JSON.stringify({ tahun_ajaran_id: ta, kelas_id: kelasId, siswa_id: siswaId })
            });
            if(res.ok){
                rasayaToast('success','Siswa ditambahkan');
                location.reload();
            }else{
                const data = await res.json().catch(()=>({}));
                rasayaToast('danger','Gagal menambah', data.errors ? Object.values(data.errors).flat() : [data.message||'Unknown error']);
            }
        }

        async function removeSiswa(kelasId, siswaId){
            const ta = Number(document.getElementById('ms-ta').value);
            const res = await fetch(`{{ route('admin.siswa_kelas.remove') }}`,{
                method:'POST',
                headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': token, 'Accept':'application/json' },
                body: JSON.stringify({ tahun_ajaran_id: ta, kelas_id: kelasId, siswa_id: siswaId })
            });
            if(res.ok){
                rasayaToast('success','Siswa dikeluarkan');
                location.reload();
            }else{
                const data = await res.json().catch(()=>({}));
                rasayaToast('danger','Gagal mengeluarkan', data.errors ? Object.values(data.errors).flat() : [data.message||'Unknown error']);
            }
        }
    </script>
@endpush
