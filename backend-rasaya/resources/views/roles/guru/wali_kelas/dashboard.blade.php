@extends('layouts.guru')

@section('title', 'Dashboard Wali Kelas')

@section('content')
<div class="container-fluid">
	<div class="row">
		<main class="col-12 col-md-9 col-lg-10 p-4">
			<div class="d-flex align-items-center justify-content-between mb-3">
				<div>
					<h2 class="h4 mb-1">Dashboard Wali Kelas</h2>
					<div class="text-muted">Halo, {{ auth()->user()->name }}</div>
				</div>
				<form method="POST" action="{{ route('logout') }}">@csrf
					<button class="btn btn-outline-secondary btn-sm">Keluar</button>
				</form>
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
