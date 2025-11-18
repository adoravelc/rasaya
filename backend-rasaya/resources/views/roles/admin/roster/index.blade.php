@extends('layouts.admin')

@section('title','Import Roster Kelas')

@section('page-header')
<div>
  <h3 class="mb-1">Import Roster Kelas</h3>
  <div class="text-muted">Unggah CSV roster untuk membuat/memperbarui penempatan siswa per kelas.</div>
</div>
<div class="d-flex gap-2 align-items-center">
  <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.roster.template') }}">
    <i class="bi bi-download"></i> Download Template CSV
  </a>
</div>
@endsection

@section('content')
@if(session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $e)
        <li>{{ $e }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="card shadow-sm">
  <div class="card-body">
    <form action="{{ route('admin.roster.preview') }}" method="post" enctype="multipart/form-data" class="row g-3">
      @csrf
      <div class="col-md-4">
        <label class="form-label">Tahun Ajaran Tujuan</label>
        <select name="tahun_ajaran_id" class="form-select" required>
          @foreach($years as $y)
            <option value="{{ $y->id }}" @selected($activeYear && $activeYear->id===$y->id)>
              {{ $y->nama ?? ($y->tahun_mulai.'/'.$y->tahun_selesai) }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Mode</label>
        <select name="mode" class="form-select">
          <option value="merge">Merge (tambah/update saja)</option>
          <option value="replace">Replace (ganti seluruh roster tahun ini)</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Opsi</label>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="auto_create" value="1" id="ac">
          <label for="ac" class="form-check-label">Buat otomatis Jurusan/Kelas bila belum ada</label>
        </div>
      </div>
      <div class="col-12">
        <label class="form-label">File CSV</label>
        <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
        <div class="form-text">Header wajib: <code>nis,tingkat,jurusan,rombel</code>. Contoh tingkat: X/XI/XII atau 10/11/12.</div>
      </div>
      <div class="col-12">
        <button class="btn btn-primary">Preview</button>
      </div>
    </form>
  </div>
</div>
@endsection
