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
                <input type="text" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}"
                    placeholder="Cari siswa/kelas/teks">
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
                        <option value="{{ $opt }}" @selected(($filters['kondisi'] ?? '') === $opt)>{{ strtoupper($opt) }}</option>
                    @endforeach
                </select>
            </div>
            @if (!empty($wkKelasId))
                <div class="col-md-4">
                    <label class="form-label">Topik Besar (Filter)</label>
                    <select name="filter_master_kategori_id" class="form-select">
                        <option value="">— Semua —</option>
                        @foreach ($masterKategoris as $mk)
                            <option value="{{ $mk->id }}" @selected(($filters['filter_master_kategori_id'] ?? '') == (string) $mk->id)>{{ $mk->nama }}</option>
                        @endforeach
                    </select>
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
                            <th>Topik Besar</th>
                            <th>Lampiran</th>
                            <th style="width:160px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rows">
                        @forelse($rows as $i => $r)
                            @php
                                $k = strtolower($r->kondisi_siswa ?? 'grey');
                                $colorMap = [
                                    'green' => '#dcfce7',
                                    'yellow' => '#fef9c3',
                                    'orange' => '#ffedd5',
                                    'red' => '#fee2e2',
                                    'black' => '#1f2937',
                                    'grey' => '#f3f4f6',
                                ];
                                $rowBg = $colorMap[$k] ?? 'transparent';
                                $rowFg = $k === 'black' ? 'color:#fff;' : '';

                                $kondisiLabels = [
                                    'green' => 'Normal / Baik',
                                    'yellow' => 'Perlu Dipantau',
                                    'orange' => 'Butuh Perhatian Lebih',
                                    'red' => 'Perlu Intervensi Segera',
                                    'black' => 'Kritis / Darurat',
                                    'grey' => 'Netral / Tidak Jelas',
                                ];
                                $kondisiText = $kondisiLabels[$k] ?? strtoupper($r->kondisi_siswa);
                            @endphp
                            <tr data-id="{{ $r->id }}" data-tanggal="{{ optional($r->tanggal)->format('Y-m-d') }}"
                                style="background:{{ $rowBg }};{{ $rowFg }}">
                                <td>{{ $rows->firstItem() + $i }}</td>
                                <td class="td-tanggal">
                                    {{ optional($r->tanggal)->locale('id')->translatedFormat('l, d F Y') }}</td>
                                <td class="td-siswakelas" data-siswakelas="{{ $r->siswa_kelas_id }}">
                                    {{ $r->siswaKelas->label ?? '-' }}
                                </td>
                                <td class="td-kondisi">{{ $kondisiText }}</td>
                                <td class="td-topik">
                                    @if ($r->masterKategori)
                                        <span class="badge text-bg-secondary">{{ $r->masterKategori->nama }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="td-gambar">
                                    @if ($r->gambar_url)
                                        <a href="{{ $r->gambar_url }}" target="_blank" class="text-decoration-none">📎</a>
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
                                <input id="m-tanggal" type="hidden" value="{{ now()->toDateString() }}">
                                <div id="m-tanggal-display" class="form-control-plaintext">
                                    {{ now()->locale('id')->translatedFormat('l, d F Y') }}
                                </div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Siswa (Kelas)</label>
                                <input type="text" id="m-siswa-search" class="form-control mb-2"
                                    placeholder="Cari nama atau NISN...">
                                <select id="m-siswakelas" class="form-select" required>
                                    <option value="">— Pilih —</option>
                                    @foreach ($siswaKelas as $sk)
                                        @php($flagged = in_array($sk['id'], $flaggedIds ?? []))
                                        <option value="{{ $sk['id'] }}"
                                            @if ($flagged) style="font-weight:bold;color:#dc2626;background:#fee2e2" @endif>
                                            {{ $flagged ? '⚠ ' : '' }}{{ $sk['label'] }}</option>
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
                                    <div id="kondisi-dot" class="rounded-circle"
                                        style="width:18px;height:18px;border:1px solid #cbd5e1"></div>
                                </div>
                                <div class="form-text mt-1">
                                    <span class="badge" style="background:#16a34a">GREEN — Aman (stabil)</span>
                                    <span class="badge" style="background:#f59e0b;color:#000">YELLOW — Perlu perhatian
                                        awal</span>
                                    <span class="badge" style="background:#f97316">ORANGE — Perlu tindak lanjut
                                        segera</span>
                                    <span class="badge" style="background:#dc2626">RED — Urgent (prioritas tinggi)</span>
                                    <span class="badge" style="background:#111827">BLACK — Krisis (intervensi
                                        langsung)</span>
                                    <span class="badge" style="background:#9ca3af;color:#000">GREY — Tidak
                                        diketahui</span>
                                </div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Topik Besar</label>
                                <select id="m-master-kategori" class="form-select">
                                    <option value="">— Pilih Topik —</option>
                                    @foreach ($masterKategoris as $mk)
                                        <option value="{{ $mk->id }}">{{ $mk->nama }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Guru memilih satu topik besar; sub-topik akan dipetakan otomatis
                                    oleh sistem analisis.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Catatan</label>
                                <textarea id="m-catatan" class="form-control" rows="3" maxlength="500"></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Gambar (opsional)</label>

                                {{-- Container Preview Gambar Existing --}}
                                <div id="preview-container"
                                    class="d-none mb-2 p-2 border rounded bg-light position-relative">
                                    <div class="d-flex align-items-start gap-2">
                                        <img id="img-preview" src="" alt="Preview" class="img-fluid rounded"
                                            style="max-height: 100px; width: auto;">
                                        <div>
                                            <small class="d-block text-muted mb-1">Gambar saat ini</small>
                                            <button type="button" class="btn btn-xs btn-danger" onclick="hapusGambar()">
                                                Hapus Gambar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Input File --}}
                                <input id="m-gambar" type="file" accept="image/*" class="form-control"
                                    onchange="previewNewImage(this)">
                                <div class="form-text">Upload baru untuk mengganti.</div>

                                {{-- Input Hidden buat nandain kalo gambar dihapus --}}
                                <input type="hidden" id="m-hapus-gambar-flag" value="0">
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

            // Simple client-side search for student select
            const sIn = document.getElementById('m-siswa-search');
            const sSel = document.getElementById('m-siswakelas');
            if (sIn && sSel) {
                const opts = Array.from(sSel.options);
                sIn.addEventListener('input', function() {
                    const q = this.value.trim().toLowerCase();
                    opts.forEach(opt => {
                        if (opt.value === '') return; // keep placeholder
                        const text = opt.text.toLowerCase();
                        opt.hidden = q.length > 0 ? !text.includes(q) : false;
                    });
                });
            }
        });
        async function openDetail(id) {
            try {
                const res = await fetch(`${base}/${id}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                if (!res.ok) throw new Error('Gagal memuat detail');
                const d = await res.json();
                // Tanggal Observasi (tanpa waktu) - dari field tanggal
                if (d.tanggal) {
                    try {
                        const t = new Date(d.tanggal);
                        const f = new Intl.DateTimeFormat('id-ID', {
                            timeZone: 'Asia/Makassar',
                            weekday: 'long',
                            day: '2-digit',
                            month: 'long',
                            year: 'numeric'
                        }).format(t);
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
                    const dateFmt = new Intl.DateTimeFormat('id-ID', {
                        timeZone: 'Asia/Makassar',
                        weekday: 'long',
                        day: '2-digit',
                        month: 'long',
                        year: 'numeric'
                    });
                    const timeFmt = new Intl.DateTimeFormat('id-ID', {
                        timeZone: 'Asia/Makassar',
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
                    });
                    const fd = dateFmt.format(created);
                    const ft = timeFmt.format(created);
                    document.getElementById('d-dibuat').innerText = `${fd} pukul ${ft} WITA`;
                } else {
                    document.getElementById('d-dibuat').innerText = '-';
                }

                const siswa = d.siswa_kelas?.siswa?.user?.name ?? d.siswa_kelas?.siswa?.name ?? '-';
                const kelas = d.siswa_kelas?.kelas ?
                    `${d.siswa_kelas.kelas.tingkat} ${(d.siswa_kelas.kelas.jurusan?.nama ?? '').trim()} ${d.siswa_kelas.kelas.rombel}`
                    .replace(/\s+/g, ' ').trim() : '-';
                document.getElementById('d-siswa').innerText = `${siswa} (${kelas})`;
                // Kondisi: dot + label + keterangan
                const kond = (d.kondisi_siswa || '').toLowerCase();
                const colorMap = {
                    green: '#16a34a',
                    yellow: '#f59e0b',
                    orange: '#fb923c',
                    red: '#ef4444',
                    black: '#111827',
                    grey: '#9ca3af'
                };
                const ketMap = {
                    green: 'Aman',
                    yellow: 'Perlu Perhatian',
                    orange: 'Perlu Tindak Lanjut',
                    red: 'Urgent',
                    black: 'Krisis',
                    grey: 'Tidak Diketahui'
                };
                const dot =
                    `<span class="rounded-circle me-2" style="display:inline-block;width:12px;height:12px;background:${colorMap[kond]||'#e5e7eb'};border:1px solid #cbd5e1"></span>`;
                const label = (kond || '-').toUpperCase();
                const ket = ketMap[kond] ? ` — ${ketMap[kond]}` : '';
                document.getElementById('d-kondisi').innerHTML = `${dot}${label}${ket}`;
                const topik = d.master_kategori ? (d.master_kategori.nama || '-') : (d.master_kategori_masalah?.nama ||
                    d.master_kategori_masalah_id || '-');
                document.getElementById('d-kategori').innerText = topik || '-';
                document.getElementById('d-catatan').innerText = d.teks || '-';
                const gambarUrl = d.gambar_url || (d.gambar ? `${location.origin}/storage/${d.gambar}` : null);
                const gambar = gambarUrl ? `<a href="${gambarUrl}" target="_blank">Lihat Lampiran</a>` : '-';
                document.getElementById('d-gambar').innerHTML = gambar;
                bsDetail.show();
            } catch (err) {
                alert(err.message || 'Gagal membuka detail');
            }
        }

        // kategori checkboxes handled directly via checked state; no hidden field needed

        function paintKondisi() {
            const val = document.getElementById('m-kondisi').value.toLowerCase();
            const dot = document.getElementById('kondisi-dot');
            const map = {
                green: '#16a34a',
                yellow: '#f59e0b',
                orange: '#fb923c',
                red: '#ef4444',
                black: '#111827',
                grey: '#9ca3af',
                aman: '#16a34a'
            };
            if (dot) dot.style.background = map[val] || '#e5e7eb';
        }

        function openCreate() {
            document.getElementById('m-title').innerText = 'Tambah Observasi';
            document.getElementById('m-id').value = '';
            // lock date to today (cannot change in UI)
            const today = new Date();
            const y = today.getFullYear();
            const m = String(today.getMonth() + 1).padStart(2, '0');
            const d = String(today.getDate()).padStart(2, '0');
            const iso = `${y}-${m}-${d}`;
            document.getElementById('m-tanggal').value = iso;
            try {
                const f = new Intl.DateTimeFormat('id-ID', {
                    timeZone: 'Asia/Makassar',
                    weekday: 'long',
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric'
                }).format(today);
                document.getElementById('m-tanggal-display').innerText = f;
            } catch (_) {
                document.getElementById('m-tanggal-display').innerText = iso;
            }
            document.getElementById('m-siswakelas').value = '';
            document.getElementById('m-kondisi').value = "{{ $opsiKondisi[0] ?? 'aman' }}";
            document.getElementById('m-master-kategori').value = '';
            const file = document.getElementById('m-gambar');
            if (file) file.value = '';
            document.getElementById('m-catatan').value = '';
            document.getElementById('m-error').innerText = '';
            paintKondisi();
            document.getElementById('m-title').innerText = 'Tambah Observasi';

            // RESET BAGIAN GAMBAR
            resetGambarUI();
            bsModal.show();
        }

        async function openEdit(id) {
            document.getElementById('m-title').innerText = 'Edit Observasi';
            document.getElementById('m-id').value = id;
            document.getElementById('m-error').innerText = '';
            const file = document.getElementById('m-gambar');
            if (file) file.value = '';

            try {
                const res = await fetch(`${base}/${id}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                if (!res.ok) throw new Error('Gagal memuat data');
                const d = await res.json();
                fillFormFromData(d);
            } catch (err) {
                // fallback: try read from table row
                const tr = document.querySelector(`tr[data-id="${id}"]`);
                if (tr) {
                    const raw = tr.getAttribute('data-tanggal') || "";
                    document.getElementById('m-tanggal').value = raw;
                    try {
                        const t = raw ? new Date(raw) : null;
                        const f = t ? new Intl.DateTimeFormat('id-ID', {
                            timeZone: 'Asia/Makassar',
                            weekday: 'long',
                            day: '2-digit',
                            month: 'long',
                            year: 'numeric'
                        }).format(t) : '-';
                        document.getElementById('m-tanggal-display').innerText = f || raw || '-';
                    } catch (_) {
                        document.getElementById('m-tanggal-display').innerText = raw || '-';
                    }
                    document.getElementById('m-siswakelas').value = tr.querySelector('.td-siswakelas').dataset
                        .siswakelas;
                    document.getElementById('m-kondisi').value = tr.querySelector('.td-kondisi').innerText.trim()
                        .toLowerCase();
                    // no subkategori editing in new flow
                    document.getElementById('m-catatan').value = '';
                }
            }
            paintKondisi();
            bsModal.show();
        }

        // 3. Fungsi Tombol Hapus Gambar (di klik user)
        function hapusGambar() {
            if (!confirm('Yakin mau menghapus gambar ini?')) return;

            document.getElementById('preview-container').classList.add('d-none'); // Sembunyikan preview
            document.getElementById('m-hapus-gambar-flag').value = '1'; // Tandai buat backend
            document.getElementById('m-gambar').value = ''; // Reset input file
        }

        // 4. Helper buat reset UI (dipakai di openCreate)
        function resetGambarUI() {
            document.getElementById('preview-container').classList.add('d-none');
            document.getElementById('img-preview').src = '';
            document.getElementById('m-hapus-gambar-flag').value = '0';
            const f = document.getElementById('m-gambar');
            if (f) f.value = '';
        }

        function fillFormFromData(d) {
            // tanggal may be 'YYYY-mm-dd' or include time, slice to 10
            const tgl = (d.tanggal || '').slice(0, 10);
            const iso = tgl || "{{ now()->toDateString() }}";
            document.getElementById('m-tanggal').value = iso;
            try {
                const t = new Date(iso);
                const f = new Intl.DateTimeFormat('id-ID', {
                    timeZone: 'Asia/Makassar',
                    weekday: 'long',
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric'
                }).format(t);
                document.getElementById('m-tanggal-display').innerText = f;
            } catch (_) {
                document.getElementById('m-tanggal-display').innerText = iso;
            }
            const siswakelasId = d.siswa_kelas_id || d.siswa_kelas?.id || '';
            document.getElementById('m-siswakelas').value = String(siswakelasId);
            const kondisi = (d.kondisi_siswa || '').toLowerCase();
            if (kondisi) document.getElementById('m-kondisi').value = kondisi;
            document.getElementById('m-master-kategori').value = d.master_kategori_masalah_id || '';
            document.getElementById('m-catatan').value = d.teks || '';
            // LOGIKA GAMBAR
            const container = document.getElementById('preview-container');
            const img = document.getElementById('img-preview');
            const flag = document.getElementById('m-hapus-gambar-flag');
            const fileInput = document.getElementById('m-gambar');

            // Reset dulu
            flag.value = '0';
            fileInput.value = '';

            // Cek ada gambar gak dari database?
            // d.gambar_url didapat dari accessor model InputGuru
            if (d.gambar_url) {
                container.classList.remove('d-none'); // Munculin kotak preview
                img.src = d.gambar_url; // Set src gambar
            } else {
                container.classList.add('d-none'); // Umpetin kalau gak ada
                img.src = '';
            }
        }

        async function submitForm(e) {
            e.preventDefault();
            const id = document.getElementById('m-id').value;

            const fd = new FormData();
            fd.append('tanggal', document.getElementById('m-tanggal').value);
            fd.append('siswa_kelas_id', String(Number(document.getElementById('m-siswakelas').value)));
            fd.append('kondisi_siswa', document.getElementById('m-kondisi').value);
            const mk = document.getElementById('m-master-kategori').value;
            if (mk) fd.append('master_kategori_masalah_id', mk);
            const catatan = document.getElementById('m-catatan').value || '';
            fd.append('teks', catatan);
            const file = document.getElementById('m-gambar');
            const hapusFlag = document.getElementById('m-hapus-gambar-flag').value;

            // Client-side size check: max 2 MB (2048 KB) same as backend
            if (file && file.files && file.files[0]) {
                const maxBytes = 2048 * 1024;
                if (file.files[0].size > maxBytes) {
                    alert('Ukuran file tidak boleh lebih dari 2 MB.');
                    return;
                }
            }

            if (file && file.files && file.files[0]) {
                fd.append('gambar', file.files[0]); // Kalau user upload baru
            } else if (hapusFlag === '1') {
                fd.append('hapus_gambar', '1'); // Kalau user klik hapus
            }
            const res = await fetch(id ? `${base}/${id}` : base, {
                method: id ? 'POST' : 'POST', // we'll override with _method for PUT
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: (function() {
                    if (id) fd.append('_method', 'PUT');
                    return fd;
                })()
            });

            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                // handle duplicate (409) by prompting to edit existing
                if (res.status === 409 && data && data.existing_id) {
                    const proceed = confirm((data.message || 'Data duplikat untuk hari ini.') +
                        '\nBuka dan edit data yang sudah ada?');
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

        function prefillFromQuery() {
            const sp = new URLSearchParams(window.location.search);

            const clearDeepLinkParams = () => {
                let changed = false;
                ['open_edit_id', 'open_create', 'siswa_kelas_id'].forEach((k) => {
                    if (sp.has(k)) {
                        sp.delete(k);
                        changed = true;
                    }
                });
                if (!changed) return;
                const qs = sp.toString();
                const newUrl = qs ? `${window.location.pathname}?${qs}` : window.location.pathname;
                try {
                    history.replaceState({}, '', newUrl);
                } catch (_) {
                    // ignore
                }
            };

            const openEditId = sp.get('open_edit_id');
            if (openEditId) {
                const id = Number(openEditId);
                if (Number.isFinite(id) && id > 0) {
                    openEdit(id);
                    clearDeepLinkParams();
                    return;
                }
            }

            const openCreateFlag = sp.get('open_create');
            const siswaKelasId = sp.get('siswa_kelas_id');
            if (openCreateFlag && siswaKelasId) {
                const skId = Number(siswaKelasId);
                if (Number.isFinite(skId) && skId > 0) {
                    openCreate();
                    const sel = document.getElementById('m-siswakelas');
                    if (sel) sel.value = String(skId);

                    clearDeepLinkParams();
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            paintKondisi();
            const sel = document.getElementById('m-kondisi');
            if (sel) sel.addEventListener('change', paintKondisi);

            // Support deep-link from Slot Konseling table
            prefillFromQuery();
        });
    </script>
@endpush
