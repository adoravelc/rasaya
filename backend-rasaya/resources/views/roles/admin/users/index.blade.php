@extends('layouts.admin')

@section('title', 'Manajemen User')

@section('page-header')
    <div class="d-flex align-items-center gap-2">
        <h1 class="h4 m-0">👥 Manajemen User</h1>
        <form class="ms-auto d-flex gap-2" method="get">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari nama/identifier/email"
                value="{{ $q }}">
            <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="" @selected(!$role)>Semua Peran</option>
                <option value="guru" @selected($role === 'guru')>Guru</option>
                <option value="siswa" @selected($role === 'siswa')>Siswa</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary" type="submit">Cari</button>
        </form>
    </div>
    <div class="d-flex align-items-center justify-content-between small text-muted mt-2">
        <div>
            Keterangan: <span class="badge bg-info">BK</span> <span class="badge bg-success">WALI KELAS</span>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.users.trashed') }}">Data Terhapus</a>
    </div>
@endsection

@section('content')
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Identifier</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Peran</th>
                    <th>Jenis Kelamin</th>
                    <th>Keterangan</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $i => $u)
                    <tr>
                        <td>{{ $users->firstItem() + $i }}</td>
                        <td>{{ $u->identifier }}</td>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td><span class="badge bg-dark text-uppercase">{{ $u->role }}</span></td>
                        <td>
                            @if ($u->jenis_kelamin === 'L')
                                <span class="badge bg-primary">Laki-laki</span>
                            @elseif($u->jenis_kelamin === 'P')
                                <span class="badge bg-secondary">Perempuan</span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td>
                            @if ($u->role === 'guru')
                                @php($jenis = $u->guru?->jenis)
                                @if ($jenis === 'bk')
                                    <span class="badge bg-info">BK</span>
                                @elseif($jenis === 'wali_kelas')
                                    <span class="badge bg-success">WALI KELAS</span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            @elseif($u->role === 'siswa')
                                @php($kelas = $u->siswa?->kelass?->first())
                                @if ($kelas)
                                    @php($taName = $activeTa ? $activeTa->nama ?? $activeTa->mulai . '/' . $activeTa->selesai : '')
                                    <span
                                        class="badge bg-info">{{ $kelas->label }}{{ $taName ? ' (' . $taName . ')' : '' }}</span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @php($canReset = (bool) $u->reset_requested_at)
                            <form action="{{ route('admin.users.reset-password', $u->id) }}" method="post" class="d-inline"
                                onsubmit="return confirm('Reset password untuk user ini? Password baru akan digenerate otomatis.')">
                                @csrf
                                <button class="btn btn-sm btn-warning me-1" {{ $canReset ? '' : 'disabled' }}>Reset
                                    Password</button>
                            </form>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#modalEditUser"
                                data-user="{{ json_encode(
                                    [
                                        'id' => $u->id,
                                        'role' => $u->role,
                                        'identifier' => $u->identifier,
                                        'name' => $u->name,
                                        'email' => $u->email,
                                        'jenis_kelamin' => $u->jenis_kelamin,
                                        'jenis' => $u->guru?->jenis,
                                        'reset_requested_at' => $u->reset_requested_at,
                                    ],
                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP,
                                ) }}">
                                Edit
                            </button>
                            @if ($u->role === 'guru')
                                <form action="{{ route('admin.guru.destroy', $u->id) }}" method="post" class="d-inline"
                                    onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            @elseif($u->role === 'siswa')
                                <form action="{{ route('admin.siswa.destroy', $u->id) }}" method="post" class="d-inline"
                                    onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            @endif
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                data-bs-target="#modalDetailUser"
                                data-user="{{ json_encode(
                                    [
                                        'id' => $u->id,
                                        'role' => $u->role,
                                        'identifier' => $u->identifier,
                                        'name' => $u->name,
                                        'email' => $u->email,
                                        'jenis_kelamin' => $u->jenis_kelamin,
                                        'jenis' => $u->guru?->jenis,
                                        'kelas' => $u->siswa?->kelass?->first()?->label,
                                        'initial_password' => $u->initial_password,
                                        'password_changed_at' => $u->password_changed_at,
                                        'reset_requested_at' => $u->reset_requested_at,
                                    ],
                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP,
                                ) }}"
                                data-decrypted="{{ $u->initial_password ? \Illuminate\Support\Facades\Crypt::decryptString($u->initial_password) : '' }}">
                                Detail
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">Tidak ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links('pagination::bootstrap-5') }}
@endsection

