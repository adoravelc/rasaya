@extends('layouts.admin')

@section('title', 'User Terhapus')

@section('page-header')
<div class="d-flex align-items-center gap-2">
  <h1 class="h5 m-0">🗑️ Data User Terhapus</h1>
  <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.users.index') }}">Kembali</a>
  <form class="ms-auto d-flex gap-2" method="get">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari nama/identifier/email" value="{{ $q }}">
      <button class="btn btn-sm btn-outline-secondary" type="submit">Cari</button>
  </form>
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
        <td class="text-end">
          <form action="{{ route('admin.users.restore', $u->id) }}" method="post" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-success" onclick="return confirm('Pulihkan user ini?')">Pulihkan</button>
          </form>
          <form action="{{ route('admin.users.force', $u->id) }}" method="post" class="d-inline">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-danger" onclick="return confirm('Hapus permanen?')">Hapus Permanen</button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-center text-muted py-5">Kosong.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
{{ $users->links('pagination::bootstrap-5') }}
@endsection
