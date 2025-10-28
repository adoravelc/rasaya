@extends('layouts.guru')

@section('title', 'Dashboard Wali Kelas')

@section('content')
<div class="container-fluid">
	<div class="row">
		<main class="col-12 col-md-9 col-lg-10 p-4">
			@php
				$kelasWk = \App\Models\Kelas::with(['tahunAjaran'])
					->where('wali_guru_id', auth()->id())
					->latest('tahun_ajaran_id')
					->first();
			@endphp
			<div class="d-flex align-items-center justify-content-between mb-3">
				<div>
					<h2 class="h4 mb-1">Dashboard Wali Kelas</h2>
					<div class="text-muted">Halo, {{ auth()->user()->name }}</div>
					<div class="mt-2">
						<div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 border bg-light-subtle">
							<div class="small text-muted">Identitas Wali Kelas</div>
							@if($kelasWk)
								<span class="badge text-bg-primary">{{ $kelasWk->label }}</span>
								<span class="badge text-bg-secondary">TA {{ $kelasWk->tahunAjaran->nama ?? '-' }}</span>
							@else
								<span class="text-muted small">Belum terdaftar pada tahun ajaran aktif</span>
							@endif
						</div>
					</div>
				</div>
			</div>

			<div class="row g-3">
				<div class="col-12 col-md-6 col-xl-4">
					<div class="card shadow-sm h-100">
						<div class="card-body">
							<div class="d-flex align-items-center justify-content-between">
								<div>
									<div class="text-muted small mb-1">Observasi</div>
									<div class="fs-5 fw-semibold">Input Guru</div>
								</div>
								<span class="display-6">📝</span>
							</div>
							<a href="{{ route('guru.observasi.index') }}" class="btn btn-primary btn-sm mt-3 stretched-link">Buka</a>
						</div>
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-4">
					<div class="card shadow-sm h-100">
						<div class="card-body">
							<div class="d-flex align-items-center justify-content-between">
								<div>
									<div class="text-muted small mb-1">Kelas</div>
									<div class="fs-5 fw-semibold">Rekap Siswa</div>
								</div>
								<span class="display-6">📋</span>
							</div>
							<a href="#" class="btn btn-outline-secondary btn-sm mt-3 disabled" aria-disabled="true">Segera</a>
						</div>
					</div>
				</div>
			</div>

			@if(($attentionList ?? collect())->isNotEmpty())
			<div class="card mt-4 shadow-sm">
				<div class="card-header bg-white fw-semibold">Siswa Perlu Perhatian (Kelas Anda)</div>
				<div class="list-group list-group-flush">
					@foreach($attentionList as $a)
						<a href="{{ route('guru.analisis.show', $a->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start">
							<div>
								<div class="fw-semibold">{{ optional($a->siswaKelas->siswa->user)->name }}</div>
								<div class="small text-muted">
									{{ optional($a->siswaKelas->kelas)->label }} — TA {{ optional($a->siswaKelas->kelas?->tahunAjaran)->nama }}
								</div>
							</div>
							<div class="text-end small text-muted">
								<div>Analisis: {{ optional($a->created_at)->diffForHumans() }}</div>
							</div>
						</a>
					@endforeach
				</div>
			</div>
			@endif

			<div class="card mt-4 shadow-sm">
				<div class="card-header bg-white fw-semibold">Hari Ini</div>
				<div class="card-body">
					<div class="row g-3">
						<div class="col-md-4">
							<div class="p-3 border rounded-3 h-100">
								<div class="small text-muted">Tanggal</div>
								<div class="fs-5">{{ now('Asia/Makassar')->translatedFormat('l, d M Y') }}</div>
							</div>
						</div>
						<div class="col-md-4">
							<div class="p-3 border rounded-3 h-100">
								<div class="small text-muted">Akun</div>
								<div class="fs-5">{{ auth()->user()->email }}</div>
							</div>
						</div>
						<div class="col-md-4">
							<div class="p-3 border rounded-3 h-100">
								<div class="small text-muted">Peran</div>
								<div class="fs-5">Wali Kelas</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</main>
	</div>
</div>
@endsection
