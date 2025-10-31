@extends('layouts.admin')

@section('title', 'Manajemen Data Siswa-Kelas')

@section('page-header')
<div class="d-flex w-100 align-items-center gap-2">
    <h1 class="h4 m-0">🧑‍🏫 Manajemen Siswa per Kelas</h1>
    <form method="get" class="ms-auto d-flex align-items-center gap-2">
        <label class="small text-muted">Tahun Ajaran</label>
        <select class="form-select form-select-sm" name="tahun_ajaran_id" onchange="this.form.submit()">
            @foreach($tahunAjarans as $ta)
                <option value="{{ $ta->id }}" @selected($activeTa==$ta->id)>{{ $ta->nama ?? ($ta->mulai.'/'.$ta->selesai) }}</option>
            @endforeach
        </select>
    </form>
    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.siswa_kelas.full', ['tahun_ajaran_id' => $activeTa]) }}">Lihat Daftar Seluruh Siswa</a>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.kelas.index', ['tahun_ajaran_id' => $activeTa]) }}">Kembali ke Manajemen Kelas</a>
</div>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Pilih Kelas: Hide when searching --}}
@if(!$search)
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="tahun_ajaran_id" value="{{ $activeTa }}">
            <div class="col-md-8">
                <label class="form-label fw-semibold">Pilih Kelas untuk Dikelola</label>
                <select name="kelas_id" class="form-select" onchange="this.form.submit()">
                    <option value="">— Pilih Kelas —</option>
                    @foreach($kelasOptions as $opt)
                        <option value="{{ $opt->id }}" @selected(($kelasId ?? null)==$opt->id)>
                            {{ $opt->label }} — Wali: {{ optional($opt->waliGuru)->name ?? '-' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Cari Siswa (di semua kelas)</label>
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Nama/Identifier" value="{{ $search }}">
                    <button class="btn btn-outline-secondary" type="submit">Cari</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Search results: Show ONLY when searching --}}
@if($search)
<div class="card mb-3">
    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
        <strong>Hasil Pencarian untuk "{{ $search }}"</strong> (di semua kelas)
        <a href="{{ route('admin.siswa_kelas.index', ['tahun_ajaran_id' => $activeTa, 'kelas_id' => $kelasId]) }}" class="btn btn-light btn-sm">
            <i class="bi bi-x-circle"></i> Tutup Pencarian
        </a>
    </div>
    <div class="card-body p-0">
        @if($searchResults->isEmpty())
            <div class="p-3 text-muted">Tidak ditemukan siswa dengan keyword tersebut.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Identifier</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($searchResults as $sr)
                        <tr>
                            <td>{{ optional($sr->siswa->user)->identifier }}</td>
                            <td>{{ optional($sr->siswa->user)->name }}</td>
                            <td><span class="badge bg-info">{{ optional($sr->kelas)->label }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endif

{{-- Main content: only when kelas selected AND not searching --}}
@if($selectedKelas && !$search)
<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header">Tambah Siswa ke {{ $selectedKelas->label }}</div>
            <div class="card-body">
                @if($availableSiswas->isEmpty())
                    <div class="alert alert-info small mb-0">
                        Semua siswa sudah terdaftar di kelas lain untuk Tahun Ajaran ini.
                    </div>
                @else
                    <form method="post" action="{{ route('admin.siswa_kelas.store') }}">
                        @csrf
                        <input type="hidden" name="tahun_ajaran_id" value="{{ $activeTa }}">
                        <input type="hidden" name="kelas_id" value="{{ $kelasId }}">
                        <div class="mb-2">
                            <label class="form-label">Siswa (belum terdaftar di kelas manapun)</label>
                            <select name="siswa_id" class="form-select" required>
                                <option value="">— Pilih Siswa —</option>
                                @foreach($availableSiswas as $s)
                                <option value="{{ $s->user_id }}">{{ $s->user->identifier }} — {{ $s->user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="text-end"><button class="btn btn-primary">Tambah</button></div>
                    </form>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                Siswa Aktif di {{ $selectedKelas->label }}
                <span class="badge text-bg-light">{{ $assignments->count() }} siswa</span>
            </div>
            <div class="card-body p-0">
                @if($assignments->isEmpty())
                    <div class="p-3 text-muted">Belum ada siswa di kelas ini.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 64px">No</th>
                                    <th style="width: 140px">Identifier</th>
                                    <th>Nama</th>
                                    <th style="width: 120px" class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($assignments as $i => $ak)
                                <tr>
                                    <td>{{ $i+1 }}</td>
                                    <td>{{ optional($ak->siswa->user)->identifier }}</td>
                                    <td>{{ optional($ak->siswa->user)->name }}</td>
                                    <td class="text-end">
                                        <form method="post" action="{{ route('admin.siswa_kelas.remove') }}" onsubmit="return confirm('Keluarkan siswa dari kelas?')" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="tahun_ajaran_id" value="{{ $activeTa }}">
                                            <input type="hidden" name="kelas_id" value="{{ $kelasId }}">
                                            <input type="hidden" name="siswa_id" value="{{ $ak->siswa_id }}">
                                            <button class="btn btn-sm btn-outline-danger">Keluarkan</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@else
<div class="alert alert-info">
    Pilih kelas dari dropdown di atas untuk mulai mengelola siswa-siswa di kelas tersebut.
</div>
@endif
@endsection
