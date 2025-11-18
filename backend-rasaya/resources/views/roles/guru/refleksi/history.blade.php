@extends('layouts.guru')

@section('title','History Refleksi Siswa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">History Refleksi Siswa</h3>
  <form class="d-flex gap-2" method="get" action="{{ route('guru.bk.refleksi-history') }}">
    <select name="year_id" class="form-select form-select-sm" style="width: 220px;">
      @foreach($years as $y)
        <option value="{{ $y->id }}" @selected((int)$yearId === (int)$y->id)>{{ $y->nama ?? ($y->tahun_mulai.'/'.$y->tahun_selesai) }}</option>
      @endforeach
    </select>
    <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Cari nama/NIS/teks..." style="width: 260px;">
    <button class="btn btn-sm btn-primary">Filter</button>
  </form>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:80px">Tanggal</th>
            <th>Siswa</th>
            <th>Jenis</th>
            <th>Ringkas</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            <tr>
              <td>{{ $r->tanggal?->format('Y-m-d') }}</td>
              <td>{{ optional(optional($r->siswaKelas)->siswa->user)->name ?? '-' }} <span class="text-muted">({{ optional(optional($r->siswaKelas)->siswa->user)->identifier ?? '-' }})</span></td>
              <td>
                @if($r->is_friend)
                  <span class="badge bg-warning text-dark">Teman</span>
                @else
                  <span class="badge bg-info text-dark">Pribadi</span>
                @endif
              </td>
              <td>{{ \Illuminate\Support\Str::limit($r->teks, 80) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="mt-3">{{ $rows->withQueryString()->links() }}</div>
@endsection
