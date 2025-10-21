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
</div>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header">Tambah Siswa ke Kelas</div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.siswa_kelas.store') }}">
                    @csrf
                    <input type="hidden" name="tahun_ajaran_id" value="{{ $activeTa }}">
                    <div class="mb-2">
                        <label class="form-label">Kelas</label>
                        <select name="kelas_id" class="form-select" required>
                            @foreach($kelas as $k)
                            <option value="{{ $k->id }}">{{ $k->label }} — Wali: {{ optional($k->waliGuru)->name ?? '-' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Siswa</label>
                        <select name="siswa_id" class="form-select" required>
                            @foreach($siswas as $s)
                            <option value="{{ $s->user_id }}">{{ $s->user->identifier }} — {{ $s->user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="text-end"><button class="btn btn-primary">Tambah</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header">Siswa Aktif per Kelas ({{ count($kelas) }} kelas)</div>
            <div class="card-body">
                @foreach($kelas as $k)
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="m-0">Kelas {{ $k->label }} <span class="text-muted">— Wali: {{ optional($k->waliGuru)->name ?? '-' }}</span></h6>
                        </div>
                        @php
                            $list = $assignments->where('kelas_id', $k->id);
                        @endphp
                        @if($list->isEmpty())
                            <div class="text-muted small">Belum ada siswa.</div>
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach($list as $ak)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ optional($ak->siswa->user)->name }}</strong>
                                        <span class="text-muted">({{ optional($ak->siswa->user)->identifier }})</span>
                                    </div>
                                    <form method="post" action="{{ route('admin.siswa_kelas.remove') }}" onsubmit="return confirm('Keluarkan siswa dari kelas?')">
                                        @csrf
                                        <input type="hidden" name="tahun_ajaran_id" value="{{ $activeTa }}">
                                        <input type="hidden" name="kelas_id" value="{{ $k->id }}">
                                        <input type="hidden" name="siswa_id" value="{{ $ak->siswa_id }}">
                                        <button class="btn btn-sm btn-outline-danger">Keluarkan</button>
                                    </form>
                                </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
