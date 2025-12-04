@extends('layouts.admin')
@section('title','Revisi Request Rekomendasi')

@section('content')
<div class="container py-4">
    <h4>Revisi Request #{{ $req->id }}</h4>
    <div class="mb-2 text-muted">Kategori: {{ optional($req->kategoriMasalah)->nama ?? '-' }}</div>
    <form method="POST" action="{{ route('admin.rekomendasi.requests.update', $req->id) }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Judul</label>
            <input type="text" class="form-control" name="judul" value="{{ old('judul', $req->judul) }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Deskripsi</label>
            <textarea class="form-control" name="deskripsi" rows="5">{{ old('deskripsi', $req->deskripsi) }}</textarea>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Severity</label>
                <select class="form-select" name="severity">
                    @php $sev = old('severity', $req->severity ?: 'low'); @endphp
                    <option value="low" {{ $sev==='low'?'selected':'' }}>low</option>
                    <option value="medium" {{ $sev==='medium'?'selected':'' }}>medium</option>
                    <option value="high" {{ $sev==='high'?'selected':'' }}>high</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Minimal Skor Sentimen</label>
                <input type="number" step="0.01" min="-1" max="0" class="form-control" name="min_neg_score" value="{{ old('min_neg_score', is_null($req->min_neg_score)?-0.05:$req->min_neg_score) }}">
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.rekomendasi.requests') }}">Batal</a>
        </div>
    </form>
</div>
@endsection
