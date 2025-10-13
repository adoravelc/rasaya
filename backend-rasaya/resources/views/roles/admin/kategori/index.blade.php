{{-- resources/views/roles/admin/kategori/index.blade.php --}}
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Kategori — Admin</title>
    @vite(['resources/js/app.js'])
    <style>
        .sidebar {
            min-height: 100vh;
            border-right: 1px solid #e5e7eb;
            background: #f8f9fa
        }

        .sidebar .nav-link.active {
            background: #e9ecef;
            font-weight: 600
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="{{ route('admin.dashboard') }}">RASAYA Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="topbar">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item me-3 text-muted small">
                        Halo, <strong>{{ auth()->user()->name }}</strong>
                        <span class="d-none d-sm-inline">({{ auth()->user()->identifier }})</span>
                    </li>
                    <li class="nav-item">
                        <form method="POST" action="{{ route('logout') }}"
                            onsubmit="return confirm('Yakin ingin logout?')">
                            @csrf
                            <button class="btn btn-outline-danger btn-sm" type="submit">Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            {{-- Sidebar --}}
            <aside class="col-12 col-md-3 col-lg-2 p-0 sidebar">
                <div class="p-3">
                    <div class="text-uppercase text-muted fw-semibold small mb-2">Menu</div>
                    <nav class="nav nav-pills flex-column gap-1">
                        <a class="nav-link" href="{{ route('admin.dashboard') }}">🏠 Dashboard</a>
                        <a class="nav-link" href="{{ route('admin.kelas.index') }}">📚 Manajemen Kelas</a>
                        <a class="nav-link active" href="{{ route('admin.kategori.index') }}">🗂️ Manajemen Kategori</a>
                        <a class="nav-link disabled">👩‍🏫 Data Guru (segera)</a>
                        <a class="nav-link disabled">🧑‍🎓 Data Siswa (segera)</a>
                    </nav>
                </div>
            </aside>

            {{-- Content --}}
            <main class="col-12 col-md-9 col-lg-10 p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h3 class="mb-1">Manajemen Kategori</h3>
                        <div class="text-muted">Kelola kode unik, nama, deskripsi, dan status aktif.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" onchange="location.href='?aktif='+this.value"
                            style="width:180px">
                            <option value="" {{ request('aktif') === null ? 'selected' : '' }}>Semua status
                            </option>
                            <option value="1" {{ request('aktif') === '1' ? 'selected' : '' }}>Aktif</option>
                            <option value="0" {{ request('aktif') === '0' ? 'selected' : '' }}>Nonaktif</option>
                        </select>
                        <button class="btn btn-primary" onclick="openCreate()">+ Tambah Kategori</button>
                    </div>
                </div>

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
                                                    <input class="form-check-input toggle-active" type="checkbox"
                                                        role="switch" data-id="{{ $k->id }}"
                                                        {{ $k->is_active ? 'checked' : '' }}>
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
                                        <button class="btn btn-outline-danger"
                                            onclick="forceDel({{ $t->id }})">Hapus Permanen</button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </main>
        </div>
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
                        <input type="hidden" id="m-id">
                        <div class="mb-3">
                            <label class="form-label">Kode</label>
                            <input id="m-kode" class="form-control" maxlength="10" required>
                            <div class="form-text">Huruf/angka tanpa spasi (mis: AKD, EMO).</div>
                        </div>
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

    <script>
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const base = '/admin/kategori';
        const modalEl = document.getElementById('modal');
        let bsModal;

        document.addEventListener('DOMContentLoaded', () => {
            bsModal = new bootstrap.Modal(modalEl, {
                backdrop: 'static'
            });
        });

        function openCreate() {
            document.getElementById('m-title').innerText = 'Tambah Kategori';
            document.getElementById('m-id').value = '';
            document.getElementById('m-kode').value = '';
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
            document.getElementById('m-kode').value = tr.querySelector('.td-kode').innerText.trim();
            document.getElementById('m-nama').value = tr.querySelector('.td-nama').innerText.trim();
            document.getElementById('m-deskripsi').value = tr.querySelector('.td-deskripsi').innerText.trim() === '—' ? '' :
                tr.querySelector('.td-deskripsi').innerText.trim();
            document.getElementById('m-active').checked = tr.querySelector('.td-is_active .badge')?.classList.contains(
                'text-bg-success');
            document.getElementById('m-error').innerText = '';
            bsModal.show();
        }

        async function submitForm(e) {
            e.preventDefault();
            const id = document.getElementById('m-id').value;
            const payload = {
                kode: document.getElementById('m-kode').value.trim(),
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
                        e.target.checked = !e.target.checked; // rollback UI
                    }
                });
            });
        });
    </script>
</body>

</html>
