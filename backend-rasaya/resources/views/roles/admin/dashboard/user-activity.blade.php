@extends('layouts.admin')

@section('title', 'User Activity Detail')

@section('page-header')
<div class="d-flex align-items-center justify-content-between">
    <h1 class="h4 m-0">👤 Aktivitas User: {{ $user->name }}</h1>
    <a href="{{ route('admin.dashboard.login-history') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>
@endsection

@section('content')
{{-- User Info Card --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="text-center">
                    <div class="fs-1 text-primary mb-2">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    @if($user->role === 'admin')
                        <span class="badge bg-danger">Admin</span>
                    @elseif($user->role === 'guru')
                        <span class="badge bg-primary">Guru</span>
                    @else
                        <span class="badge bg-success">Siswa</span>
                    @endif
                </div>
            </div>
            <div class="col-md-9">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th width="150">Nama:</th>
                        <td><strong>{{ $user->name }}</strong></td>
                    </tr>
                    <tr>
                        <th>Identifier:</th>
                        <td>{{ $user->identifier }}</td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td>{{ $user->email }}</td>
                    </tr>
                    <tr>
                        <th>Akun Dibuat:</th>
                        <td>{{ $user->created_at->format('d M Y H:i') }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Date Filter --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <a href="{{ route('admin.dashboard.user-activity', $user->id) }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    {{-- All Activities --}}
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">📋 Semua Aktivitas ({{ count($activities) }} records)</h5>
            </div>
            <div class="card-body p-0">
                @if(count($activities) > 0)
                    <div class="list-group list-group-flush">
                        @foreach($activities as $activity)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="badge 
                                            @if($activity['type'] === 'Refleksi Siswa') bg-primary
                                            @elseif($activity['type'] === 'Mood Tracking') bg-warning
                                            @elseif($activity['type'] === 'Booking Konseling') bg-success
                                            @elseif($activity['type'] === 'Observasi Guru') bg-info
                                            @else bg-secondary
                                            @endif
                                        ">
                                            {{ $activity['type'] }}
                                        </span>
                                        <strong class="ms-2">{{ \Carbon\Carbon::parse($activity['date'])->format('d M Y') }}</strong>
                                    </div>
                                    <small class="text-muted">{{ $activity['created_at']->diffForHumans() }}</small>
                                </div>
                                <div class="mt-2 small">
                                    {{ $activity['description'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2">Tidak ada aktivitas untuk periode yang dipilih</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Login History Sidebar --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">🕒 Riwayat Login</h5>
            </div>
            <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                @if($loginHistory->count() > 0)
                    <div class="list-group list-group-flush">
                        @foreach($loginHistory as $login)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="small">
                                            <i class="bi bi-box-arrow-in-right text-success"></i>
                                            <strong>{{ $login->logged_in_at->format('d/m H:i') }}</strong>
                                        </div>
                                        @if($login->logged_out_at)
                                            <div class="small text-muted">
                                                <i class="bi bi-box-arrow-left"></i>
                                                {{ $login->logged_out_at->format('d/m H:i') }}
                                            </div>
                                        @else
                                            <span class="badge bg-warning small">Aktif</span>
                                        @endif
                                    </div>
                                    <div class="text-end">
                                        @if($login->logged_out_at)
                                            <small class="text-muted">
                                                {{ $login->logged_in_at->diffForHumans($login->logged_out_at, true) }}
                                            </small>
                                        @endif
                                    </div>
                                </div>
                                @if($login->ip_address)
                                    <small class="text-muted d-block mt-1">
                                        <i class="bi bi-globe"></i> {{ $login->ip_address }}
                                    </small>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-3 text-center text-muted">
                        <p class="mb-0">Tidak ada riwayat login</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
