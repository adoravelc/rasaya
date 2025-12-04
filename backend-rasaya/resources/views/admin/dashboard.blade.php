@extends('layouts.admin')
@section('title','Dashboard Admin')

@section('content')
<div class="container py-4">
    <h4 class="mb-3">Dashboard Admin</h4>

    @if($pendingCount > 0)
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <div>
            <strong>{{ $pendingCount }}</strong> request rekomendasi tindakan menunggu review.
        </div>
        <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.rekomendasi.requests') }}">Tinjau Sekarang</a>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <p class="text-muted mb-0">Konten dashboard admin dapat ditambahkan di sini.</p>
        </div>
    </div>
</div>
@endsection
