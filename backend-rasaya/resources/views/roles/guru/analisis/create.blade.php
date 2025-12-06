@extends('layouts.guru')
@section('title', 'Analisis Siswa — Buat')

@section('content')
    <div class="container py-4">
        <h4 class="mb-3">Buat Analisis Baru</h4>
        
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Error:</strong> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        
        <form method="post" action="{{ route('guru.analisis.store') }}" class="card p-3">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Siswa</label>
                    <input type="text" id="siswa-search" class="form-control mb-2" placeholder="Cari nama atau NISN...">
                    <select name="siswa_kelas_id" id="siswa-select" class="form-select" required>
                        <option value="">— pilih —</option>
                        @foreach ($siswas as $sk)
                            @php
                                $user = auth()->user();
                                $jenis = optional($user->guru)->jenis;
                                $nama = $sk->siswa->user->name ?? 'Siswa';
                                $ident = $sk->siswa->user->identifier ?? '';
                                $kelas = optional($sk->kelas)->label ?? '';
                                $label = $jenis === 'wali_kelas'
                                    ? sprintf('%s (%s)', $nama, $ident)
                                    : sprintf('%s — %s (%s)', $nama, $kelas, $ident);
                            @endphp
                            <option value="{{ $sk->id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Dari</label>
                    <input type="date" name="from" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sampai</label>
                    <input type="date" name="to" class="form-control" required>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <a href="{{ route('guru.analisis.index') }}" class="btn btn-outline-secondary me-2">Batal</a>
                    <button class="btn btn-primary">Analisis</button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const search = document.getElementById('siswa-search');
    const select = document.getElementById('siswa-select');
    if (!search || !select) return;

    const options = Array.from(select.options);
    search.addEventListener('input', function() {
        const q = this.value.trim().toLowerCase();
        options.forEach(opt => {
            if (opt.value === '') return; // keep placeholder
            const text = opt.text.toLowerCase();
            opt.hidden = q.length > 0 ? !text.includes(q) : false;
        });
    });
});
</script>
@endpush
