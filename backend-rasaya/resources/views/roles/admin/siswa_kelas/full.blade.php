@extends('layouts.admin')

@section('title', 'Daftar Hadir Kelas (Full Page)')

@section('page-header')
<div class="d-flex w-100 align-items-center gap-2">
  <h1 class="h4 m-0">📋 Daftar Hadir Kelas</h1>
  <form method="get" class="ms-auto d-flex align-items-center gap-2">
    <label class="small text-muted">Tahun Ajaran</label>
    <select class="form-select form-select-sm" name="tahun_ajaran_id" onchange="this.form.submit()">
      @foreach($tahunAjarans as $ta)
        <option value="{{ $ta->id }}" @selected($activeTa==$ta->id)>{{ $ta->nama ?? ($ta->mulai.'/'.$ta->selesai) }}</option>
      @endforeach
    </select>
    <label class="small text-muted">Kelas</label>
    <select class="form-select form-select-sm" name="kelas_id" onchange="this.form.submit()">
      <option value="">Semua Kelas</option>
      @foreach($kelasOptions as $opt)
        <option value="{{ $opt->id }}" @selected(($kelasId ?? null)==$opt->id)>{{ $opt->label }}</option>
      @endforeach
    </select>
  </form>
  <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.siswa_kelas.index', ['tahun_ajaran_id' => $activeTa]) }}">Kembali ke Manajemen</a>
</div>
@endsection

@section('content')
<div class="row g-3">
  @forelse($kelas as $k)
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <strong>Kelas {{ $k->label }}</strong>
          <span class="text-muted">— Wali: {{ optional($k->waliGuru)->name ?? '-' }}</span>
        </div>
        <span class="badge text-bg-light">{{ ($assignments[$k->id] ?? collect())->count() }} siswa</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 64px">No</th>
                <th style="width: 140px">Identifier</th>
                <th>Nama</th>
                <th style="width: 260px">Email</th>
              </tr>
            </thead>
            <tbody>
              @php $rows = ($assignments[$k->id] ?? collect())->values(); @endphp
              @forelse($rows as $i => $ak)
                <tr>
                  <td>{{ $i+1 }}</td>
                  <td>{{ optional($ak->siswa->user)->identifier }}</td>
                  <td>{{ optional($ak->siswa->user)->name }}</td>
                  <td>{{ optional($ak->siswa->user)->email }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">Belum ada siswa pada kelas ini.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  @empty
  <div class="col-12">
    <div class="alert alert-info">Belum ada kelas untuk tahun ajaran ini.</div>
  </div>
  @endforelse
</div>
@endsection
