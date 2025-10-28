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
        @php
          $student = optional($row->siswaKelas->siswa->user);
          $kelas = optional($row->siswaKelas->kelas)->label;
          $taObj = optional($row->siswaKelas->tahunAjaran);
          $tahunAjaran = $taObj->nama ?? $taObj->tahun ?? '';
          $rank = ['low'=>1,'medium'=>2,'high'=>3,'severe'=>4];
          $pool = $row->rekomendasis->where('status','accepted');
          if ($pool->isEmpty()) { $pool = $row->rekomendasis->where('status','suggested'); }
          $sevVal = 0; $sevLabel = null;
          foreach ($pool as $rec) { $v = $rank[strtolower($rec->severity ?? '')] ?? 0; if ($v > $sevVal) { $sevVal = $v; $sevLabel = $rec->severity; } }
          $tags = collect($row->rekomendasis)->flatMap(function($r){ return (array) optional($r->master)->tags; })->filter()->unique()->take(6)->values();
        @endphp

        <a href="{{ route('guru.analisis.show', $row->id) }}" class="list-group-item list-group-item-action">
          <div class="d-flex w-100 justify-content-between">
            <div>
              <h6 class="mb-1">{{ $student->name }} <span class="text-muted">({{ $student->identifier }})</span></h6>
              <div class="small text-muted">Kelas: <strong>{{ $kelas ?: '-' }}</strong>@if($tahunAjaran) — TA <strong>{{ $tahunAjaran }}</strong>@endif</div>
            </div>
            <div class="text-end">
              <div class="small text-muted">{{ optional($row->updated_at)->diffForHumans() }}</div>
              @if($sevLabel)
                <span class="badge text-bg-danger">{{ ucfirst($sevLabel) }}</span>
              @else
                <span class="badge text-bg-secondary">Netral</span>
              @endif
            </div>
          </div>
          <div class="mt-1 small">
            <span class="text-muted">Rentang:</span>
            {{ \Illuminate\Support\Carbon::parse($row->tanggal_awal_proses)->toDateString() }} —
            {{ \Illuminate\Support\Carbon::parse($row->tanggal_akhir_proses)->toDateString() }}
          </div>
          @if($tags->isNotEmpty())
            <div class="mt-1 small">
              <span class="text-muted">Kelompok masalah:</span>
              @foreach($tags as $t)
                <span class="badge text-bg-light">{{ $t }}</span>
              @endforeach
            </div>
          @endif
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
