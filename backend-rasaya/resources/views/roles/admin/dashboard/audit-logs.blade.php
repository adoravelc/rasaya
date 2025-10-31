@extends('layouts.admin')

@section('title', 'Audit Logs')

@section('page-header')
<div class="d-flex align-items-center justify-content-between">
    <h1 class="h4 m-0">📝 Audit Logs - Perubahan Data (Read-Only)</h1>
    <a href="{{ route('admin.dashboard.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
    </a>
</div>
@endsection

@section('content')
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong>Read-Only Mode:</strong> Halaman ini hanya untuk melihat riwayat perubahan data penting. 
    Tidak ada aksi edit/delete yang tersedia untuk menjaga integritas audit trail.
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold">Tipe Data</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">Semua Tipe</option>
                    <option value="users" {{ $type == 'users' ? 'selected' : '' }}>Users</option>
                    <option value="kelas" {{ $type == 'kelas' ? 'selected' : '' }}>Kelas</option>
                    <option value="siswa_kelas" {{ $type == 'siswa_kelas' ? 'selected' : '' }}>Siswa-Kelas</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Cari</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Nama/Email/Keyword" value="{{ $search }}">
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
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="{{ route('admin.dashboard.audit-logs') }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Audit Logs Table --}}
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">📜 Log Perubahan Data ({{ count($logs) }} total records)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="120">Waktu</th>
                        <th width="100">Tipe</th>
                        <th width="100">Aksi</th>
                        <th>Deskripsi</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pagedLogs as $log)
                        <tr>
                            <td class="small">
                                {{ $log['timestamp']->format('d/m/Y H:i') }}
                            </td>
                            <td>
                                @if($log['type'] === 'User')
                                    <span class="badge bg-primary">{{ $log['type'] }}</span>
                                @elseif($log['type'] === 'Kelas')
                                    <span class="badge bg-success">{{ $log['type'] }}</span>
                                @elseif($log['type'] === 'Siswa-Kelas')
                                    <span class="badge bg-warning">{{ $log['type'] }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ $log['type'] }}</span>
                                @endif
                            </td>
                            <td>
                                @if($log['action'] === 'Created')
                                    <span class="badge bg-success">{{ $log['action'] }}</span>
                                @elseif($log['action'] === 'Updated')
                                    <span class="badge bg-info">{{ $log['action'] }}</span>
                                @elseif($log['action'] === 'Assigned')
                                    <span class="badge bg-primary">{{ $log['action'] }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ $log['action'] }}</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $log['description'] }}</strong>
                            </td>
                            <td class="small text-muted">
                                {{ $log['details'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Tidak ada log yang sesuai dengan filter
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Info Box --}}
<div class="card mt-4 border-warning">
    <div class="card-body">
        <h6 class="text-warning"><i class="bi bi-shield-lock"></i> Catatan Audit Trail</h6>
        <ul class="small mb-0">
            <li>Log ini menampilkan perubahan pada data penting: Users, Kelas, Siswa-Kelas assignments</li>
            <li>Untuk sistem yang lebih robust, pertimbangkan package audit trail seperti <code>spatie/laravel-activitylog</code></li>
            <li>Semua timestamp menggunakan zona waktu server: {{ config('app.timezone') }}</li>
            <li>Data soft-deleted tidak ditampilkan di log ini</li>
        </ul>
    </div>
</div>
@endsection
