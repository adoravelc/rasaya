@extends('layouts.guru')
@section('title', 'Analisis Siswa — Daftar')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Daftar Analisis</h4>
    <a href="{{ route('guru.analisis.create') }}" class="btn btn-primary">Buat Analisis</a>
  </div>

  <div class="card">
    <div class="list-group list-group-flush">
      @forelse($rows as $row)
        <a href="{{ route('guru.analisis.show', $row->id) }}" class="list-group-item list-group-item-action">
          <div class="d-flex w-100 justify-content-between">
            <h6 class="mb-1">Analisis #{{ $row->id }} • skor {{ $row->skor_sentimen }}</h6>
            <small class="text-muted">{{ optional($row->updated_at)->diffForHumans() }}</small>
          </div>
          <small class="text-muted">Rentang: {{ \Illuminate\Support\Carbon::parse($row->tanggal_awal_proses)->toDateString() }} — {{ \Illuminate\Support\Carbon::parse($row->tanggal_akhir_proses)->toDateString() }}</small>
        </a>
      @empty
        <div class="list-group-item text-muted">Belum ada analisis.</div>
      @endforelse
    </div>
    @if(method_exists($rows, 'links'))
      <div class="card-footer">{{ $rows->links() }}</div>
    @endif
  </div>
</div>
@endsection
