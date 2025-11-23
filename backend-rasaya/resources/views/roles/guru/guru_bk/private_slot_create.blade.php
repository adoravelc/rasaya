@extends('layouts.guru')

@section('title','Jadwalkan Konseling Privat')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <main class="col-12 col-md-10 col-lg-8">
            <div class="card shadow-sm" style="border-left:4px solid var(--guru-pink);">
                <div class="card-body">
                    <h5 class="fw-semibold mb-2" style="color:var(--guru-pink-dark);">
                        <i class="bi bi-calendar-plus me-1"></i>Jadwalkan Konseling Privat
                    </h5>
                    <div class="mb-3 small text-muted">Siswa: <span class="fw-medium">{{ optional($siswaKelas->siswa->user)->name }}</span> • Kelas: <span class="fw-medium">{{ optional($siswaKelas->kelas)->label }}</span></div>
                    @if(session('error'))
                        <div class="alert alert-danger small">{{ session('error') }}</div>
                    @endif
                    <form method="post" action="{{ route('guru.guru_bk.private_slots.schedule', $referral->id) }}" class="row g-3">
                        @csrf
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-medium">Tanggal</label>
                            <input type="date" name="tanggal" class="form-control form-control-sm" value="{{ old('tanggal') }}" required>
                            @error('tanggal')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-medium">Waktu Mulai</label>
                            <input type="time" name="start_time" class="form-control form-control-sm" value="{{ old('start_time') }}" required>
                            @error('start_time')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-medium">Durasi (menit)</label>
                            <input type="number" min="10" max="240" name="durasi_menit" class="form-control form-control-sm" value="{{ old('durasi_menit',60) }}" required>
                            @error('durasi_menit')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-medium">Lokasi (opsional)</label>
                            <input type="text" name="lokasi" maxlength="191" class="form-control form-control-sm" value="{{ old('lokasi') }}">
                            @error('lokasi')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Catatan (opsional)</label>
                            <textarea name="notes" rows="3" class="form-control form-control-sm">{{ old('notes') }}</textarea>
                            @error('notes')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 d-flex gap-2 mt-2">
                            <a href="{{ route('guru.bk.dashboard') }}" class="btn btn-sm btn-outline-secondary">Batal</a>
                            <button class="btn btn-sm" style="background:#0b2e5f;color:#fff;border:1px solid #082245;"
                                onmouseover="this.style.background='#103a75'" onmouseout="this.style.background='#0b2e5f'">
                                <i class="bi bi-check2 me-1"></i>Jadwalkan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
@endsection
