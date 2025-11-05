@extends('layouts.admin')

@section('title', 'Manajemen Kategori')

@section('page-header')
    <div>
        <h3 class="mb-1">Manajemen Kategori</h3>
    <div class="text-muted">Kelola kode, nama, deskripsi, dan status aktif.</div>
    </div>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm" onchange="location.href='?aktif='+this.value" style="width:180px">
            <option value="" {{ request('aktif') === null ? 'selected' : '' }}>Semua status</option>
            <option value="1" {{ request('aktif') === '1' ? 'selected' : '' }}>Aktif</option>
            <option value="0" {{ request('aktif') === '0' ? 'selected' : '' }}>Nonaktif</option>
        </select>
        <button class="btn btn-primary" onclick="openCreate()">+ Tambah Kategori</button>
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
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Deskripsi</th>
                            <th>Status</th>
                            <th style="width:160px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rows">
                        @forelse ($rows as $i => $k)
                            <tr data-id="{{ $k->id }}">
                                <td>{{ $rows->firstItem() + $i }}</td>
                                <td class="td-kode">{{ $k->kode }}</td>
                                <td class="td-nama">{{ $k->nama }}</td>
                                <td class="td-deskripsi">{{ $k->deskripsi ?? '—' }}</td>
                                <td class="td-is_active">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input toggle-active" type="checkbox" role="switch"
                                            data-id="{{ $k->id }}" {{ $k->is_active ? 'checked' : '' }}>
                                    </div>
                                </td>
                                <td class="actions">
                                    <div class="btn-group btn-group-sm">
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
                        <div class="mb-3">
                            <label class="form-label">Nama</label>
                            <input id="m-nama" class="form-control" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi (opsional)</label>
                            <textarea id="m-deskripsi" class="form-control" rows="2" maxlength="255"></textarea>
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
@endsection

@push('scripts')
    <script>
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const base = '/admin/kategori';
        const modalEl = document.getElementById('modal');
        let bsModal;

        document.addEventListener('DOMContentLoaded', () => {
            bsModal = new bootstrap.Modal(modalEl, {
                backdrop: 'static'
            });

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

        function openCreate() {
            document.getElementById('m-title').innerText = 'Tambah Kategori';
            document.getElementById('m-id').value = '';
            document.getElementById('m-nama').value = '';
            document.getElementById('m-deskripsi').value = '';
            document.getElementById('m-active').checked = true;
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
            const payload = {
                nama: document.getElementById('m-nama').value.trim(),
                deskripsi: document.getElementById('m-deskripsi').value.trim() || null,
                is_active: document.getElementById('m-active').checked ? 1 : 0,
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
    </script>
@endpush
