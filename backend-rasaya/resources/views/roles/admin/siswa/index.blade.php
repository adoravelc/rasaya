@extends('layouts.admin')

@section('title', 'Manajemen Siswa')

@section('page-header')
<div class="d-flex align-items-center gap-2">
    <h1 class="h4 m-0">👨‍🎓 Manajemen Siswa</h1>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddSiswa">Tambah Siswa</button>
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
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditSiswa" data-user="{{ json_encode(['id'=>$s->user->id,'identifier'=>$s->user->identifier,'name'=>$s->user->name,'email'=>$s->user->email]) }}">Edit</button>
                    <form action="{{ route('admin.siswa.destroy', $s->user->id) }}" method="post" class="d-inline" onsubmit="return confirm('Arsipkan siswa ini?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Arsipkan</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-5">Belum ada data siswa.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $siswas->links() }}
</div>

<!-- Modal Tambah Siswa -->
<div class="modal fade" id="modalAddSiswa" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="{{ route('admin.siswa.store') }}">
      @csrf
      <div class="modal-header"><h5 class="modal-title">Tambah Siswa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Identifier (NISN)</label><input name="identifier" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Password (opsional)</label><input name="password" type="password" class="form-control" placeholder="default: password123"></div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary" type="submit">Simpan</button></div>
    </form>
  </div>
  </div>

<!-- Modal Edit Siswa -->
<div class="modal fade" id="modalEditSiswa" tabindex="-1">
  <div class="modal-dialog">
    <form id="formEditSiswa" class="modal-content" method="post">
      @csrf @method('PUT')
      <div class="modal-header"><h5 class="modal-title">Edit Siswa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Identifier</label><input name="identifier" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Password (isi jika ganti)</label><input name="password" type="password" class="form-control"></div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary" type="submit">Update</button></div>
    </form>
  </div>
</div>

@push('scripts')
<script>
document.getElementById('modalEditSiswa')?.addEventListener('show.bs.modal', (ev)=>{
  const btn = ev.relatedTarget;
  const data = JSON.parse(btn.getAttribute('data-user'));
  const form = document.getElementById('formEditSiswa');
  form.action = `{{ url('/admin/siswa') }}/${data.id}`;
  form.querySelector('[name=identifier]').value = data.identifier;
  form.querySelector('[name=name]').value = data.name;
  form.querySelector('[name=email]').value = data.email;
});
</script>
@endpush
@endsection
