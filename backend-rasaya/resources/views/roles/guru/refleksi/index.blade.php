@extends('layouts.guru')

@section('title','Refleksi Siswa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h3 class="mb-0">Refleksi Siswa</h3>
  <form class="d-flex gap-2 align-items-end flex-wrap" method="get" action="{{ route('guru.refleksi.index') }}" style="max-width:100%">
    @if($guruJenis === 'bk')
      <div class="d-flex flex-column">
        <label class="form-label small mb-1">Kelas</label>
        <select name="kelas_id" class="form-select form-select-sm" style="width:220px;">
          <option value="">— Semua —</option>
          @foreach($kelasOptions as $k)
            <option value="{{ $k['id'] }}" @selected(($filters['kelas_id'] ?? '') == (string)$k['id'])>{{ $k['label'] }}</option>
          @endforeach
        </select>
      </div>
    @endif
    <div class="d-flex flex-column">
      <label class="form-label small mb-1">Jenis</label>
      <select name="jenis" class="form-select form-select-sm" style="width:160px;">
        <option value="">Semua</option>
        <option value="pribadi" @selected(($filters['jenis'] ?? '')==='pribadi')>Refleksi Pribadi</option>
        <option value="teman" @selected(($filters['jenis'] ?? '')==='teman')>Laporan Teman</option>
      </select>
    </div>
    <div class="d-flex flex-column">
      <label class="form-label small mb-1">Cari</label>
      <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Cari nama / NIS / kata" style="width:240px;">
    </div>
    <div class="d-flex align-items-end gap-2">
      <button class="btn btn-sm btn-primary">Terapkan</button>
      <a href="{{ route('guru.refleksi.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
    </div>
  </form>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:90px">Tanggal</th>
            <th>Siswa</th>
            <th>Jenis</th>
            <th>Isi</th>
            <th>Pelapor</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            @php($isFriend = (bool)$r->is_friend)
            <tr>
              <td>{{ $r->tanggal?->format('Y-m-d') }}</td>
              <td>{{ optional(optional($r->siswaKelas)->siswa->user)->name ?? '-' }} <span class="text-muted">({{ optional(optional($r->siswaKelas)->siswa->user)->identifier ?? '-' }})</span></td>
              <td>
                @if($isFriend)
                  <span class="badge bg-warning text-dark">Laporan Teman</span>
                @else
                  <span class="badge bg-info text-dark">Refleksi Pribadi</span>
                @endif
              </td>
              <td class="small" style="max-width:420px;white-space:pre-wrap;">{{ \Illuminate\Support\Str::limit($r->teks, 140) }}</td>
              <td>
                @if($isFriend)
                  {{-- Pelapor siswaKelas (teman) dan orang yang dilaporkan (siswaDilaporKelas) untuk konteks --}}
                  <span class="small">{{ optional(optional($r->siswaKelas)->siswa->user)->name ?? '-' }}</span>
                @else
                  <span class="small">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data refleksi.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="mt-3">{{ $rows->withQueryString()->links() }}</div>
@endsection
