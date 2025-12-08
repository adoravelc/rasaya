@extends('layouts.admin')

@section('title', 'Manajemen Siswa')

@section('page-header')
<div class="d-flex align-items-center gap-2">
    <h1 class="h4 m-0">👨‍🎓 Manajemen Siswa</h1>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddSiswa">Tambah Siswa</button>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.siswa.trashed') }}">Data Terhapus</a>
    <form class="ms-auto d-flex gap-2" method="get">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari nama/identifier/email" value="{{ $q }}">
        <button class="btn btn-sm btn-outline-secondary" type="submit">Cari</button>
    </form>
</div>
@endsection

@section('content')
{{-- Notifications handled by layout toasts --}}

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>#</th>
                <th>Identifier</th>
                <th>Nama</th>
                <th>Email</th>
            <th>Jenis Kelamin</th>
                <th>Kelas</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($siswas as $i => $s)
            <tr>
                <td>{{ $siswas->firstItem() + $i }}</td>
                <td>{{ $s->user->identifier }}</td>
                <td>{{ $s->user->name }}</td>
                <td>{{ $s->user->email }}</td>
            <td>
              @php $jk = $s->user->jenis_kelamin; @endphp
              @if($jk === 'L')
                <span class="badge bg-primary">Laki-laki</span>
              @elseif($jk === 'P')
                <span class="badge" style="background-color:#ec4899;">Perempuan</span>
              @else
                <span class="text-muted small">-</span>
              @endif
            </td>
                <td>
                    @if($s->kelass->isNotEmpty())
                        @php
                            $kelas = $s->kelass->first();
                            $kelasLabel = $kelas->label; // Using the label accessor: "XII IPS 1"
                            $taName = $activeTa ? ($activeTa->nama ?? ($activeTa->mulai . '/' . $activeTa->selesai)) : '';
                        @endphp
                        <span class="badge bg-info">{{ $kelasLabel }} ({{ $taName }})</span>
                    @else
                        <span class="text-muted small">-</span>
                    @endif
                </td>
                <td class="text-end">
              @php($canReset = (bool)($s->user->reset_requested_at ?? null))
              <form action="{{ route('admin.users.reset-password', $s->user->id) }}" method="post" class="d-inline" onsubmit="return confirm('Reset password untuk user ini? Password baru akan digenerate otomatis.')">
                @csrf
                <button class="btn btn-sm btn-warning me-1" {{ $canReset ? '' : 'disabled' }}>Reset Password</button>
              </form>
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditSiswa" data-user="{{ json_encode(['id'=>$s->user->id,'identifier'=>$s->user->identifier,'name'=>$s->user->name,'email'=>$s->user->email,'jenis_kelamin'=>$s->user->jenis_kelamin,'reset_requested_at'=>$s->user->reset_requested_at], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) }}">Edit</button>
              @if(($s->is_active ?? true))
                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalDeactivate-{{ $s->user->id }}">Nonaktifkan</button>
              @else
                <form action="{{ route('admin.siswa.activate', $s->user->id) }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">Aktifkan</button>
                </form>
              @endif
                    <form action="{{ route('admin.siswa.destroy', $s->user->id) }}" method="post" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                        @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                </td>
            </tr>
            @empty
          <tr><td colspan="7" class="text-center text-muted py-5">Belum ada data siswa.</td></tr>
            @endforelse
        </tbody>
    </table>
  {{ $siswas->links('pagination::bootstrap-5') }}
</div>

<!-- Modal Tambah Siswa -->
<div class="modal fade" id="modalAddSiswa" tabindex="-1">
  <div class="modal-dialog">
    <form id="formAddSiswa" class="modal-content" method="post" action="{{ route('admin.siswa.store') }}">
      @csrf
      <div class="modal-header"><h5 class="modal-title">Tambah Siswa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Identifier (NISN)</label><input name="identifier" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required autocomplete="off"></div>
        <div class="mb-2"><label class="form-label">Jenis Kelamin</label>
            <select name="jenis_kelamin" class="form-select" required>
                <option value="L">Laki-laki</option>
                <option value="P">Perempuan</option>
            </select>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary" type="submit">Simpan</button></div>
    </form>
  </div>
  </div>

@foreach($siswas as $i => $s)
  @if(($s->is_active ?? true))
  <div class="modal fade" id="modalDeactivate-{{ $s->user->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post" action="{{ route('admin.siswa.deactivate', $s->user->id) }}" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Nonaktifkan Siswa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">Alasan menonaktifkan <strong>{{ $s->user->name }}</strong>:</p>
          <input type="text" name="reason" class="form-control" required minlength="5" maxlength="255" placeholder="Contoh: keluar sekolah / pindah / dikeluarkan / lulus">
          <div class="form-text mt-1">Siswa akan dihapus dari kelas aktif (status keanggotaan nonaktif).</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Nonaktifkan</button>
        </div>
      </form>
    </div>
  </div>
  @endif
@endforeach

<!-- Modal Edit Siswa -->
<div class="modal fade" id="modalEditSiswa" tabindex="-1">
  <div class="modal-dialog">
    <form id="formEditSiswa" class="modal-content" method="post">
      @csrf @method('PUT')
      <div class="modal-header"><h5 class="modal-title">Edit Siswa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Identifier</label><input name="identifier" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required autocomplete="off"></div>
        <div class="mb-2"><label class="form-label">Jenis Kelamin</label>
            <select name="jenis_kelamin" class="form-select">
                <option value="L">Laki-laki</option>
                <option value="P">Perempuan</option>
            </select>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary" type="submit">Update</button></div>
    </form>
  </div>
</div>

@push('scripts')
<script>
document.getElementById('modalEditSiswa')?.addEventListener('show.bs.modal', (ev)=>{
  const btn = ev.relatedTarget || window.rasayaLastModalTrigger;
  if (!btn) return;
  const data = JSON.parse(btn.getAttribute('data-user'));
  const form = document.getElementById('formEditSiswa');
  form.action = `{{ url('/admin/siswa') }}/${data.id}`;
  form.querySelector('[name=identifier]').value = data.identifier;
  form.querySelector('[name=name]').value = data.name;
  form.querySelector('[name=email]').value = data.email;
  if (data.jenis_kelamin) {
    form.querySelector('[name=jenis_kelamin]').value = data.jenis_kelamin;
  }
});
</script>
@endpush
@endsection
