@extends('layouts.admin')

@section('title', 'Manajemen Guru')

@section('page-header')
<div class="d-flex align-items-center gap-2">
    <h1 class="h4 m-0">👩‍🏫 Manajemen Guru</h1>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddGuru">Tambah Guru</button>
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
                <th>Identifier</th>
                <th>Nama</th>
                <th>Email</th>
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
                <td><span class="badge bg-{{ $g->jenis==='bk'?'info':'success' }}">{{ strtoupper(str_replace('_',' ',$g->jenis)) }}</span></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditGuru" data-user="{{ json_encode(['id'=>$g->user->id,'identifier'=>$g->user->identifier,'name'=>$g->user->name,'email'=>$g->user->email,'jenis'=>$g->jenis]) }}">Edit</button>
                    <form action="{{ route('admin.guru.destroy', $g->user->id) }}" method="post" class="d-inline" onsubmit="return confirm('Arsipkan guru ini?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Arsipkan</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Belum ada data guru.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $gurus->links() }}

<!-- Modal Tambah Guru -->
<div class="modal fade" id="modalAddGuru" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="{{ route('admin.guru.store') }}">
      @csrf
      <div class="modal-header"><h5 class="modal-title">Tambah Guru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        {{-- Errors shown via global toast --}}
        <div class="mb-2"><label class="form-label">Identifier (NIP/NIK)</label><input name="identifier" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Password (opsional)</label><input name="password" type="password" class="form-control" placeholder="default: password123"></div>
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
        <div class="mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Password (isi jika ganti)</label><input name="password" type="password" class="form-control"></div>
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
  const btn = ev.relatedTarget;
  const data = JSON.parse(btn.getAttribute('data-user'));
  const form = document.getElementById('formEditGuru');
  form.action = `{{ url('/admin/guru') }}/${data.id}`;
  form.querySelector('[name=identifier]').value = data.identifier;
  form.querySelector('[name=name]').value = data.name;
  form.querySelector('[name=email]').value = data.email;
  form.querySelector('[name=jenis]').value = data.jenis;
});
</script>
@endpush
@endsection
