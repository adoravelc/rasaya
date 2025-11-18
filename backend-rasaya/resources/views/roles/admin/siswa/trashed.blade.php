@extends('layouts.admin')

@section('title', 'Siswa Terhapus')

@section('page-header')
<div class="d-flex align-items-center gap-2">
  <h1 class="h5 m-0">🗑️ Data Siswa Terhapus</h1>
  <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.siswa.index') }}">Kembali</a>
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
        <th class="text-end">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($siswas as $i => $s)
      <tr>
        <td>{{ $siswas->firstItem() + $i }}</td>
        <td>{{ $s->user?->identifier }}</td>
        <td>{{ $s->user?->name }}</td>
        <td>{{ $s->user?->email }}</td>
        <td class="text-end">
          <form action="{{ route('admin.siswa.restore', $s->user_id) }}" method="post" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-success" onclick="return confirm('Pulihkan data ini?')">Pulihkan</button>
          </form>
          <form action="{{ route('admin.siswa.force', $s->user_id) }}" method="post" class="d-inline">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-danger" onclick="return confirm('Hapus permanen?')">Hapus Permanen</button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="5" class="text-center text-muted py-5">Kosong.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
{{ $siswas->links('pagination::bootstrap-5') }}
@endsection
