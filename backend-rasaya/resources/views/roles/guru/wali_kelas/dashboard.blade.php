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

			@if(!auth()->user()->password_changed_at && auth()->user()->initial_password)
			<div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
				<span class="fw-semibold">Segera ubah password anda.</span>
				<span class="small">Gunakan token awal sebagai Password lama pada form ubah password.</span>
				<a href="{{ route('guru.profile.index', ['pwd' => 1]) }}" class="btn btn-sm btn-outline-dark ms-auto">Ubah Sekarang</a>
			</div>
			@endif

			{{-- Chart Section --}}
			<div class="row g-3 mb-4">
				<div class="col-12">
					<div class="card shadow-sm card-guru-accent">
						<div class="card-body">
							<div class="d-flex align-items-center justify-content-between">
								<div>
									<div class="text-muted small mb-1">📈 Statistik</div>
									<div class="fs-5 fw-semibold">Tren Emosi Siswa (Kelas Anda)</div>
								</div>
								<a href="{{ route('guru.tren_emosi.index') }}" class="btn btn-sm" style="background: var(--guru-navy); color: white;">Lihat Semua</a>
							</div>
							<div class="d-flex align-items-center gap-2 mt-3">
								<select id="wk-period" class="form-select form-select-sm" style="max-width:180px;">
									<option value="daily" selected>Harian</option>
									<option value="weekly">Mingguan</option>
									<option value="monthly">Bulanan</option>
								</select>
								<small id="wk-meta" class="text-muted"></small>
							</div>
							<canvas id="wk-emosi-chart" height="140" class="mt-3"></canvas>
						</div>
					</div>
				</div>
			</div>

			{{-- Menu Cards Grid --}}
			<div class="row g-3">
				<div class="col-12 col-md-6 col-xl-4">
					<div class="card shadow-sm h-100 card-guru-pink">
						<div class="card-body">
							<div class="d-flex align-items-center justify-content-between mb-2">
								<span class="fs-2">📝</span>
								<span class="badge rounded-pill" style="background: var(--guru-pink-dark);">Data Input</span>
							</div>
							<div class="text-muted small mb-1">Observasi Kelas</div>
							<div class="fs-6 fw-semibold mb-3">Input Guru</div>
							<a href="{{ route('guru.observasi.index') }}" class="btn btn-sm w-100 stretched-link" style="background: var(--guru-navy); color: white;">Buka</a>
						</div>
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-4">
					<div class="card shadow-sm h-100 card-guru-pink">
						<div class="card-body">
							<div class="d-flex align-items-center justify-content-between mb-2">
								<span class="fs-2">🔍</span>
								<span class="badge rounded-pill" style="background: var(--guru-navy);">Review</span>
							</div>
							<div class="text-muted small mb-1">Hasil Analisis</div>
							<div class="fs-6 fw-semibold mb-3">Analisis Input</div>
							<a href="{{ route('guru.analisis.index') }}" class="btn btn-sm w-100 stretched-link" style="background: var(--guru-pink-dark); color: white;">Lihat</a>
						</div>
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-4">
					<div class="card shadow-sm h-100 card-guru-pink">
						<div class="card-body">
							<div class="d-flex align-items-center justify-content-between mb-2">
								<span class="fs-2">📊</span>
								<span class="badge rounded-pill" style="background: var(--guru-pink-dark);">Monitoring</span>
							</div>
							<div class="text-muted small mb-1">Pelacak Suasana Hati</div>
							<div class="fs-6 fw-semibold mb-3">Tren Emosi</div>
							<a href="{{ route('guru.tren_emosi.index') }}" class="btn btn-sm w-100 stretched-link" style="background: var(--guru-navy); color: white;">Lihat Data</a>
						</div>
					</div>
				</div>

				<div class="col-12 col-md-6 col-xl-4">
					<div class="card shadow-sm h-100 card-guru-pink">
						<div class="card-body">
							<div class="d-flex align-items-center justify-content-between mb-2">
								<span class="fs-2">💭</span>
								<span class="badge rounded-pill" style="background: var(--guru-navy);">Jurnal</span>
							</div>
							<div class="text-muted small mb-1">Laporan Diri Siswa</div>
							<div class="fs-6 fw-semibold mb-3">Refleksi Siswa</div>
							<a href="{{ route('guru.refleksi.index') }}" class="btn btn-sm w-100 stretched-link" style="background: var(--guru-pink-dark); color: white;">Baca</a>
						</div>
					</div>
				</div>
			</div>

			{{-- Siswa Butuh Perhatian (Merah) --}}
			@if(($attentionList ?? collect())->isNotEmpty())
			<div class="card mt-4 shadow-sm border-danger">
				<div class="card-header bg-danger text-white fw-semibold">
					<i class="bi bi-exclamation-triangle-fill me-2"></i>Siswa Butuh Perhatian (Kelas Anda)
				</div>
				<div class="list-group list-group-flush">
					@foreach($attentionList as $a)
						@php($ageDays = optional($a->created_at)->diffInDays(now()))
						@php($overdue = $ageDays >= 2)
						<a href="{{ route('guru.analisis.show', $a->id) }}" class="list-group-item list-group-item-action list-group-item-danger d-flex justify-content-between align-items-start">
							<div>
								<div class="fw-semibold {{ $overdue ? 'text-danger' : '' }}">{{ optional($a->siswaKelas->siswa->user)->name }}
									@if($overdue)
										<span class="badge bg-danger ms-2">Reminder {{ $ageDays }} hari</span>
									@else
										<span class="badge bg-danger ms-2">Butuh Perhatian</span>
									@endif
								</div>
								<div class="small text-muted">
									{{ optional($a->siswaKelas->kelas)->label }} — TA {{ optional($a->siswaKelas->kelas?->tahunAjaran)->nama }}
								</div>
							</div>
							<div class="text-end small text-muted">
								<div>{{ optional($a->created_at)->diffForHumans() }}</div>
							</div>
						</a>
					@endforeach
				</div>
			</div>
			@endif

			{{-- Siswa Sedang Ditangani (Orange) --}}
			@if(($handledList ?? collect())->isNotEmpty())
			<div class="card mt-4 shadow-sm border-warning">
				<div class="card-header bg-warning text-dark fw-semibold">
					<i class="bi bi-hourglass-split me-2"></i>Siswa Sedang Ditangani (Kelas Anda)
				</div>
				<div class="list-group list-group-flush">
					@foreach($handledList as $a)
						@php($ageDays = optional($a->created_at)->diffInDays(now()))
						<a href="{{ route('guru.analisis.show', $a->id) }}" class="list-group-item list-group-item-action list-group-item-warning d-flex justify-content-between align-items-start">
							<div>
								<div class="fw-semibold">{{ optional($a->siswaKelas->siswa->user)->name }}
									<span class="badge bg-warning text-dark ms-2">Sedang Ditangani</span>
								</div>
								<div class="small text-muted">
									{{ optional($a->siswaKelas->kelas)->label }} — TA {{ optional($a->siswaKelas->kelas?->tahunAjaran)->nama }}
								</div>
							</div>
							<div class="text-end small text-muted">
								<div>{{ optional($a->created_at)->diffForHumans() }}</div>
								<div class="small">{{ $ageDays }} hari ditangani</div>
							</div>
						</a>
					@endforeach
				</div>
			</div>
			@endif

			{{-- Jadwal Konseling Siswa di Kelas (Read-only untuk reminder) --}}
			@if(isset($konselingSchedules) && $konselingSchedules->count() > 0)
			<div class="card shadow-sm mt-4" style="border-left: 4px solid var(--guru-pink); background: rgba(236, 72, 153, 0.03);">
				<div class="card-body">
					<div class="d-flex align-items-center justify-content-between mb-3">
						<div>
							<h5 class="mb-0" style="color: var(--guru-pink-dark);">
								<i class="bi bi-calendar-check me-2"></i>Jadwal Konseling Siswa
							</h5>
							<small class="text-muted">Siswa di kelas Anda yang akan konseling (7 hari ke depan)</small>
						</div>
					</div>
					<div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
						<i class="bi bi-info-circle me-2"></i>
						<strong>Info:</strong> Ini adalah jadwal konseling siswa di kelas Anda dengan Guru BK. Anda dapat mengingatkan siswa untuk datang tepat waktu.
						<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
					</div>
					<div class="table-responsive">
						<table class="table table-sm table-hover mb-0">
							<thead>
								<tr style="background: rgba(236, 72, 153, 0.08);">
									<th style="color: var(--guru-navy);">Waktu</th>
									<th style="color: var(--guru-navy);">Siswa</th>
									<th style="color: var(--guru-navy);">Konselor</th>
									<th style="color: var(--guru-navy);">Status</th>
								</tr>
							</thead>
							<tbody>
								@foreach($konselingSchedules as $booking)
								<tr>
									<td>
										<div class="fw-semibold" style="color: var(--guru-navy);">
											{{ $booking->slot->start_at->format('d M Y') }}
										</div>
										<small class="text-muted">
											{{ $booking->slot->start_at->format('H:i') }} - {{ $booking->slot->end_at->format('H:i') }}
										</small>
									</td>
									<td>
										<div class="fw-medium">{{ $booking->siswaKelas->siswa->user->name }}</div>
										<small class="text-muted">{{ $booking->siswaKelas->siswa->user->identifier }}</small>
									</td>
									<td>
										@if($booking->slot && $booking->slot->guru && $booking->slot->guru->user)
											<div class="fw-medium">{{ $booking->slot->guru->user->name }}</div>
											<small class="text-muted">Guru BK</small>
										@else
											<span class="text-muted">-</span>
										@endif
									</td>
									<td>
										@if($booking->status === 'booked')
											<span class="badge bg-success">Booked</span>
										@endif
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
	const sel = document.getElementById('wk-period');
	const meta = document.getElementById('wk-meta');
	let chart;
	// Emoji scale: 10=🤩 Rad, 9=😍 In Love, 8=😎 Chill, 7=😊 Good, 5=😐 Meh/😴 Tired, 4=� Stressed, 3=� Bad, 2=� Overwhelmed, 1=� Awful
	const scoreEmoji = (y)=>{
		const s = Math.round(y);
		if(s>=10) return '🤩';
		if(s>=9) return '😍';
		if(s>=8) return '😎';
		if(s>=7) return '😊';
		if(s>=6) return '😴';
		if(s>=5) return '😐';
		if(s>=4) return '😟';
		if(s>=3) return '😔';
		if(s>=2) return '😭';
		return '😓';
	};
	const lerp = (a,b,t)=>a+(b-a)*t;
	const scoreToColor = (s)=>{
		const clamp = Math.max(1, Math.min(10, s||5));
		const t = (clamp-1)/9;
		const stops = [ {r:239,g:68,b:68}, {r:245,g:158,b:11}, {r:22,g:163,b:74} ];
		const s1 = t<0.5 ? stops[0] : stops[1];
		const s2 = t<0.5 ? stops[1] : stops[2];
		const tt = t<0.5 ? (t/0.5) : ((t-0.5)/0.5);
		const r = Math.round(lerp(s1.r,s2.r,tt));
		const g = Math.round(lerp(s1.g,s2.g,tt));
		const b = Math.round(lerp(s1.b,s2.b,tt));
		return `rgb(${r}, ${g}, ${b})`;
	};
	const todayLocal = ()=>{ const d=new Date(); d.setMinutes(d.getMinutes()-d.getTimezoneOffset()); return d.toISOString().slice(0,10); };
	async function load(){
		// Default WK dashboard: bar per siswa (hari ini)
		const params = new URLSearchParams({ period: sel.value || 'daily', from: todayLocal(), to: todayLocal(), group: 'siswa' });
		const res = await fetch(`{{ route('guru.tren_emosi.data') }}?${params.toString()}`, { headers:{ 'Accept':'application/json' } });
		const data = await res.json();
		const ys = data.datasets?.[0]?.data || [];
		const labels = data.labels || [];
		const colors = data.colors || ys.map(scoreToColor);
		const ctx = document.getElementById('wk-emosi-chart').getContext('2d');
		if (chart) chart.destroy();
		chart = new Chart(ctx, {
			type:'bar',
			data:{ labels, datasets:[{ label:'Rata-rata Emosi', data:ys, backgroundColor: colors, borderWidth:0, borderRadius:6 }] },
			options:{ indexAxis:'y', scales:{ x:{ suggestedMin:1, suggestedMax:10, ticks:{ stepSize:1, callback:(v)=>scoreEmoji(v) } }, y:{ ticks:{ autoSkip:false } } }, plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(ctx)=>` ${scoreEmoji(ctx.parsed.x)}  avg ${ctx.parsed.x}` } } } }
		});
		meta.textContent = `Periode: ${data.period} — ${data.from} s/d ${data.to}`;
	}
	sel?.addEventListener('change', load);
	load();
})();
</script>
@endpush
