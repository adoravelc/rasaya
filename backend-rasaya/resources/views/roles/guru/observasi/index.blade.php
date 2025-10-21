@extends('layouts.guru')

@section('title', 'Input Guru (Observasi) — RASAYA')

@section('content')
    @if (session('ok'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('ok') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-1">Input Guru (Observasi)</h3>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" onclick="openCreate()">+ Tambah Observasi</button>
        </div>
    </div>

    <form method="get" class="card mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Cari</label>
                <input type="text" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari siswa/kelas/teks">
            </div>
            @if (empty($wkKelasId))
            <div class="col-md-3">
                <label class="form-label">Kelas</label>
                <select name="kelas_id" class="form-select">
                    <option value="">— Semua —</option>
                    @foreach ($kelasOptions as $opt)
                        <option value="{{ $opt['id'] }}" @selected(($filters['kelas_id'] ?? '') == (string) $opt['id'])>{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-2">
                <label class="form-label">Kondisi</label>
                <select name="kondisi" class="form-select">
                    <option value="">— Semua —</option>
                    @foreach ($opsiKondisi as $opt)
                        <option value="{{ $opt }}" @selected(($filters['kondisi'] ?? '')===$opt)>{{ strtoupper($opt) }}</option>
                    @endforeach
                </select>
            </div>
            @if (!empty($wkKelasId))
            <div class="col-md-4">
                <label class="form-label">Kategori</label>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($kategoris as $k)
                        @php $fid = 'fk-'.$k->id; @endphp
                        <input type="checkbox" class="btn-check" id="{{ $fid }}" name="filter_kategori_ids[]" value="{{ $k->id }}" autocomplete="off"
                          @checked(in_array($k->id, $filters['filter_kategori_ids'] ?? []))>
                        <label class="btn btn-outline-secondary btn-sm" for="{{ $fid }}">{{ $k->nama }}</label>
                    @endforeach
                </div>
            </div>
            @endif
            <div class="col-md-2">
                <label class="form-label">Dari</label>
                <input type="date" class="form-control" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sampai</label>
                <input type="date" class="form-control" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="col-12 d-flex gap-2 justify-content-end">
                <a href="{{ route('guru.observasi.index') }}" class="btn btn-outline-secondary">Reset</a>
                <button class="btn btn-success" type="submit">Filter</button>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:64px">#</th>
                            <th>Tanggal</th>
                            <th>Siswa (Kelas)</th>
                            <th>Kondisi</th>
                            <th>Kategori</th>
                            <th>Lampiran</th>
                            <th style="width:160px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rows">
                        @forelse($rows as $i => $r)
                            <tr data-id="{{ $r->id }}" data-kategoris="{{ $r->kategoris->pluck('id')->join(',') }}" data-tanggal="{{ optional($r->tanggal)->format('Y-m-d') }}">
                                <td>{{ $rows->firstItem() + $i }}</td>
                                <td class="td-tanggal">{{ optional($r->tanggal)->locale('id')->translatedFormat('l, d F Y') }}</td>
                                <td class="td-siswakelas" data-siswakelas="{{ $r->siswa_kelas_id }}">
                                    {{ $r->siswaKelas->label ?? '-' }}
                                </td>
                                <td class="td-kondisi">{{ strtoupper($r->kondisi_siswa) }}</td>
                                <td class="td-kategoris">
                                    @forelse($r->kategoris as $k)
                                        <span class="badge text-bg-secondary me-1">{{ $k->nama }}</span>
                                    @empty
                                        <span class="text-muted">—</span>
                                    @endforelse
                                </td>
                                <td class="td-gambar">
                                    @if ($r->gambar)
                                        <a href="{{ asset('storage/'.$r->gambar) }}" target="_blank" class="text-decoration-none">📎</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary"
                                            onclick="openEdit({{ $r->id }})">Edit</button>
                                        <button class="btn btn-outline-danger"
                                            onclick="doDelete({{ $r->id }})">Hapus</button>
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
@endsection

@push('modals')
    {{-- Modal Form --}}
    <div class="modal fade" id="modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="m-title" class="modal-title">Form Observasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <form id="m-form" onsubmit="submitForm(event)">
                        <input type="hidden" id="m-id">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tanggal</label>
                                <input id="m-tanggal" type="date" class="form-control"
                                    value="{{ now()->toDateString() }}" required>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Siswa (Kelas)</label>
                                <select id="m-siswakelas" class="form-select" required>
                                    <option value="">— Pilih —</option>
                                    @foreach ($siswaKelas as $sk)
                                        <option value="{{ $sk['id'] }}">{{ $sk['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Kondisi Siswa</label>
                                <div class="d-flex align-items-center gap-2">
                                    <select id="m-kondisi" class="form-select" required>
                                        @foreach ($opsiKondisi as $opt)
                                            <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
                                        @endforeach
                                    </select>
                                    <div id="kondisi-dot" class="rounded-circle" style="width:18px;height:18px;border:1px solid #cbd5e1"></div>
                                </div>
                                <div class="form-text mt-1">
                                    <span class="badge" style="background:#16a34a">GREEN — Aman</span>
                                    <span class="badge" style="background:#f59e0b">YELLOW — Perlu Perhatian</span>
                                    <span class="badge" style="background:#fb923c">ORANGE — Perlu Tindak Lanjut</span>
                                    <span class="badge" style="background:#ef4444">RED — Urgent</span>
                                    <span class="badge" style="background:#111827">BLACK — Krisis</span>
                                    <span class="badge" style="background:#9ca3af">GREY — Tidak Diketahui</span>
                                </div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Kategori</label>
                                <div id="kategori-group" class="d-flex flex-wrap gap-2">
                                    @foreach ($kategoris as $k)
                                        @php $kid = 'kat-'.$k->id; @endphp
                                        <input class="btn-check" id="{{ $kid }}" type="checkbox" name="kategori_ids[]" value="{{ $k->id }}" autocomplete="off">
                                        <label class="btn btn-outline-secondary btn-sm" for="{{ $kid }}" style="border-radius:20px">{{ $k->nama }}</label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Catatan</label>
                                <textarea id="m-catatan" class="form-control" rows="3" maxlength="500"></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Gambar (opsional)</label>
                                <input id="m-gambar" type="file" accept="image/*" class="form-control">
                                <div class="form-text">PNG/JPG maks 2MB</div>
                            </div>

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
    {{-- Modal Detail Read-Only --}}
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Observasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Tanggal Observasi</dt>
                        <dd class="col-sm-9" id="d-tanggal">-</dd>
                        <dt class="col-sm-3">Dibuat</dt>
                        <dd class="col-sm-9" id="d-dibuat">-</dd>
                        <dt class="col-sm-3">Siswa (Kelas)</dt>
                        <dd class="col-sm-9" id="d-siswa">-</dd>
                        <dt class="col-sm-3">Kondisi</dt>
                        <dd class="col-sm-9" id="d-kondisi">-</dd>
                        <dt class="col-sm-3">Kategori</dt>
                        <dd class="col-sm-9" id="d-kategori">-</dd>
                        <dt class="col-sm-3">Catatan</dt>
                        <dd class="col-sm-9" id="d-catatan">-</dd>
                        <dt class="col-sm-3">Gambar</dt>
                        <dd class="col-sm-9" id="d-gambar">-</dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script>
    const token = document.querySelector('meta[name="csrf-token"]').content;
        const base = "{{ route('guru.observasi.index') }}".replace(/\/$/, ''); // /guru/observasi
        const modalEl = document.getElementById('modal');
    let bsModal;
    let bsDetail;

        document.addEventListener('DOMContentLoaded', () => {
            bsModal = new bootstrap.Modal(modalEl, {
                backdrop: 'static'
            });
            bsDetail = new bootstrap.Modal(document.getElementById('detailModal'));

            // row click to open detail (ignore clicks on buttons/links)
            document.querySelectorAll('#rows tr[data-id]')?.forEach(tr => {
                tr.addEventListener('click', (e) => {
                    const t = e.target;
                    if (t && (t.closest('button') || t.closest('a'))) return;
                    const id = tr.getAttribute('data-id');
                    openDetail(Number(id));
                });
            });
        });
        async function openDetail(id){
            try{
                const res = await fetch(`${base}/${id}`, { headers: { 'Accept':'application/json' } });
                if(!res.ok) throw new Error('Gagal memuat detail');
                const d = await res.json();
                // Tanggal Observasi (tanpa waktu) - dari field tanggal
                if (d.tanggal) {
                    try {
                        const t = new Date(d.tanggal);
                        const f = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Makassar', weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' }).format(t);
                        document.getElementById('d-tanggal').innerText = f;
                    } catch (_) {
                        document.getElementById('d-tanggal').innerText = d.tanggal;
                    }
                } else {
                    document.getElementById('d-tanggal').innerText = '-';
                }

                // Format waktu dibuat (created_at) + WITA 24h
                const created = d.created_at ? new Date(d.created_at) : null;
                if (created) {
                    const dateFmt = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Makassar', weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
                    const timeFmt = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Makassar', hour: '2-digit', minute: '2-digit', hour12: false });
                    const fd = dateFmt.format(created);
                    const ft = timeFmt.format(created);
                    document.getElementById('d-dibuat').innerText = `${fd} pukul ${ft} WITA`;
                } else {
                    document.getElementById('d-dibuat').innerText = '-';
                }

                const siswa = d.siswa_kelas?.siswa?.user?.name ?? d.siswa_kelas?.siswa?.name ?? '-';
                const kelas = d.siswa_kelas?.kelas ? `${d.siswa_kelas.kelas.tingkat} ${(d.siswa_kelas.kelas.jurusan?.nama ?? '').trim()} ${d.siswa_kelas.kelas.rombel}`.replace(/\s+/g,' ').trim() : '-';
                document.getElementById('d-siswa').innerText = `${siswa} (${kelas})`;
                // Kondisi: dot + label + keterangan
                const kond = (d.kondisi_siswa || '').toLowerCase();
                const colorMap = { green:'#16a34a', yellow:'#f59e0b', orange:'#fb923c', red:'#ef4444', black:'#111827', grey:'#9ca3af' };
                const ketMap = { green:'Aman', yellow:'Perlu Perhatian', orange:'Perlu Tindak Lanjut', red:'Urgent', black:'Krisis', grey:'Tidak Diketahui' };
                const dot = `<span class="rounded-circle me-2" style="display:inline-block;width:12px;height:12px;background:${colorMap[kond]||'#e5e7eb'};border:1px solid #cbd5e1"></span>`;
                const label = (kond || '-').toUpperCase();
                const ket = ketMap[kond] ? ` — ${ketMap[kond]}` : '';
                document.getElementById('d-kondisi').innerHTML = `${dot}${label}${ket}`;
                const kat = Array.isArray(d.kategoris) ? d.kategoris.map(k=>k.nama).join(', ') : '-';
                document.getElementById('d-kategori').innerText = kat || '-';
                document.getElementById('d-catatan').innerText = d.teks || '-';
                const gambar = d.gambar ? `<a href="${location.origin}/storage/${d.gambar}" target="_blank">Lihat Lampiran</a>` : '-';
                document.getElementById('d-gambar').innerHTML = gambar;
                bsDetail.show();
            }catch(err){
                alert(err.message || 'Gagal membuka detail');
            }
        }

        // kategori checkboxes handled directly via checked state; no hidden field needed

        function paintKondisi(){
            const val = document.getElementById('m-kondisi').value.toLowerCase();
            const dot = document.getElementById('kondisi-dot');
            const map = { green:'#16a34a', yellow:'#f59e0b', orange:'#fb923c', red:'#ef4444', black:'#111827', grey:'#9ca3af', aman:'#16a34a'};
            if (dot) dot.style.background = map[val] || '#e5e7eb';
        }

        function openCreate() {
            document.getElementById('m-title').innerText = 'Tambah Observasi';
            document.getElementById('m-id').value = '';
            document.getElementById('m-tanggal').value = "{{ now()->toDateString() }}";
            document.getElementById('m-siswakelas').value = '';
            document.getElementById('m-kondisi').value = "{{ $opsiKondisi[0] ?? 'aman' }}";
            document.querySelectorAll('#kategori-group input[type="checkbox"]').forEach(el=> el.checked=false);
            const file = document.getElementById('m-gambar'); if (file) file.value = '';
            document.getElementById('m-catatan').value = '';
            document.getElementById('m-error').innerText = '';
            paintKondisi();
            bsModal.show();
        }

        async function openEdit(id) {
            document.getElementById('m-title').innerText = 'Edit Observasi';
            document.getElementById('m-id').value = id;
            document.getElementById('m-error').innerText = '';
            const file = document.getElementById('m-gambar'); if (file) file.value = '';

            try {
                const res = await fetch(`${base}/${id}`, { headers: { 'Accept':'application/json' } });
                if (!res.ok) throw new Error('Gagal memuat data');
                const d = await res.json();
                fillFormFromData(d);
            } catch (err) {
                // fallback: try read from table row
                const tr = document.querySelector(`tr[data-id="${id}"]`);
                if (tr) {
                    const raw = tr.getAttribute('data-tanggal');
                    document.getElementById('m-tanggal').value = raw || "";
                    document.getElementById('m-siswakelas').value = tr.querySelector('.td-siswakelas').dataset.siswakelas;
                    document.getElementById('m-kondisi').value = tr.querySelector('.td-kondisi').innerText.trim().toLowerCase();
                    const ids = (tr.getAttribute('data-kategoris')||'').split(',').filter(Boolean).map(x=>Number(x));
                    document.querySelectorAll('#kategori-group input[type="checkbox"]').forEach(el=> {
                        const v = Number(el.value);
                        el.checked = ids.includes(v);
                    });
                    document.getElementById('m-catatan').value = '';
                }
            }
            paintKondisi();
            bsModal.show();
        }

        function fillFormFromData(d){
            // tanggal may be 'YYYY-mm-dd' or include time, slice to 10
            const tgl = (d.tanggal || '').slice(0,10);
            document.getElementById('m-tanggal').value = tgl || "{{ now()->toDateString() }}";
            const siswakelasId = d.siswa_kelas_id || d.siswa_kelas?.id || '';
            document.getElementById('m-siswakelas').value = String(siswakelasId);
            const kondisi = (d.kondisi_siswa || '').toLowerCase();
            if (kondisi) document.getElementById('m-kondisi').value = kondisi;
            const katIds = Array.isArray(d.kategoris) ? d.kategoris.map(k=>Number(k.id)) : [];
            document.querySelectorAll('#kategori-group input[type="checkbox"]').forEach(el=> {
                const v = Number(el.value);
                el.checked = katIds.includes(v);
            });
            document.getElementById('m-catatan').value = d.teks || '';
        }

        async function submitForm(e) {
            e.preventDefault();
            const id = document.getElementById('m-id').value;

            const fd = new FormData();
            fd.append('tanggal', document.getElementById('m-tanggal').value);
            fd.append('siswa_kelas_id', String(Number(document.getElementById('m-siswakelas').value)));
            fd.append('kondisi_siswa', document.getElementById('m-kondisi').value);
            document.querySelectorAll('#kategori-group input[name="kategori_ids[]"]:checked').forEach(el=> {
                fd.append('kategori_ids[]', el.value);
            });
            const catatan = document.getElementById('m-catatan').value || '';
            fd.append('teks', catatan);
            const file = document.getElementById('m-gambar');
            if (file && file.files && file.files[0]) fd.append('gambar', file.files[0]);

            const res = await fetch(id ? `${base}/${id}` : base, {
                method: id ? 'POST' : 'POST', // we'll override with _method for PUT
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: (function(){
                    if (id) fd.append('_method', 'PUT');
                    return fd;
                })()
            });

            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                // handle duplicate (409) by prompting to edit existing
                if (res.status === 409 && data && data.existing_id) {
                    const proceed = confirm((data.message || 'Data duplikat untuk hari ini.') + '\nBuka dan edit data yang sudah ada?');
                    if (proceed) {
                        bsModal.hide();
                        await openEdit(data.existing_id);
                    }
                    return;
                }
                document.getElementById('m-error').innerText = JSON.stringify(data.errors ?? data, null, 2);
                return;
            }
            bsModal.hide();
            location.reload();
        }

        async function doDelete(id) {
            if (!confirm('Hapus observasi ini?')) return;
            const res = await fetch(`${base}/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            });
            if (res.ok) location.reload();
        }
        document.addEventListener('DOMContentLoaded', ()=>{
            paintKondisi();
            const sel = document.getElementById('m-kondisi');
            if (sel) sel.addEventListener('change', paintKondisi);
        });
    </script>
@endpush
