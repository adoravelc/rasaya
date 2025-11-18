@extends('layouts.admin')

@section('title', 'Login History')

@section('page-header')
<div class="d-flex align-items-center justify-content-between">
    <h1 class="h4 m-0">🕒 Login History - Aktivitas User</h1>
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
                <h6 class="text-muted mb-1">Total Login (All Time)</h6>
                <h3 class="mb-0">{{ number_format($summary['total_logins']) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body">
                <h6 class="text-muted mb-1">Login Hari Ini</h6>
                <h3 class="mb-0">{{ number_format($summary['today_logins']) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body">
                <h6 class="text-muted mb-1">Sesi Aktif (Belum Logout)</h6>
                <h3 class="mb-0">{{ number_format($summary['active_sessions']) }}</h3>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold">Role</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">Semua Role</option>
                    <option value="admin" {{ $role == 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="guru" {{ $role == 'guru' ? 'selected' : '' }}>Guru</option>
                    <option value="siswa" {{ $role == 'siswa' ? 'selected' : '' }}>Siswa</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Cari User</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Nama/Email/Identifier" value="{{ $search }}">
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
                <a href="{{ route('admin.dashboard.login-history') }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Login History Table --}}
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">📜 Riwayat Login ({{ $histories->total() }} records)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Waktu Login</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>IP Address</th>
                        <th>User Agent</th>
                        <th>Waktu Logout</th>
                        <th>Durasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($histories as $history)
                        <tr>
                            <td class="small">
                                <strong>{{ $history->logged_in_at->format('d/m/Y H:i') }}</strong>
                            </td>
                            <td>
                                <div>{{ $history->user->name }}</div>
                                <small class="text-muted">{{ $history->user->identifier }}</small>
                            </td>
                            <td>
                                @if($history->user->role === 'admin')
                                    <span class="badge bg-danger">Admin</span>
                                @elseif($history->user->role === 'guru')
                                    @php($jenis = optional($history->user->guru)->jenis)
                                    @if($jenis === 'bk')
                                        <span class="badge bg-primary">Guru BK</span>
                                    @elseif($jenis === 'wali_kelas')
                                        <span class="badge bg-info text-dark">Wali Kelas</span>
                                    @else
                                        <span class="badge bg-primary">Guru</span>
                                    @endif
                                @else
                                    <span class="badge bg-success">Siswa</span>
                                @endif
                            </td>
                            <td class="small">{{ $history->ip_address ?? '-' }}</td>
                            <td class="small" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $history->user_agent }}">
                                {{ $history->user_agent ?? '-' }}
                            </td>
                            <td class="small">
                                @if($history->logged_out_at)
                                    {{ $history->logged_out_at->format('d/m/Y H:i') }}
                                @else
                                    <span class="badge bg-warning">Masih Login</span>
                                @endif
                            </td>
                            <td class="small">
                                @if($history->logged_out_at)
                                    {{ $history->logged_in_at->diffForHumans($history->logged_out_at, true) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.dashboard.user-activity', $history->user_id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Tidak ada riwayat login yang sesuai filter
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($histories->hasPages())
        <div class="card-footer">
            {{ $histories->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
