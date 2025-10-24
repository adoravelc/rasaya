@extends('layouts.guru')
@section('title', 'Hasil Analisis')

@section('content')
    <div class="container py-4">
        <a href="{{ route('guru.analisis.index') }}" class="btn btn-sm btn-outline-secondary mb-3">← Kembali</a>

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                        <div class="small text-muted">Siswa</div>
                        <div class="fw-semibold">
                            {{ optional($analisis->siswaKelas->siswa->user)->name }}
                            <span class="text-muted">({{ optional($analisis->siswaKelas->siswa->user)->identifier }})</span>
                        </div>
                        @unless($isWali)
                            <div class="small">
                                Kelas: <strong>{{ optional($analisis->siswaKelas->kelas)->label }}</strong>
                            </div>
                        @endunless
                    </div>
                </div>

                <h5 class="mb-1">Ringkasan</h5>
                <div class="text-muted small mb-2">
                    Rentang: {{ \Illuminate\Support\Carbon::parse($analisis->tanggal_awal_proses)->toDateString() }} —
                    {{ \Illuminate\Support\Carbon::parse($analisis->tanggal_akhir_proses)->toDateString() }}
                </div>
                <div>Skor Sentimen Rata-rata: <strong>{{ $analisis->skor_sentimen }}</strong></div>
                <div class="mt-2">
                    <span class="text-muted">Kata kunci:</span>
                    @foreach ($analisis->kata_kunci ?? [] as $kw)
                        <span class="badge text-bg-light">{{ $kw['term'] ?? '' }} <small
                                class="text-muted">×{{ $kw['count'] ?? 1 }}</small></span>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Rekomendasi Sistem</strong></div>
            <div class="list-group list-group-flush">
                @forelse($analisis->rekomendasis as $r)
                    <div class="list-group-item d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">{{ $r->judul }}</div>
                            <div class="small text-muted">{{ $r->deskripsi }}</div>
                            <div class="small">Severity: <span class="badge text-bg-secondary">{{ $r->severity }}</span>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted mb-1">Status: <strong>{{ $r->status }}</strong></div>
                            @if ($r->status === 'suggested')
                                <form class="d-inline" method="post"
                                    action="{{ route('guru.analisis.decide', [$analisis->id, $r->id]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="accept">
                                    <button class="btn btn-sm btn-success">Terima</button>
                                </form>
                                <form class="d-inline" method="post"
                                    action="{{ route('guru.analisis.decide', [$analisis->id, $r->id]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <button class="btn btn-sm btn-outline-danger">Tolak</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-muted">Belum ada rekomendasi.</div>
                @endforelse
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Semua Input dalam Rentang</strong>
                <span class="small text-muted">
                    Total: {{ ($refleksisSelf->count() ?? 0) + ($friendReports->count() ?? 0) + ($guruNotes->count() ?? 0) }} item
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <h6 class="mb-2">Refleksi Diri <span class="badge text-bg-secondary">{{ $refleksisSelf->count() ?? 0 }}</span></h6>
                        <div class="list-group">
                            @forelse(($refleksisSelf ?? collect()) as $it)
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div class="small text-muted">{{ \Illuminate\Support\Carbon::parse($it->tanggal)->toDateString() }}</div>
                                        @if(!empty($it->avg_emosi))
                                            <div class="small">Mood: <strong>{{ number_format($it->avg_emosi, 2) }}</strong></div>
                                        @endif
                                    </div>
                                    <div class="mt-1">{{ \Illuminate\Support\Str::limit($it->teks, 120) }}</div>
                                    @if(($it->kategoris ?? collect())->count())
                                        <div class="mt-1 small">
                                            @foreach ($it->kategoris as $k)
                                                <span class="badge text-bg-light">{{ $k->nama }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="text-muted small">Tidak ada data.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h6 class="mb-2">Laporan Teman <span class="badge text-bg-secondary">{{ $friendReports->count() ?? 0 }}</span></h6>
                        <div class="list-group">
                            @forelse(($friendReports ?? collect()) as $it)
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div class="small text-muted">{{ \Illuminate\Support\Carbon::parse($it->tanggal)->toDateString() }}</div>
                                        @if($it->siswaKelas && $it->siswaKelas->siswa && $it->siswaKelas->siswa->user)
                                            <div class="small">Pelapor: <strong>{{ $it->siswaKelas->siswa->user->name }}</strong></div>
                                        @endif
                                    </div>
                                    <div class="mt-1">{{ \Illuminate\Support\Str::limit($it->teks, 120) }}</div>
                                    @if(($it->kategoris ?? collect())->count())
                                        <div class="mt-1 small">
                                            @foreach ($it->kategoris as $k)
                                                <span class="badge text-bg-light">{{ $k->nama }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="text-muted small">Tidak ada data.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h6 class="mb-2">Observasi Guru <span class="badge text-bg-secondary">{{ $guruNotes->count() ?? 0 }}</span></h6>
                        <div class="list-group">
                            @forelse(($guruNotes ?? collect()) as $it)
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div class="small text-muted">{{ \Illuminate\Support\Carbon::parse($it->tanggal)->toDateString() }}</div>
                                        @if(!empty($it->kondisi_siswa))
                                            <div class="small">Kondisi: <strong>{{ $it->kondisi_siswa }}</strong></div>
                                        @endif
                                    </div>
                                    <div class="mt-1">{{ \Illuminate\Support\Str::limit($it->teks, 120) }}</div>
                                    @if(($it->kategoris ?? collect())->count())
                                        <div class="mt-1 small">
                                            @foreach ($it->kategoris as $k)
                                                <span class="badge text-bg-light">{{ $k->nama }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="text-muted small">Tidak ada data.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
