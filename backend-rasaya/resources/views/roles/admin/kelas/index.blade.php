{{-- resources/views/roles/admin/kelas/index.blade.php --}}
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Kelas — Admin</title>
    @vite(['resources/js/app.js'])
    <style>
        .sidebar {
            min-height: 100vh;
            border-right: 1px solid #e5e7eb;
            background: #f8f9fa;
        }

        .sidebar .nav-link.active {
            background: #e9ecef;
            font-weight: 600;
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
                        <a class="nav-link active" href="{{ route('admin.kelas.index') }}">📚 Manajemen Kelas</a>
                        <a class="nav-link disabled">👩‍🏫 Data Guru (segera)</a>
                        <a class="nav-link disabled">🧑‍🎓 Data Siswa (segera)</a>
                    </nav>
                </div>
            </aside>

            {{-- Content --}}
            <main class="col-12 col-md-9 col-lg-10 p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h3 class="mb-1">Manajemen Kelas</h3>
                        <div class="text-muted">Kelola tingkat, penjurusan, rombel, dan wali kelas.</div>
                    </div>
                    <button class="btn btn-primary" onclick="openCreate()">
                        + Tambah Kelas
                    </button>
                </div>

                {{-- Filter Tahun Ajaran --}}
                <form method="get" class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label">Tahun Ajaran</label>
                        <select name="tahun_ajaran_id" class="form-select" onchange="this.form.submit()">
                            @foreach ($tahunAjarans as $ta)
                                <option value="{{ $ta->id }}" {{ $activeTa == $ta->id ? 'selected' : '' }}>
                                    {{ $ta->nama }} {{ $ta->is_active ? '(aktif)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>

                {{-- Tabel --}}
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
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
                                        <tr data-id="{{ $k->id }}">
                                            <td>{{ $kelas->firstItem() + $i }}</td>
                                            <td class="td-label">{{ $k->label }}</td>
                                            <td class="td-tingkat">{{ $k->tingkat }}</td>
                                            <td class="td-jur">{{ $k->penjurusan ?? '-' }}</td>
                                            <td class="td-rombel">{{ $k->rombel }}</td>
                                            <td class="td-wali">{{ $k->waliGuru->name ?? '-' }}</td>
                                            <td class="actions">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary"
                                                        onclick="openEdit({{ $k->id }})">
                                                        Edit
                                                    </button>
                                                    <button class="btn btn-outline-danger"
                                                        onclick="doDelete({{ $k->id }})">
                                                        Hapus
                                                    </button>
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

    {{-- Modal Bootstrap --}}
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

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tahun Ajaran</label>
                                <select id="m-ta" class="form-select" required>
                                    @foreach ($tahunAjarans as $ta)
                                        <option value="{{ $ta->id }}">{{ $ta->nama }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tingkat</label>
                                <select id="m-tingkat" class="form-select" required>
                                    <option>X</option>
                                    <option>XI</option>
                                    <option>XII</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Penjurusan (opsional)</label>
                                <select id="m-penjurusan" class="form-select">
                                    <option value="">—</option>
                                    <option>IPA</option>
                                    <option>IPS</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Rombel (nomor)</label>
                                <input id="m-rombel" type="number" min="1" class="form-control" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Wali Guru (User ID)</label>
                                <input id="m-wali" type="number" class="form-control" placeholder="opsional">
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

    <script>
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const base = '/admin/kelas';
        const modalEl = document.getElementById('modal');
        let bsModal; // instance Bootstrap Modal

        document.addEventListener('DOMContentLoaded', () => {
            // window.bootstrap is available from Vite import 'bootstrap'
            bsModal = new bootstrap.Modal(modalEl, {
                backdrop: 'static'
            });
        });

        function openCreate() {
            document.getElementById('m-title').innerText = 'Tambah Kelas';
            document.getElementById('m-id').value = '';
            document.getElementById('m-ta').value = '{{ $activeTa }}';
            document.getElementById('m-tingkat').value = 'X';
            document.getElementById('m-penjurusan').value = '';
            document.getElementById('m-rombel').value = '';
            document.getElementById('m-wali').value = '';
            document.getElementById('m-error').innerText = '';
            bsModal.show();
        }

        function openEdit(id) {
            const tr = document.querySelector(`tr[data-id="${id}"]`);
            document.getElementById('m-title').innerText = 'Edit Kelas';
            document.getElementById('m-id').value = id;
            document.getElementById('m-ta').value = '{{ $activeTa }}';
            document.getElementById('m-tingkat').value = tr.querySelector('.td-tingkat').innerText.trim();
            document.getElementById('m-penjurusan').value =
                (tr.querySelector('.td-jur').innerText.trim() === '-' ? '' :
                    tr.querySelector('.td-jur').innerText.trim());
            document.getElementById('m-rombel').value = tr.querySelector('.td-rombel').innerText.trim();
            document.getElementById('m-wali').value = '';
            document.getElementById('m-error').innerText = '';
            bsModal.show();
        }

        async function submitForm(e) {
            e.preventDefault();
            const id = document.getElementById('m-id').value;

            const payload = {
                tahun_ajaran_id: document.getElementById('m-ta').value,
                tingkat: document.getElementById('m-tingkat').value,
                penjurusan: document.getElementById('m-penjurusan').value || null,
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
                document.getElementById('m-error').innerText = JSON.stringify(data.errors ?? data, null, 2);
                return;
            }
            bsModal.hide();
            location.reload();
        }

        async function doDelete(id) {
            if (!confirm('Hapus kelas?')) return;
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
</body>

</html>
