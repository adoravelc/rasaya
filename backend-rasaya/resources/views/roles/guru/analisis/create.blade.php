@extends('layouts.guru')
@section('title', 'Analisis Siswa — Buat')

@section('content')
    <div class="container py-4">
        <h4 class="mb-3">Buat Analisis Baru</h4>
        <form method="post" action="{{ route('guru.analisis.store') }}" class="card p-3">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Siswa</label>
                    <select name="siswa_kelas_id" class="form-select" required>
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
