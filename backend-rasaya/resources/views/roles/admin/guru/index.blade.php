@extends('layouts.admin')

@section('title', 'Manajemen Guru')

@section('page-header')
<div class="d-flex align-items-center gap-2">
    <h1 class="h4 m-0">👩‍🏫 Manajemen Guru</h1>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddGuru">Tambah Guru</button>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.guru.trashed') }}">Data Terhapus</a>
    <form class="ms-auto d-flex gap-2" method="get">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari nama/identifier/email" value="{{ $q }}">
        <select name="jenis" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Semua Jenis</option>
            <option value="bk" @selected($jenis==='bk')>BK</option>
            <option value="wali_kelas" @selected($jenis==='wali_kelas')>Wali Kelas</option>
        </select>
        <button class="btn btn-sm btn-outline-secondary" type="submit">Cari</button>
    </form>
</div>
<div class="small text-muted mt-2">
  Keterangan: <span class="badge bg-info">BK</span> <span class="badge bg-success">WALI KELAS</span>
  — gunakan filter untuk menyaring.
</div>
@endsection

@section('content')
{{-- Notifications handled by layout toasts --}}

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>#</th>
                <th>Username</th>
                <th>Nama</th>
                <th>Email</th>
            <th>Jenis Kelamin</th>
                <th>Jenis</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gurus as $i => $g)
            <tr>
                <td>{{ $gurus->firstItem() + $i }}</td>
                <td>{{ $g->user->identifier }}</td>
                <td>{{ $g->user->name }}</td>
                <td>{{ $g->user->email }}</td>
            <td>
              @php $jk = $g->user->jenis_kelamin; @endphp
              @if($jk === 'L')
                <span class="badge bg-primary">Laki-laki</span>
              @elseif($jk === 'P')
                <span class="badge" style="background-color:#ec4899;">Perempuan</span>
              @else
                <span class="text-muted small">-</span>
              @endif
            </td>
                <td><span class="badge bg-{{ $g->jenis==='bk'?'info':'success' }}">{{ strtoupper(str_replace('_',' ',$g->jenis)) }}</span></td>
                <td class="text-end">
              @php($canReset = (bool)($g->user->reset_requested_at ?? null))
              <form action="{{ route('admin.users.reset-password', $g->user->id) }}" method="post" class="d-inline" onsubmit="return confirm('Reset password untuk user ini? Password baru akan digenerate otomatis.')">
                @csrf
                <button class="btn btn-sm btn-warning me-1" {{ $canReset ? '' : 'disabled' }}>Reset Password</button>
              </form>
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditGuru" data-user="{{ json_encode(['id'=>$g->user->id,'identifier'=>$g->user->identifier,'name'=>$g->user->name,'email'=>$g->user->email,'jenis'=>$g->jenis,'jenis_kelamin'=>$g->user->jenis_kelamin,'reset_requested_at'=>$g->user->reset_requested_at], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) }}">Edit</button>
                    <form action="{{ route('admin.guru.destroy', $g->user->id) }}" method="post" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                        @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                </td>
            </tr>
            @empty
          <tr><td colspan="7" class="text-center text-muted py-5">Belum ada data guru.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $gurus->links() }}

<!-- Modal Tambah Guru -->
<div class="modal fade" id="modalAddGuru" tabindex="-1">
  <div class="modal-dialog">
    <form id="formAddGuru" class="modal-content" method="post" action="{{ route('admin.guru.store') }}">
      @csrf
      <div class="modal-header"><h5 class="modal-title">Tambah Guru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        {{-- Errors shown via global toast --}}
        <div class="mb-2"><label class="form-label">Username</label><input name="identifier" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required autocomplete="off"></div>
        <div class="mb-2"><label class="form-label">Jenis Kelamin</label>
          <select name="jenis_kelamin" class="form-select" required>
            <option value="L">Laki-laki</option>
            <option value="P">Perempuan</option>
          </select>
        </div>
        <div class="mb-2"><label class="form-label">Jenis</label>
            <select name="jenis" class="form-select" required>
                <option value="bk">BK</option>
                <option value="wali_kelas">Wali Kelas</option>
            </select>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary" type="submit">Simpan</button></div>
    </form>
  </div>
</div>

<!-- Modal Edit Guru -->
<div class="modal fade" id="modalEditGuru" tabindex="-1">
  <div class="modal-dialog">
    <form id="formEditGuru" class="modal-content" method="post">
      @csrf @method('PUT')
      <div class="modal-header"><h5 class="modal-title">Edit Guru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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
        <div class="mb-2"><label class="form-label">Jenis</label>
            <select name="jenis" class="form-select" required>
                <option value="bk">BK</option>
                <option value="wali_kelas">Wali Kelas</option>
            </select>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary" type="submit">Update</button></div>
    </form>
  </div>
</div>

@push('scripts')
<script>
document.getElementById('modalEditGuru')?.addEventListener('show.bs.modal', (ev)=>{
  const btn = ev.relatedTarget || window.rasayaLastModalTrigger;
  if (!btn) return;
  const data = JSON.parse(btn.getAttribute('data-user'));
  const form = document.getElementById('formEditGuru');
  form.action = `{{ url('/admin/guru') }}/${data.id}`;
  form.querySelector('[name=identifier]').value = data.identifier;
  form.querySelector('[name=name]').value = data.name;
  form.querySelector('[name=email]').value = data.email;
  form.querySelector('[name=jenis]').value = data.jenis;
  if (data.jenis_kelamin) {
    form.querySelector('[name=jenis_kelamin]').value = data.jenis_kelamin;
  }
});
</script>
@endpush
@endsection
