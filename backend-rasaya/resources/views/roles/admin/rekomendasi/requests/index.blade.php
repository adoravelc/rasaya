@extends('layouts.admin')
@section('title','Request Tambahan Rekomendasi')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Request Tambahan Rekomendasi</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.rekomendasi.index') }}" class="btn btn-sm btn-outline-secondary">← Kembali ke Manajemen</a>
            <a href="{{ route('admin.rekomendasi.requests') }}" class="btn btn-sm btn-outline-secondary">Refresh</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif
    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kategori</th>
                    <th>Judul</th>
                    <th>Deskripsi</th>
                    <th>Severity</th>
                    <th>Min Neg Score</th>
                    <th>Status</th>
                    <th>Diajukan Oleh</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($requests as $r)
                <tr>
                    <td>{{ $r->id }}</td>
                    <td>{{ optional($r->kategori)->nama ?? '-' }}</td>
                    <td>{{ $r->judul ?? '-' }}</td>
                    <td style="max-width:420px">{{ $r->deskripsi }}</td>
                    <td>{{ $r->severity ?? '-' }}</td>
                    <td>{{ is_null($r->min_neg_score) ? '-' : number_format($r->min_neg_score, 2) }}</td>
                    <td>
                        @if($r->status === 'pending')
                            <span class="badge text-bg-warning">Pending</span>
                        @elseif($r->status === 'approved')
                            <span class="badge text-bg-success">Approved</span>
                        @else
                            <span class="badge text-bg-secondary">Rejected</span>
                        @endif
                    </td>
                    <td>{{ optional($r->requester)->name ?? 'Guru' }}</td>
                    <td class="d-flex gap-2">
                        @if($r->status === 'pending')
                        <form method="POST" action="{{ route('admin.rekomendasi.requests.admit', $r->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-success">Admit</button>
                        </form>
                        <a class="btn btn-sm btn-primary" href="{{ route('admin.rekomendasi.requests.edit', $r->id) }}">Revisi</a>
                        <form method="POST" action="{{ route('admin.rekomendasi.requests.reject', $r->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">Reject</button>
                        </form>
                        @else
                        <em class="text-muted">Tidak ada aksi</em>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $requests->links() }}
</div>
@endsection
