{{-- resources/views/dashboards/admin.blade.php --}}
@extends('layouts.admin')

@section('title', 'Dashboard')

@section('page-header')
    <div>
        <h3 class="mb-1">Dashboard</h3>
    <div class="text-muted">Selamat datang kembali</div>
    </div>
@endsection

@section('content')
    @if (session('ok'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('ok') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-2">Ringkasan Cepat</h5>
                    <p class="text-muted small mb-3">Akses cepat menuju modul yang sering dipakai.</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('admin.kelas.index') }}" class="btn btn-primary">📚 Kelola Kelas</a>
                        <a href="{{ route('admin.kategori.index') }}" class="btn btn-outline-secondary">🗂️ Kelola
                            Kategori</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-2">Aktivitas Terakhir</h5>
                    <p class="text-muted small mb-0">Belum ada data.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
