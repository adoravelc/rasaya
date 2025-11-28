@extends('layouts.guru')

@section('title','Refleksi Siswa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h3 class="mb-0">Refleksi Siswa</h3>
  
  {{-- Form Filter --}}
  <form class="d-flex gap-2 align-items-end flex-wrap" method="get" action="{{ route('guru.refleksi.index') }}" style="max-width:100%">
    
    {{-- Filter Kelas (Hanya BK) --}}
    @if($guruJenis === 'bk')
      <div class="d-flex flex-column">
        <label class="form-label small mb-1">Kelas</label>
        {{-- onchange submit agar dropdown siswa terisi sesuai kelas yang dipilih --}}
        <select name="kelas_id" class="form-select form-select-sm" style="width:200px;" onchange="this.form.submit()">
          <option value="">— Pilih Kelas —</option>
          @foreach($kelasOptions as $k)
            <option value="{{ $k['id'] }}" @selected(($filters['kelas_id'] ?? '') == (string)$k['id'])>{{ $k['label'] }}</option>
          @endforeach
        </select>
      </div>
    @endif

    {{-- Filter Siswa (Muncul jika Wali Kelas ATAU BK sudah pilih kelas) --}}
    @if($siswaOptions->isNotEmpty())
      <div class="d-flex flex-column">
        <label class="form-label small mb-1">Siswa</label>
        <select name="siswa_id" class="form-select form-select-sm" style="width:180px;">
          <option value="">— Semua Siswa —</option>
          @foreach($siswaOptions as $s)
            <option value="{{ $s['id'] }}" @selected(($filters['siswa_id'] ?? '') == (string)$s['id'])>{{ $s['label'] }}</option>
          @endforeach
        </select>
      </div>
    @endif

    {{-- Filter Jenis --}}
    <div class="d-flex flex-column">
      <label class="form-label small mb-1">Jenis</label>
      <select name="jenis" class="form-select form-select-sm" style="width:140px;">
        <option value="">Semua</option>
        <option value="pribadi" @selected(($filters['jenis'] ?? '')==='pribadi')>Refleksi Pribadi</option>
        <option value="teman" @selected(($filters['jenis'] ?? '')==='teman')>Laporan Teman</option>
      </select>
    </div>

    {{-- Pencarian Teks --}}
    <div class="d-flex flex-column">
      <label class="form-label small mb-1">Cari</label>
      <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Nama / Isi..." style="width:180px;">
    </div>

    {{-- Tombol Aksi --}}
    <div class="d-flex align-items-end gap-2">
      <button class="btn btn-sm btn-primary">Filter</button>
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
            <th style="width:110px">Tanggal</th>
            <th>Siswa</th>
            <th>Jenis</th>
            <th>Isi Refleksi</th>
            <th>Pelapor (Konteks)</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            @php($isFriend = (bool)$r->is_friend)
            <tr>
              {{-- Tanggal Refleksi --}}
              <td class="small">
                <div>{{ $r->tanggal?->format('d M Y') }}</div>
                <div class="text-muted" style="font-size:0.75rem">{{ $r->created_at->format('H:i') }}</div>
              </td>
              
              {{-- Siswa Pembuat Refleksi / Yang Dilaporkan --}}
              <td>
                <div class="fw-bold">{{ optional(optional($r->siswaKelas)->siswa->user)->name ?? '-' }}</div>
                <div class="small text-muted">{{ optional(optional($r->siswaKelas)->siswa->user)->identifier ?? '-' }}</div>
              </td>

              {{-- Badge Jenis --}}
              <td>
                @if($isFriend)
                  <span class="badge bg-warning text-dark">Laporan Teman</span>
                @else
                  <span class="badge bg-info text-dark">Refleksi Pribadi</span>
                @endif
              </td>

              {{-- Isi Konten --}}
              <td class="small text-break" style="min-width: 250px;">
                {{ \Illuminate\Support\Str::limit($r->teks, 150) }}
              </td>

              {{-- Pelapor (Jika Laporan Teman) --}}
              <td>
                @if($isFriend)
                  {{-- Logic: InputSiswa milik 'siswaKelas' (pelapor), 'siswaDilaporKelas' adalah target --}}
                  {{-- Koreksi logic display: Biasanya table menampilkan Subject Utama. 
                       Jika Laporan Teman: Subject utama adalah PELAPOR atau KORBAN? 
                       Biasanya di tabel refleksi, kolom 'Siswa' adalah yang menulis (Pelapor). --}}
                   
                   {{-- Jika ingin menampilkan siapa yang dilaporkan: --}}
                   @if($r->siswaDilaporKelas)
                     <div class="small text-muted">Melaporkan:</div>
                     <div class="small fw-bold">{{ optional(optional($r->siswaDilaporKelas)->siswa->user)->name }}</div>
                   @else
                     <span class="text-muted">-</span>
                   @endif
                @else
                  <span class="text-muted small">-</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data refleksi yang sesuai filter.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="mt-3">{{ $rows->withQueryString()->links() }}</div>
@endsection