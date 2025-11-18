@extends('layouts.admin')

@section('title', 'History Refleksi')

@section('page-header')
<div class="d-flex align-items-center justify-content-between">
    <h1 class="h4 m-0">📓 History Refleksi Siswa</h1>
    <a href="{{ route('admin.dashboard.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
    </a>
</div>
@endsection

@section('content')
{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body">
                <h6 class="text-muted mb-1">Total Refleksi (All Time)</h6>
                <h3 class="mb-0">{{ number_format($summary['total']) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body">
                <h6 class="text-muted mb-1">Refleksi Hari Ini</h6>
                <h3 class="mb-0">{{ number_format($summary['today']) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body">
                <h6 class="text-muted mb-1">Laporan Teman (All Time)</h6>
                <h3 class="mb-0">{{ number_format($summary['friend_reports']) }}</h3>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Cari Siswa</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Nama/Identifier" value="{{ $search }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Kelas</label>
                <select name="kelas_id" class="form-select form-select-sm">
                    <option value="">Semua Kelas</option>
                    @foreach($kelasList as $k)
                        <option value="{{ $k->id }}" {{ (string)$kelasId === (string)$k->id ? 'selected' : '' }}>
                            {{ $k->tingkat }} {{ optional(\App\Models\Jurusan::find($k->jurusan_id))->nama }} {{ $k->rombel }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Jenis</label>
                <select name="jenis" class="form-select form-select-sm">
                    @php($optJenis = $jenis ?? 'all')
                    <option value="all" {{ $optJenis==='all' ? 'selected' : '' }}>Semua</option>
                    <option value="pribadi" {{ $optJenis==='pribadi' ? 'selected' : '' }}>Refleksi Pribadi</option>
                    <option value="teman" {{ $optJenis==='teman' ? 'selected' : '' }}>Laporan Teman</option>
                </select>
            </div>
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="{{ route('admin.dashboard.refleksi-history') }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">📜 Riwayat Refleksi ({{ $refleksis->total() }} records)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Pelapor</th>
                        <th>Kelas</th>
                        <th>Jenis</th>
                                <th>Keterangan</th>
                                <th>Ringkasan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($refleksis as $r)
                        <tr>
                            <td class="small"><strong>{{ optional($r->tanggal)->format('d/m/Y') }}</strong></td>
                            <td>
                                <div>{{ optional(optional($r->siswaKelas)->siswa->user)->name ?? '-' }}</div>
                                <small class="text-muted">{{ optional(optional($r->siswaKelas)->siswa->user)->identifier ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ optional($r->siswaKelas->kelas)->label ?? '-' }}</span>
                            </td>
                            <td>
                                @if($r->is_friend)
                                    <span class="badge bg-warning">Laporan Teman</span>
                                @else
                                    <span class="badge bg-primary">Refleksi Pribadi</span>
                                @endif
                            </td>
                                    <td class="small">
                                        @if($r->is_friend)
                                            @php($t = optional(optional($r->siswaDilaporKelas)->siswa->user))
                                            Laporan tentang: <strong>{{ $t->name ?? '-' }}</strong>
                                            @if(!empty($t->identifier))<div class="text-muted">{{ $t->identifier }}</div>@endif
                                        @else
                                            —
                                        @endif
                                    </td>
                            <td class="small" style="max-width:400px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $r->teks }}">{{ $r->teks }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Tidak ada data refleksi sesuai filter</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($refleksis->hasPages())
        <div class="card-footer">
            {{ $refleksis->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
