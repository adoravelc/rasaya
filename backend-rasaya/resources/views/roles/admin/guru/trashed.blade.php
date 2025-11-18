@extends('layouts.admin')

@section('title', 'Guru Terhapus')

@section('page-header')
<div class="d-flex align-items-center gap-2">
  <h1 class="h5 m-0">🗑️ Data Guru Terhapus</h1>
  <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.guru.index') }}">Kembali</a>
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
        <th>Jenis</th>
        <th class="text-end">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($gurus as $i => $g)
      <tr>
        <td>{{ $gurus->firstItem() + $i }}</td>
        <td>{{ $g->user?->identifier }}</td>
        <td>{{ $g->user?->name }}</td>
        <td>{{ $g->user?->email }}</td>
        <td><span class="badge bg-{{ $g->jenis==='bk'?'info':'success' }}">{{ strtoupper(str_replace('_',' ',$g->jenis)) }}</span></td>
        <td class="text-end">
          <form action="{{ route('admin.guru.restore', $g->user_id) }}" method="post" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-success" onclick="return confirm('Pulihkan data ini?')">Pulihkan</button>
          </form>
          <form action="{{ route('admin.guru.force', $g->user_id) }}" method="post" class="d-inline">
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
{{ $gurus->links('pagination::bootstrap-5') }}
@endsection
