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
            <div class="text-muted">Catat observasi ke siswa (terhubung ke <em>siswa_kelas</em>).</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" onclick="openCreate()">+ Tambah Observasi</button>
        </div>
    </div>

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
                            <th style="width:160px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rows">
                        @forelse($rows as $i => $r)
                            <tr data-id="{{ $r->id }}">
                                <td>{{ $rows->firstItem() + $i }}</td>
                                <td class="td-tanggal">{{ $r->tanggal->format('Y-m-d') }}</td>
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
                                <select id="m-kondisi" class="form-select" required>
                                    @foreach ($opsiKondisi as $opt)
                                        <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Kategori (boleh banyak)</label>
                                <select id="m-kategoris" multiple class="form-select">
                                    @foreach ($kategoris as $k)
                                        <option value="{{ $k->id }}">{{ $k->nama }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Gunakan Ctrl/Cmd untuk memilih lebih dari satu kategori.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea id="m-catatan" class="form-control" rows="3" maxlength="500"></textarea>
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
@endpush

@push('scripts')
    <script>
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const base = "{{ route('guru.observasi.index') }}".replace(/\/$/, ''); // /guru/observasi
        const modalEl = document.getElementById('modal');
        let bsModal;

        document.addEventListener('DOMContentLoaded', () => {
            bsModal = new bootstrap.Modal(modalEl, {
                backdrop: 'static'
            });
        });

        function valMulti(selectEl) {
            return Array.from(selectEl.selectedOptions).map(o => Number(o.value));
        }

        function openCreate() {
            document.getElementById('m-title').innerText = 'Tambah Observasi';
            document.getElementById('m-id').value = '';
            document.getElementById('m-tanggal').value = "{{ now()->toDateString() }}";
            document.getElementById('m-siswakelas').value = '';
            document.getElementById('m-kondisi').value = "{{ $opsiKondisi[0] ?? 'aman' }}";
            document.getElementById('m-kategoris').selectedIndex = -1;
            document.getElementById('m-catatan').value = '';
            document.getElementById('m-error').innerText = '';
            bsModal.show();
        }

        async function openEdit(id) {
            const tr = document.querySelector(`tr[data-id="${id}"]`);
            document.getElementById('m-title').innerText = 'Edit Observasi';
            document.getElementById('m-id').value = id;

            document.getElementById('m-tanggal').value = tr.querySelector('.td-tanggal').innerText.trim();
            document.getElementById('m-siswakelas').value = tr.querySelector('.td-siswakelas').dataset.siswakelas;
            document.getElementById('m-kondisi').value = tr.querySelector('.td-kondisi').innerText.trim().toLowerCase();
            document.getElementById('m-kategoris').selectedIndex = -
            1; // untuk kesederhanaan; edit kategori via show endpoint kalau mau
            document.getElementById('m-catatan').value = '';
            document.getElementById('m-error').innerText = '';
            bsModal.show();
        }

        async function submitForm(e) {
            e.preventDefault();
            const id = document.getElementById('m-id').value;

            const payload = {
                tanggal: document.getElementById('m-tanggal').value,
                siswa_kelas_id: Number(document.getElementById('m-siswakelas').value),
                kondisi_siswa: document.getElementById('m-kondisi').value,
                kategori_ids: valMulti(document.getElementById('m-kategoris')),
                catatan: document.getElementById('m-catatan').value || null,
                isi: document.getElementById('m-catatan').value || '',
                status_upload: 1, // final submit (1=dikirim)
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
    </script>
@endpush
