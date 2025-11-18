@extends('layouts.admin')

@section('title','Preview Import Roster')

@section('page-header')
<div>
  <h3 class="mb-1">Preview Import Roster</h3>
  <div class="text-muted">Tahun: {{ $year->nama ?? ($year->tahun_mulai.'/'.$year->tahun_selesai) }} — Mode: {{ strtoupper($mode) }} — Auto-create: {{ $autoCreate ? 'Ya' : 'Tidak' }}</div>
</div>
@endsection

@section('content')
@if(!empty($errors))
  <div class="alert alert-warning">
    <div class="fw-semibold">Peringatan pembacaan file:</div>
    <ul class="mb-0">
      @foreach($errors as $e)
        <li>{{ $e }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-3"><strong>Total Baris:</strong> {{ $summary['total'] }}</div>
      <div class="col-md-3"><strong>User Ditemukan:</strong> {{ $summary['found_users'] }}</div>
      <div class="col-md-3"><strong>Jurusan Dibuat:</strong> {{ $summary['created_jurusan'] }}</div>
      <div class="col-md-3"><strong>Kelas Dibuat:</strong> {{ $summary['created_kelas'] }}</div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>NIS</th>
            <th>Tingkat</th>
            <th>Jurusan</th>
            <th>Rombel</th>
            <th>Validasi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($validated as $i => $v)
            <tr>
              <td>{{ $i+1 }}</td>
              <td>{{ $v['row']['nis'] }}</td>
              <td>{{ $v['row']['tingkat'] }}</td>
              <td>{{ $v['row']['jurusan'] }}</td>
              <td>{{ $v['row']['rombel'] }}</td>
              <td>
                @if(empty($v['errors']))
                  <span class="badge bg-success">OK</span>
                @else
                  @foreach($v['errors'] as $err)
                    <div class="text-danger small">• {{ $err }}</div>
                  @endforeach
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<form class="mt-3" action="{{ route('admin.roster.commit') }}" method="post">
  @csrf
  <input type="hidden" name="token" value="{{ $token }}">
  <button class="btn btn-primary" {{ collect($validated)->contains(fn($v)=>!empty($v['errors'])) ? 'disabled' : '' }}>Commit Import</button>
  <a class="btn btn-secondary" href="{{ route('admin.roster.index') }}">Kembali</a>
</form>
@endsection
