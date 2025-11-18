@extends('layouts.admin')

@section('title', 'Dashboard Admin')

@section('page-header')
<div class="d-flex align-items-center justify-content-between">
    <h1 class="h4 m-0">📊 Dashboard Admin</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.dashboard.login-history') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-clock-history"></i> Login History
        </a>
        <a href="{{ route('admin.dashboard.audit-logs') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-journal-text"></i> Audit Logs
        </a>
    </div>
</div>
@endsection

@section('content')
@if(isset($resetRequests) && $resetRequests->count())
<div class="alert alert-warning d-flex align-items-start justify-content-between" role="alert">
    <div>
        <div class="fw-semibold mb-1">Ada {{ $resetRequests->count() }} permohonan reset password</div>
        <ul class="mb-0 small">
            @foreach($resetRequests as $r)
                <li>
                    <span class="badge bg-dark text-uppercase">{{ $r->role }}</span>
                    <strong>{{ $r->name }}</strong> ({{ $r->identifier }})
                    — diminta {{ optional($r->reset_requested_at)->diffForHumans() }}
                    <form action="{{ route('admin.users.reset-password', $r->id) }}" method="post" class="d-inline ms-2" onsubmit="return confirm('Reset password untuk {{ $r->name }}?')">
                        @csrf
                        <button class="btn btn-sm btn-outline-dark">Reset sekarang</button>
                    </form>
                    <a class="btn btn-sm btn-outline-secondary ms-1" href="{{ route('admin.users.index', ['q' => $r->identifier]) }}">Lihat di Manajemen User</a>
                </li>
            @endforeach
        </ul>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.users.index', ['role' => 'guru']) }}">Kelola</a>
    </div>
@endif

{{-- Quick Stats Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Total Users</p>
                        <h3 class="mb-0">{{ $stats['total_users'] }}</h3>
                        <small class="text-muted">
                            {{ $stats['total_siswa'] }} siswa, {{ $stats['total_guru'] }} guru
                        </small>
                    </div>
                    <div class="fs-1 text-primary opacity-50">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Login Hari Ini</p>
                        <h3 class="mb-0">{{ $stats['today_logins'] }}</h3>
                        <small class="text-muted">aktivitas pengguna</small>
                    </div>
                    <div class="fs-1 text-success opacity-50">
                        <i class="bi bi-door-open"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Refleksi Hari Ini</p>
                        <h3 class="mb-0">{{ $stats['today_refleksi_siswa'] + $stats['today_refleksi_guru'] }}</h3>
                        <small class="text-muted">
                            {{ $stats['today_refleksi_siswa'] }} siswa, {{ $stats['today_refleksi_guru'] }} guru
                        </small>
                    </div>
                    <div class="fs-1 text-warning opacity-50">
                        <i class="bi bi-journal-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Mood Tracking Hari Ini</p>
                        <h3 class="mb-0">{{ $stats['today_mood_tracking'] }}</h3>
                        <small class="text-muted">entri pemantauan emosi</small>
                    </div>
                    <div class="fs-1 text-info opacity-50">
                        <i class="bi bi-emoji-smile"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Summary Stats --}}
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">📈 Trend Input Refleksi (7 Hari Terakhir)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th class="text-end">Jumlah Input</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dailyTrend as $trend)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($trend->date)->format('d M Y') }}</td>
                                    <td class="text-end">
                                        <strong>{{ $trend->count }}</strong>
                                        <div class="progress" style="height: 5px; width: 100px; display: inline-block; margin-left: 10px;">
                                            <div class="progress-bar bg-primary" style="width: {{ min(100, ($trend->count / max($dailyTrend->max('count'), 1)) * 100) }}%"></div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">Tidak ada data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">😊 Distribusi Mood (30 Hari)</h5>
            </div>
            <div class="card-body">
                @forelse($moodDistribution as $mood)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Skor {{ $mood->skor }}/5</span>
                            <strong>{{ $mood->count }}</strong>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar {{ $mood->skor >= 4 ? 'bg-success' : ($mood->skor >= 3 ? 'bg-warning' : 'bg-danger') }}" 
                                 style="width: {{ ($mood->count / max($moodDistribution->sum('count'), 1)) * 100 }}%">
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted text-center">Tidak ada data mood tracking</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- All Time Stats --}}
<div class="row g-3 mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">📊 Statistik Global (Seluruh Waktu)</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="border-end">
                            <h4 class="text-primary">{{ $stats['total_refleksi_siswa'] }}</h4>
                            <p class="text-muted small mb-0">Total Refleksi Siswa</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h4 class="text-success">{{ $stats['total_refleksi_guru'] }}</h4>
                            <p class="text-muted small mb-0">Total Observasi Guru</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h4 class="text-warning">{{ $stats['total_mood_tracking'] }}</h4>
                            <p class="text-muted small mb-0">Total Mood Tracking</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-info">{{ $stats['total_konseling_bookings'] }}</h4>
                        <p class="text-muted small mb-0">Total Booking Konseling</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Recent Activity --}}
<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">🔍 Analisis Terbaru</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Siswa</th>
                                <th>Kelas</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentAnalyses as $analisis)
                                <tr>
                                    <td>{{ optional(optional($analisis->siswaKelas)->siswa->user)->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            {{ optional($analisis->siswaKelas)->kelas->label ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="small text-muted">{{ $analisis->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Belum ada analisis</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">📅 Booking Konseling Aktif</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Siswa</th>
                                <th>Konselor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeBookings as $booking)
                                <tr>
                                    <td class="small">{{ optional(optional($booking->siswaKelas)->siswa->user)->name ?? '-' }}</td>
                                    <td class="small">{{ optional($booking->slot)->guru->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-success small">{{ $booking->status }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Belum ada booking aktif</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