@push('scripts')
    <script>
        document.getElementById('modalEditUser')?.addEventListener('show.bs.modal', (ev) => {
            const btn = ev.relatedTarget || window.rasayaLastModalTrigger;
            if (!btn) return;
            const data = JSON.parse(btn.getAttribute('data-user'));
            const form = document.getElementById('formEditUser');
            const roleInput = form.querySelector('[name=_role]');
            roleInput.value = data.role;
            // action per-role
            if (data.role === 'guru') {
                form.action = `{{ url('/admin/guru') }}/${data.id}`;
                document.getElementById('groupJenisGuru').classList.remove('d-none');
                // ensure select exists and has name for guru
                const jenisSelect = form.querySelector('#groupJenisGuru select');
                if (jenisSelect) {
                    jenisSelect.setAttribute('name', 'jenis');
                    jenisSelect.value = data.jenis || 'bk';
                }
            } else if (data.role === 'siswa') {
                form.action = `{{ url('/admin/siswa') }}/${data.id}`;
                // hide and remove name to avoid sending to siswa endpoint
                document.getElementById('groupJenisGuru').classList.add('d-none');
                const jenisSelect = form.querySelector('#groupJenisGuru select');
                if (jenisSelect) jenisSelect.removeAttribute('name');
            } else {
                // fallback: disable submit
                form.action = '#';
                document.getElementById('groupJenisGuru').classList.add('d-none');
            }
            form.querySelector('[name=identifier]').value = data.identifier || '';
            form.querySelector('[name=name]').value = data.name || '';
            form.querySelector('[name=email]').value = data.email || '';
            if (data.jenis_kelamin) {
                form.querySelector('[name=jenis_kelamin]').value = data.jenis_kelamin;
            }
        });

        // Ensure jenis only sent for guru
        document.getElementById('formEditUser')?.addEventListener('submit', (e) => {
            const form = e.target;
            const role = form.querySelector('[name=_role]').value;
            const jenisSelect = form.querySelector('#groupJenisGuru select');
            if (role !== 'guru' && jenisSelect) {
                jenisSelect.removeAttribute('name');
            } else if (role === 'guru' && jenisSelect) {
                jenisSelect.setAttribute('name', 'jenis');
            }
        });

        // Detail User modal presenter
        document.getElementById('modalDetailUser')?.addEventListener('show.bs.modal', (ev) => {
            const trigger = ev.relatedTarget || window.rasayaLastModalTrigger;
            if (!trigger) return;
            const data = JSON.parse(trigger.getAttribute('data-user'));
            const el = document.getElementById('detailUserBody');
            const roleLabel = data.role === 'guru' ? 'Guru' : (data.role === 'siswa' ? 'Siswa' : data.role);
            const jk = data.jenis_kelamin === 'L' ? 'Laki-laki' : (data.jenis_kelamin === 'P' ? 'Perempuan' : '-');
            const info = [];
            info.push(`<div><strong>Identifier:</strong> ${data.identifier || '-'}</div>`);
            info.push(`<div><strong>Nama:</strong> ${data.name || '-'}</div>`);
            info.push(`<div><strong>Email:</strong> ${data.email || '-'}</div>`);
            info.push(`<div><strong>Peran:</strong> ${roleLabel}</div>`);
            info.push(`<div><strong>Jenis Kelamin:</strong> ${jk}</div>`);
            if (data.role === 'guru') {
                info.push(`<div><strong>Jenis Guru:</strong> ${data.jenis || '-'}</div>`);
            }
            if (data.role === 'siswa') {
                info.push(`<div><strong>Kelas (TA aktif):</strong> ${data.kelas || '-'}</div>`);
            }
            // Password reveal logic
            let pwHtml = '';
            if (data.password_changed_at) {
                pwHtml =
                    `<div><strong>Password:</strong> <span class="text-muted">••••••••</span> <small class="text-muted">(User ini sudah mengubah password)</small></div>`;
            } else if (data.initial_password) {
                pwHtml =
                    `<div><strong>Token password:</strong> <button type="button" class="btn btn-sm btn-outline-primary" id="revealPwdBtn">Klik untuk tampilkan</button> <span id="pwdVal" class="ms-2"></span></div>`;
            } else {
                const rr = data.reset_requested_at ? '<span class="badge bg-warning ms-1">Meminta reset</span>' :
                '';
                pwHtml =
                    `<div><strong>Token password:</strong> <span class="text-muted">tidak tersedia</span> <small class="text-muted">(User lama; admin dapat reset password)</small> ${rr}</div>`;
            }
            el.innerHTML = info.join('') + pwHtml;
            if (!data.password_changed_at && data.initial_password) {
                document.getElementById('revealPwdBtn')?.addEventListener('click', () => {
                    const pv = document.getElementById('pwdVal');
                    const btn = window.rasayaLastModalTrigger;
                    pv.textContent = (btn && btn.getAttribute('data-decrypted')) || '(mohon refresh)';
                });
            }
        });
    </script>
@endpush
<!-- Modal Edit User -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
    <div class="modal-dialog">
        <form id="formEditUser" class="modal-content" method="post">
            @csrf @method('PUT')
            <input type="hidden" name="_role" value="">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5><button type="button" class="btn-close"
                    data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">Identifier</label><input name="identifier"
                        class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Nama</label><input name="name" class="form-control"
                        required></div>
                <div class="mb-2"><label class="form-label">Email</label><input name="email" type="email"
                        class="form-control" required autocomplete="off"></div>
                <div class="mb-2"><label class="form-label">Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="form-select">
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
                <div id="groupJenisGuru" class="mb-2 d-none">
                    <label class="form-label">Jenis Guru</label>
                    <select class="form-select">
                        <option value="bk">BK</option>
                        <option value="wali_kelas">Wali Kelas</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal"
                    type="button">Batal</button><button class="btn btn-primary" type="submit">Update</button></div>
        </form>
    </div>
</div>

<!-- Modal Detail User -->
<div class="modal fade" id="modalDetailUser" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail User</h5><button type="button" class="btn-close"
                    data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailUserBody"></div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal"
                    type="button">Tutup</button></div>
        </div>
    </div>
</div>
